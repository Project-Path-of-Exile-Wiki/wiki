<?php
/**
 * CargoSQLQuery - a wrapper class around SQL queries, that also handles
 * the special Cargo keywords like "HOLDS" and "NEAR".
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoSQLQuery {

	private $mCargoDB;
	public $mTablesStr;
	public $mAliasedTableNames;
	public $mFieldsStr;
	public $mOrigWhereStr;
	public $mWhereStr;
	public $mJoinOnStr;
	public $mCargoJoinConds;
	public $mJoinConds;
	public $mAliasedFieldNames;
	public $mOrigAliasedFieldNames;
	public $mFieldStringAliases;
	public $mTableSchemas;
	public $mFieldDescriptions;
	public $mFieldTables;
	public $mOrigGroupByStr;
	public $mGroupByStr;
	public $mOrigHavingStr;
	public $mHavingStr;
	public $mOrderBy;
	public $mQueryLimit;
	public $mOffset;
	public $mSearchTerms = [];

	public function __construct() {
		$this->mCargoDB = CargoUtils::getDB();
	}

	/**
	 * This is newFromValues() instead of __construct() so that an
	 * object can be created without any values.
	 */
	public static function newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr,
		$havingStr, $orderByStr, $limitStr, $offsetStr ) {
		global $wgCargoDefaultQueryLimit, $wgCargoMaxQueryLimit;

		// "table(s)" is the only mandatory value.
		if ( $tablesStr == '' ) {
			throw new MWException( "At least one table must be specified." );
		}

		self::validateValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr,
			$havingStr, $orderByStr, $limitStr, $offsetStr );

		$sqlQuery = new CargoSQLQuery();
		$sqlQuery->mCargoDB = CargoUtils::getDB();
		$sqlQuery->mTablesStr = $tablesStr;
		$sqlQuery->setAliasedTableNames();
		$sqlQuery->mFieldsStr = $fieldsStr;
		// This _decode() call is necessary because the "where="
		// clause can (and often does) include a call to {{PAGENAME}},
		// which HTML-encodes certain characters, notably single quotes.
		$sqlQuery->mOrigWhereStr = htmlspecialchars_decode( $whereStr, ENT_QUOTES );
		$sqlQuery->mWhereStr = $sqlQuery->mOrigWhereStr;
		$sqlQuery->mJoinOnStr = $joinOnStr;
		$sqlQuery->setCargoJoinConds( $joinOnStr );
		$sqlQuery->setAliasedFieldNames();
		$sqlQuery->mTableSchemas = CargoUtils::getTableSchemas( $sqlQuery->mAliasedTableNames );
		$sqlQuery->setOrderBy( $orderByStr );
		$sqlQuery->setGroupBy( $groupByStr );
		$sqlQuery->mOrigHavingStr = $havingStr;
		$sqlQuery->mHavingStr = $sqlQuery->mOrigHavingStr;
		$sqlQuery->setDescriptionsAndTableNamesForFields();
		$sqlQuery->handleHierarchyFields();
		$sqlQuery->handleVirtualFields();
		$sqlQuery->handleVirtualCoordinateFields();
		$sqlQuery->handleDateFields();
		$sqlQuery->handleSearchTextFields();
		$sqlQuery->setMWJoinConds();
		$sqlQuery->mQueryLimit = $wgCargoDefaultQueryLimit;
		if ( $limitStr != '' ) {
			$sqlQuery->mQueryLimit = min( $limitStr, $wgCargoMaxQueryLimit );
		}
		$sqlQuery->mOffset = $offsetStr;
		$sqlQuery->addTablePrefixesToAll();

		return $sqlQuery;
	}

	/**
	 * Throw an error if there are forbidden values in any of the
	 * #cargo_query parameters - some or all of them are potential
	 * security risks.
	 *
	 * It could be that, given the way #cargo_query is structured, only
	 * some of the parameters need to be checked for these strings,
	 * but we might as well validate all of them.
	 *
	 * The function CargoUtils::getTableSchemas() also does specific
	 * validation of the "tables" parameter, while this class's
	 * setDescriptionsAndTableNameForFields() does validation of the
	 * "fields=" parameter.
	 */
	public static function validateValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr, $groupByStr,
		$havingStr, $orderByStr, $limitStr, $offsetStr ) {
		// Remove quoted strings from "where" parameter, to avoid
		// unnecessary false positives from words like "from"
		// being included in string comparisons.
		// However, before we do that, check for certain strings that
		// shouldn't be in quote marks either.
		$whereStrRegexps = [
			'/\-\-/' => '--',
			'/#/' => '#',
		];

		// HTML-decode the string - this is necessary if the query
		// contains a call to {{PAGENAME}} and the page name has any
		// special characters, because {{PAGENAME]] unfortunately
		// HTML-encodes the value, which leads to a '#' in the string.
		$decodedWhereStr = html_entity_decode( $whereStr, ENT_QUOTES );
		foreach ( $whereStrRegexps as $regexp => $displayString ) {
			if ( preg_match( $regexp, $decodedWhereStr ) ) {
				throw new MWException( "Error in \"where\" parameter: the string \"$displayString\" cannot be used within #cargo_query." );
			}
		}
		$noQuotesFieldsStr = CargoUtils::removeQuotedStrings( $fieldsStr );
		$noQuotesWhereStr = CargoUtils::removeQuotedStrings( $whereStr );
		$noQuotesJoinOnStr = CargoUtils::removeQuotedStrings( $joinOnStr );
		$noQuotesGroupByStr = CargoUtils::removeQuotedStrings( $groupByStr );
		$noQuotesHavingStr = CargoUtils::removeQuotedStrings( $havingStr );
		$noQuotesOrderByStr = CargoUtils::removeQuotedStrings( $orderByStr );

		$regexps = [
			'/\bselect\b/i' => 'SELECT',
			'/\binto\b/i' => 'INTO',
			'/\bfrom\b/i' => 'FROM',
			'/\bunion\b/i' => 'UNION',
			'/;/' => ';',
			'/@/' => '@',
			'/\<\?/' => '<?',
			'/\-\-/' => '--',
			'/\/\*/' => '/*',
			'/#/' => '#',
		];
		foreach ( $regexps as $regexp => $displayString ) {
			if ( preg_match( $regexp, $tablesStr ) ||
				preg_match( $regexp, $noQuotesFieldsStr ) ||
				preg_match( $regexp, $noQuotesWhereStr ) ||
				preg_match( $regexp, $noQuotesJoinOnStr ) ||
				preg_match( $regexp, $noQuotesGroupByStr ) ||
				preg_match( $regexp, $noQuotesHavingStr ) ||
				preg_match( $regexp, $noQuotesOrderByStr ) ||
				preg_match( $regexp, $limitStr ) ||
				preg_match( $regexp, $offsetStr ) ) {
				throw new MWException( "Error: the string \"$displayString\" cannot be used within #cargo_query." );
			}
		}

		self::getAndValidateSQLFunctions( $noQuotesWhereStr );
		self::getAndValidateSQLFunctions( $noQuotesJoinOnStr );
		self::getAndValidateSQLFunctions( $noQuotesGroupByStr );
		self::getAndValidateSQLFunctions( $noQuotesHavingStr );
		self::getAndValidateSQLFunctions( $noQuotesOrderByStr );
		self::getAndValidateSQLFunctions( $limitStr );
		self::getAndValidateSQLFunctions( $offsetStr );
	}

	/**
	 * Gets a mapping of original field name strings to their field name aliases
	 * as they appear in the query result
	 */
	public function getAliasForFieldString( $fieldString ) {
		return $this->mFieldStringAliases[$fieldString];
	}

	/**
	 * Gets an array of field names and their aliases from the passed-in
	 * SQL fragment.
	 */
	private function setAliasedFieldNames() {
		$this->mAliasedFieldNames = [];
		$fieldStrings = CargoUtils::smartSplit( ',', $this->mFieldsStr );
		// Default is "_pageName".
		if ( count( $fieldStrings ) == 0 ) {
			$fieldStrings[] = '_pageName';
		}

		// Quick error-checking: for now, just disallow "DISTINCT",
		// and require "GROUP BY" instead.
		foreach ( $fieldStrings as $i => $fieldString ) {
			if ( strtolower( substr( $fieldString, 0, 9 ) ) == 'distinct ' ) {
				throw new MWException( "Error: The DISTINCT keyword is not allowed by Cargo; "
				. "please use \"group by=\" instead." );
			}
		}

		// Because aliases are used as keys, we can't have more than
		// one blank alias - so replace blank aliases with the name
		// "Blank value X" - it will get replaced back before being
		// displayed.
		$blankAliasCount = 0;
		foreach ( $fieldStrings as $i => $fieldString ) {
			$fieldStringParts = CargoUtils::smartSplit( '=', $fieldString, true );
			if ( count( $fieldStringParts ) == 2 ) {
				$fieldName = trim( $fieldStringParts[0] );
				$alias = trim( $fieldStringParts[1] );
			} else {
				$fieldName = $fieldString;
				// Might as well change underscores to spaces
				// by default - but for regular field names,
				// not the special ones.
				// "Real" field = with the table name removed.
				if ( strpos( $fieldName, '.' ) !== false ) {
					list( $tableName, $realFieldName ) = explode( '.', $fieldName, 2 );
				} else {
					$realFieldName = $fieldName;
				}
				if ( $realFieldName[0] != '_' ) {
					$alias = str_replace( '_', ' ', $realFieldName );
				} else {
					$alias = $realFieldName;
				}
			}
			if ( empty( $alias ) ) {
				$blankAliasCount++;
				$alias = "Blank value $blankAliasCount";
			}
			$this->mAliasedFieldNames[$alias] = $fieldName;
			$this->mFieldStringAliases[$fieldString] = $alias;
		}
		$this->mOrigAliasedFieldNames = $this->mAliasedFieldNames;
	}

	private function setAliasedTableNames() {
		$this->mAliasedTableNames = [];
		$tableStrings = CargoUtils::smartSplit( ',', $this->mTablesStr );

		foreach ( $tableStrings as $i => $tableString ) {
			$tableStringParts = CargoUtils::smartSplit( '=', $tableString );
			if ( count( $tableStringParts ) == 2 ) {
				$tableName = trim( $tableStringParts[0] );
				$alias = trim( $tableStringParts[1] );
			} else {
				$tableName = $tableString;
				$alias = $tableString;
			}
			if ( empty( $alias ) ) {
				throw new MWException( "Error: blank table aliases cannot be set." );
			}
			$this->mAliasedTableNames[$alias] = $tableName;
		}
	}

	/**
	 * This does double duty: it both creates a "join conds" array
	 * from the string, and validates the set of join conditions
	 * based on the set of table names - making sure each table is
	 * joined.
	 *
	 * The "join conds" array created is not of the format that
	 * MediaWiki's database query() method requires - it is more
	 * structured and does not contain the necessary table prefixes yet.
	 */
	private function setCargoJoinConds( $joinOnStr ) {
		// This string is needed for "deferred" queries.
		$this->mJoinOnStr = $joinOnStr;

		$this->mCargoJoinConds = [];

		if ( trim( $joinOnStr ) == '' ) {
			if ( count( $this->mAliasedTableNames ) > 1 ) {
				throw new MWException( "Error: join conditions must be set for tables." );
			}
			return;
		}

		$joinStrings = explode( ',', $joinOnStr );
		// 'HOLDS' must be all-caps for now.
		$allowedJoinOperators = [ '=', ' HOLDS ', '<=', '>=', '<', '>' ];
		$joinOperator = null;

		foreach ( $joinStrings as $joinString ) {
			$foundValidOperator = false;
			foreach ( $allowedJoinOperators as $allowedOperator ) {
				if ( strpos( $joinString, $allowedOperator ) === false ) {
					continue;
				}
				$foundValidOperator = true;
				$joinOperator = $allowedOperator;
				break;
			}

			if ( !$foundValidOperator ) {
				throw new MWException( "No valid operator found in join condition ($joinString)." );
			}

			$joinParts = explode( $joinOperator, $joinString );
			$joinPart1 = trim( $joinParts[0] );
			$tableAndField1 = explode( '.', $joinPart1 );
			if ( count( $tableAndField1 ) != 2 ) {
				throw new MWException( "Table and field name must both be specified in '$joinPart1'." );
			}
			list( $table1, $field1 ) = $tableAndField1;
			$joinPart2 = trim( $joinParts[1] );
			$tableAndField2 = explode( '.', $joinPart2 );
			if ( count( $tableAndField2 ) != 2 ) {
				throw new MWException( "Table and field name must both be specified in '$joinPart2'." );
			}
			list( $table2, $field2 ) = $tableAndField2;
			$joinCond = [
				'joinType' => 'LEFT OUTER JOIN',
				'table1' => $table1,
				'field1' => $field1,
				'table2' => $table2,
				'field2' => $field2,
				'joinOperator' => $joinOperator
			];
			$this->mCargoJoinConds[] = $joinCond;
		}

		// Now validate, to make sure that all the tables
		// are "joined" together. There's probably some more
		// efficient network algorithm for this sort of thing, but
		// oh well.
		$numUnmatchedTables = count( $this->mAliasedTableNames );
		$firstJoinCond = current( $this->mCargoJoinConds );
		$firstTableInJoins = $firstJoinCond['table1'];
		$matchedTables = [ $firstTableInJoins ];
		// We will check against aliases, not table names.
		$allPossibleTableAliases = [];
		foreach ( $this->mAliasedTableNames as $tableAlias => $tableName ) {
			$allPossibleTableAliases[] = $tableAlias;
			// This is useful for at least PostgreSQL.
			$allPossibleTableAliases[] = $this->mCargoDB->addIdentifierQuotes( $tableAlias );
		}
		do {
			$previousNumUnmatchedTables = $numUnmatchedTables;
			foreach ( $this->mCargoJoinConds as $joinCond ) {
				$table1 = $joinCond['table1'];
				$table2 = $joinCond['table2'];
				if ( !in_array( $table1, $allPossibleTableAliases ) ) {
					throw new MWException( "Error: table \"$table1\" is not in list of table names or aliases." );
				}
				if ( !in_array( $table2, $allPossibleTableAliases ) ) {
					throw new MWException( "Error: table \"$table2\" is not in list of table names or aliases." );
				}

				if ( in_array( $table1, $matchedTables ) && !in_array( $table2, $matchedTables ) ) {
					$matchedTables[] = $table2;
					$numUnmatchedTables--;
				}
				if ( in_array( $table2, $matchedTables ) && !in_array( $table1, $matchedTables ) ) {
					$matchedTables[] = $table1;
					$numUnmatchedTables--;
				}
			}
		} while ( $numUnmatchedTables > 0 && $numUnmatchedTables > $previousNumUnmatchedTables );

		if ( $numUnmatchedTables > 0 ) {
			foreach ( array_keys( $this->mAliasedTableNames ) as $tableAlias ) {
				$escapedTableAlias = $this->mCargoDB->addIdentifierQuotes( $tableAlias );
				if ( !in_array( $tableAlias, $matchedTables ) &&
					!in_array( $escapedTableAlias, $matchedTables ) ) {
					throw new MWException( "Error: Table \"$tableAlias\" is not included within the "
					. "join conditions." );
				}
			}
		}
	}

	/**
	 * Turn the very structured format that Cargo uses for join
	 * conditions into the one that MediaWiki uses - this includes
	 * adding the database prefix to each table name.
	 */
	private function setMWJoinConds() {
		if ( $this->mCargoJoinConds == null ) {
			return;
		}

		$this->mJoinConds = [];
		foreach ( $this->mCargoJoinConds as $cargoJoinCond ) {
			// Only add the DB prefix to the table names if
			// they're true table names and not aliases.
			$table1 = $cargoJoinCond['table1'];
			if ( !array_key_exists( $table1, $this->mAliasedTableNames ) || $this->mAliasedTableNames[$table1] == $table1 ) {
				$cargoTable1 = $this->mCargoDB->tableName( $table1 );
			} else {
				$cargoTable1 = $this->mCargoDB->addIdentifierQuotes( $table1 );
			}
			$table2 = $cargoJoinCond['table2'];
			if ( !array_key_exists( $table2, $this->mAliasedTableNames ) || $this->mAliasedTableNames[$table2] == $table2 ) {
				$cargoTable2 = $this->mCargoDB->tableName( $table2 );
			} else {
				$cargoTable2 = $this->mCargoDB->addIdentifierQuotes( $table2 );
			}
			if ( array_key_exists( 'joinOperator', $cargoJoinCond ) ) {
				$joinOperator = $cargoJoinCond['joinOperator'];
			} else {
				$joinOperator = '=';
			}

			$field1 = $this->mCargoDB->addIdentifierQuotes( $cargoJoinCond['field1'] );
			$field2 = $this->mCargoDB->addIdentifierQuotes( $cargoJoinCond['field2'] );
			$joinCondConds = [
				$cargoTable1 . '.' . $field1 . $joinOperator .
				$cargoTable2 . '.' . $field2
			];
			if ( array_key_exists( 'extraCond', $cargoJoinCond ) ) {
				$joinCondConds[] = $cargoJoinCond['extraCond'];
			}
			if ( !array_key_exists( $table2, $this->mJoinConds ) ) {
				$this->mJoinConds[$table2] = [
					$cargoJoinCond['joinType'],
					$joinCondConds
				];
			} else {
				$this->mJoinConds[$table2][1] = array_merge(
					$this->mJoinConds[$table2][1],
					$joinCondConds
				);
			}
		}
	}

	public function setOrderBy( $orderByStr = null ) {
		$this->mOrderBy = [];
		if ( $orderByStr != '' ) {
			$orderByElements = CargoUtils::smartSplit( ',', $orderByStr );
			foreach ( $orderByElements as $elem ) {
				// Get rid of 'ASC' - it's never needed.
				if ( substr( $elem, -4 ) == ' ASC' ) {
					$elem = trim( substr( $elem, 0, strlen( $elem ) - 4 ) );
				}
				// If it has "DESC" at the end, remove it, then
				// add it back in later.
				$hasDesc = ( substr( $elem, -5 ) == ' DESC' );
				if ( $hasDesc ) {
					$elem = trim( substr( $elem, 0, strlen( $elem ) - 5 ) );
				}
				if ( strpos( $elem, '(' ) === false && strpos( $elem, '.' ) === false && !$this->mCargoDB->isQuotedIdentifier( $elem ) ) {
					$elem = $this->mCargoDB->addIdentifierQuotes( $elem );
				}
				if ( $hasDesc ) {
					$elem .= ' DESC';
				}
				$this->mOrderBy[] = $elem;
			}
		} else {
			// By default, sort on up to the first five fields, in
			// the order in which they're defined. Five seems like
			// enough to make sure everything is in the right order,
			// no? Or should it always be all the fields?
			$fieldNum = 1;
			foreach ( $this->mAliasedFieldNames as $fieldName ) {
				if ( strpos( $fieldName, '(' ) === false && strpos( $fieldName, '.' ) === false ) {
					$this->mOrderBy[] = $this->mCargoDB->addIdentifierQuotes( $fieldName );
				} else {
					$this->mOrderBy[] = $fieldName;
				}
				$fieldNum++;
				if ( $fieldNum > 5 ) {
					break;
				}
			}
		}
	}

	public function setGroupBy( $groupByStr ) {
		// @TODO - $mGroupByStr should turn into an array named
		// $mGroupBy for better handling of mulitple values, as was
		// done with $mOrderBy.
		$this->mOrigGroupByStr = $groupByStr;
		if ( $groupByStr == '' ) {
			$this->mGroupByStr = null;
		} elseif ( strpos( $groupByStr, '(' ) === false && strpos( $groupByStr, '.' ) === false && strpos( $groupByStr, ',' ) === false ) {
			$this->mGroupByStr = $this->mCargoDB->addIdentifierQuotes( $groupByStr );
		} else {
			$this->mGroupByStr = $groupByStr;
		}
	}

	private static function getAndValidateSQLFunctions( $str ) {
		global $wgCargoAllowedSQLFunctions;

		$sqlFunctionMatches = [];
		$sqlFunctionRegex = '/(\b|\W)(\w*?)\s*\(/';
		preg_match_all( $sqlFunctionRegex, $str, $sqlFunctionMatches );
		$sqlFunctions = array_map( 'strtoupper', $sqlFunctionMatches[2] );
		$sqlFunctions = array_map( 'trim', $sqlFunctions );
		// Throw an error if any of these functions
		// are not in our "whitelist" of SQL functions.
		// Also add to this whitelist SQL operators like AND, OR, NOT,
		// etc., because the parsing can mistake these for functions.
		$logicalOperators = [ 'AND', 'OR', 'NOT', 'IN' ];
		$allowedFunctions = array_merge( $wgCargoAllowedSQLFunctions, $logicalOperators );
		foreach ( $sqlFunctions as $sqlFunction ) {
			// @TODO - fix the original regexp to avoid blank
			// strings, so that this check is not necessary.
			if ( $sqlFunction == '' ) {
				continue;
			}
			if ( !in_array( $sqlFunction, $allowedFunctions ) ) {
				throw new MWException( wfMessage( "cargo-query-badsqlfunction", "$sqlFunction()" )->parse() );
			}
		}

		return $sqlFunctions;
	}

	/**
	 * Attempts to get the "field description" (type, etc.), as well as the
	 * table name, of a single field specified in a SELECT call (via a
	 * #cargo_query call), using the set of schemas for all data tables.
	 *
	 * Also does some validation of table names, field names, and any SQL
	 * functions contained in this clause.
	 */
	private function getDescriptionAndTableNameForField( $origFieldName ) {
		$tableName = null;
		$fieldName = null;
		$description = new CargoFieldDescription();

		// We use "\p{L}0-9" instead of \w here in order to
		// handle accented and other non-ASCII characters in
		// table and field names.
		$fieldPattern = '/^([-_\p{L}0-9$]+)([.]([-_\p{L}0-9$]+))?$/u';
		$fieldPatternFound = preg_match( $fieldPattern, $origFieldName, $fieldPatternMatches );
		$stringPatternFound = false;
		$hasFunctionCall = false;

		if ( $fieldPatternFound ) {
			switch ( count( $fieldPatternMatches ) ) {
				case 2:
					$fieldName = $fieldPatternMatches[1];
					break;
				case 4:
					$tableName = $fieldPatternMatches[1];
					$fieldName = $fieldPatternMatches[3];
					break;
			}
		} else {
			$stringPattern = '/^(([\'"]).*?\2)(.+)?$/';
			$stringPatternFound = preg_match( $stringPattern, $origFieldName, $stringPatternMatches );
			if ( $stringPatternFound ) {
				// If the count is 3 we have a single quoted string
				// If the count is 4 we have stuff after it
				$stringPatternFound = count( $stringPatternMatches ) == 3;
			}

			if ( !$stringPatternFound ) {
				$noQuotesOrigFieldName = CargoUtils::removeQuotedStrings( $origFieldName );

				$functionCallPattern = '/\p{L}\s*\(/';
				$hasFunctionCall = preg_match( $functionCallPattern, $noQuotesOrigFieldName );
			}
		}
		// If it's a pre-defined field, we probably know its type.
		if ( $fieldName == '_ID' || $fieldName == '_rowID' || $fieldName == '_pageID' || $fieldName == '_pageNamespace' || $fieldName == '_position' ) {
			$description->mType = 'Integer';
		} elseif ( $fieldName == '_pageTitle' ) {
			// It's a string - do nothing.
		} elseif ( $fieldName == '_pageName' ) {
			$description->mType = 'Page';
		} elseif ( $stringPatternFound ) {
			// It's a quoted, literal string - do nothing.
		} elseif ( $hasFunctionCall ) {
			$sqlFunctions = self::getAndValidateSQLFunctions( $noQuotesOrigFieldName );
			$firstFunction = $sqlFunctions[0];
			// 'ROUND' is in neither the Integer nor Float
			// lists because it sometimes returns an
			// integer, sometimes a float - for formatting
			// purposes, we'll just treat it as a string.
			if ( in_array( $firstFunction, [ 'COUNT', 'FLOOR', 'CEIL' ] ) ) {
				$description->mType = 'Integer';
			} elseif ( in_array( $firstFunction, [ 'SUM', 'POWER', 'LN', 'LOG' ] ) ) {
				$description->mType = 'Float';
			} elseif ( in_array( $firstFunction,
					[ 'DATE', 'DATE_ADD', 'DATE_SUB', 'DATE_DIFF' ] ) ) {
				$description->mType = 'Date';
			} elseif ( in_array( $firstFunction, [ 'TRIM' ] ) ) {
				// @HACK - allow users one string function
				// (TRIM()) that will return a String type, and
				// thus won't have its value parsed as wikitext.
				// Hopefully this won't cause problems for those
				// just wanting to call TRIM(). (In that case,
				// they can wrap the call in CONCAT().)
				$description->mType = 'String';
			} elseif ( in_array( $firstFunction, [ 'MAX', 'MIN', 'AVG' ] ) ) {
				// These are special functions in that the type
				// of their output is not fixed, but rather
				// matches the type of their input. So we find
				// what's inside the function call and call
				// *this* function recursively on that.
				$startParenPos = strpos( $origFieldName, '(' );
				$lastParenPos = strrpos( $origFieldName, ')' );
				$innerFieldName = substr( $origFieldName, $startParenPos + 1, ( $lastParenPos - $startParenPos - 1 ) );
				list( $innerDesc, $innerTableName ) = $this->getDescriptionAndTableNameForField( $innerFieldName );
				if ( $firstFunction == 'AVG' && $innerDesc->mType == 'Integer' ) {
					// In practice, handling of AVG() is here
					// so that calling it on a Rating
					// field will keep it as Rating.
					$description->mType = 'Float';
				} else {
					return [ $innerDesc, $innerTableName ];
				}
			}
			// If it's anything else ('CONCAT', 'SUBSTRING',
			// etc. etc.), we don't have to do anything.
		} else {
			// It's a standard field - though if it's '_value',
			// or ends in '__full', it's actually the type of its
			// corresponding field.
			$useListTable = ( $fieldName == '_value' );
			if ( $useListTable ) {
				if ( $tableName != null ) {
					list( $tableName, $fieldName ) = explode( '__', $tableName, 2 );
				} else {
					// We'll assume that there's exactly one
					// "field table" in the list of tables -
					// otherwise a standalone call to
					// "_value" will presumably crash the
					// SQL call.
					foreach ( $this->mAliasedTableNames as $curTable ) {
						if ( strpos( $curTable, '__' ) !== false ) {
							list( $tableName, $fieldName ) = explode( '__', $curTable );
							break;
						}
					}
				}
			} elseif ( strlen( $fieldName ) > 6 &&
				strpos( $fieldName, '__full', strlen( $fieldName ) - 6 ) !== false ) {
				$fieldName = substr( $fieldName, 0, strlen( $fieldName ) - 6 );
			}
			if ( $tableName != null && !$useListTable ) {
				if ( !array_key_exists( $tableName, $this->mAliasedTableNames ) ) {
					throw new MWException( wfMessage( "cargo-query-badalias", $tableName )->parse() );
				}
				$actualTableName = $this->mAliasedTableNames[$tableName];
				if ( !array_key_exists( $actualTableName, $this->mTableSchemas ) ) {
					throw new MWException( wfMessage( "cargo-query-unknowndbtable", $actualTableName )->parse() );
				} elseif ( !array_key_exists( $fieldName, $this->mTableSchemas[$actualTableName]->mFieldDescriptions ) ) {
					throw new MWException( wfMessage( "cargo-query-unknownfieldfortable", $fieldName, $actualTableName )->parse() );
				} else {
					$description = $this->mTableSchemas[$actualTableName]->mFieldDescriptions[$fieldName];
				}
			} elseif ( substr( $fieldName, -5 ) == '__lat' || substr( $fieldName, -5 ) == '__lon' ) {
				// Special handling for lat/lon helper fields.
				$description->mType = 'Coordinates part';
				$tableName = '';
			} elseif ( substr( $fieldName, -11 ) == '__precision' ) {
				// Special handling for lat/lon helper fields.
				// @TODO - we need validation on
				// __lat, __lon and __precision fields,
				// to make sure that they exist.
				$description->mType = 'Date precision';
				$tableName = '';
			} else {
				// Go through all the fields, until we find the
				// one matching this one.
				foreach ( $this->mTableSchemas as $curTableName => $tableSchema ) {
					if ( array_key_exists( $fieldName, $tableSchema->mFieldDescriptions ) ) {
						$description = $tableSchema->mFieldDescriptions[$fieldName];
						foreach ( $this->mAliasedTableNames as $tableAlias => $tableName1 ) {
							if ( $tableName1 == $curTableName ) {
								$tableName = $tableAlias;
								break;
							}
						}
						break;
					}
				}

				// If we couldn't find a table name, throw an error.
				if ( $tableName == '' ) {
					// There's a good chance that
					// $fieldName is blank too.
					if ( $fieldName == '' ) {
						$fieldName = $origFieldName;
					}
					throw new MWException( wfMessage( "cargo-query-unknownfield", $fieldName )->parse() );
				}
			}
		}

		return [ $description, $tableName ];
	}

	/**
	 * Attempts to get the "field description" (type, etc.), as well as
	 * the table name, of each field specified in a SELECT call (via a
	 * #cargo_query call), using the set of schemas for all data tables.
	 */
	public function setDescriptionsAndTableNamesForFields() {
		$this->mFieldDescriptions = [];
		$this->mFieldTables = [];
		foreach ( $this->mAliasedFieldNames as $alias => $origFieldName ) {
			list( $description, $tableName ) = $this->getDescriptionAndTableNameForField( $origFieldName );

			// Fix alias.
			$alias = trim( $alias );
			$this->mFieldDescriptions[$alias] = $description;
			$this->mFieldTables[$alias] = $tableName;
		}
	}

	public function addToCargoJoinConds( $newCargoJoinConds ) {
		foreach ( $newCargoJoinConds as $newCargoJoinCond ) {
			// Go through to make sure it's not there already.
			$foundMatch = false;
			foreach ( $this->mCargoJoinConds as $cargoJoinCond ) {
				if ( $cargoJoinCond['table1'] == $newCargoJoinCond['table1'] &&
					$cargoJoinCond['field1'] == $newCargoJoinCond['field1'] &&
					$cargoJoinCond['table2'] == $newCargoJoinCond['table2'] &&
					$cargoJoinCond['field2'] == $newCargoJoinCond['field2'] ) {
					$foundMatch = true;
					continue;
				}
			}
			if ( !$foundMatch ) {
				$this->mCargoJoinConds[] = $newCargoJoinCond;
			}
		}
	}

	public function addFieldTableToTableNames( $fieldTableName, $fieldTableAlias, $tableAlias ) {
		// Add it in in the correct place, if it should be added at all.
		if ( array_key_exists( $fieldTableAlias, $this->mAliasedTableNames ) ) {
			return;
		}
		if ( !array_key_exists( $tableAlias, $this->mAliasedTableNames ) ) {
			// Show an error message here?
			return;
		}

		// array_splice() for an associative array - copied from
		// http://stackoverflow.com/a/1783125
		$indexOfMainTable = array_search( $tableAlias, array_keys( $this->mAliasedTableNames ) );
		$offset = $indexOfMainTable + 1;
		$this->mAliasedTableNames = array_slice( $this->mAliasedTableNames, 0, $offset, true ) +
			[ $fieldTableAlias => $fieldTableName ] +
			array_slice( $this->mAliasedTableNames, $offset, null, true );
	}

	/**
	 * Helper function for handleVirtualFields() - for the query's
	 * "fields" and "order by" values, the right replacement for "virtual
	 * fields" depends on whether the separate table for that field has
	 * been included in the query.
	 */
	public function fieldTableIsIncluded( $fieldTableAlias ) {
		foreach ( $this->mCargoJoinConds as $cargoJoinCond ) {
			if ( $cargoJoinCond['table1'] == $fieldTableAlias ||
				$cargoJoinCond['table2'] == $fieldTableAlias ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Provides HOLDS functionality to WHERE clause by replacing $pattern
	 * in $subject with suitable subquery and setting $found to true if
	 * successful (leaves it untouched otehrwise). Includes modifying
	 * the regex beginning from a non-valid identifier character to word
	 * boundary.
	 */
	public function substVirtualFieldName( &$subject, $rootPattern, $tableAlias, $notOperation, $fieldTableName, $compareOperator, &$found ) {
		$notOperator = $notOperation ? 'NOT' : '';
		$patternMatch = [];
		// Match HOLDS syntax with values in single quotes
		if ( preg_match_all( $rootPattern . '\s*(\'.*?[^\\\\\']\')/i', $subject, $matches ) ) {
			$pattern = $rootPattern . '\s*(\'.*?[^\\\\\']\')/i';
			$patternMatch[$pattern] = $matches;
		}
		// Match HOLDS syntax with values in double quotes
		if ( preg_match_all( $rootPattern . '\s*(\".*?[^\\\"]\")/i', $subject, $matches ) ) {
			$pattern = $rootPattern . '\s*(\".*?[^\\\"]\")/i';
			$patternMatch[$pattern] = $matches;
		}
		// Match HOLDS syntax with fieldnames without quotes.
		// Fieldnames are expected to be single words without spaces.
		if ( preg_match_all( $rootPattern . '\s*([^\'"\s]+\s*)/i', $subject, $matches ) ) {
			$pattern = $rootPattern . '\s*([^\'"\s]*\s*)/i';
			$patternMatch[$pattern] = $matches;
		}
		// If any match is found, replace it with a subquery.
		if ( !empty( $patternMatch ) ) {
			foreach ( $patternMatch as $pattern => $matches ) {
				$pattern = str_replace( '([^\w$,]|^)', '\b', $pattern );
				$pattern = str_replace( '([^\w$.,]|^)', '\b', $pattern );
				foreach ( $matches[2] as $match ) {
					// _ID need not be quoted here.
					// This being attached with a table name is handled
					// in the function addTablePrefixesToAll, like other fields.
					$replacement =
						$tableAlias . "._ID " .
						$notOperator .
						" IN (SELECT " . $this->mCargoDB->addIdentifierQuotes( "_rowID" ) . " FROM " .
						$this->mCargoDB->tableName( $fieldTableName ) .
						" WHERE " . $this->mCargoDB->addIdentifierQuotes( "_value" ) .
						$compareOperator .
						$match .
						") ";
					$subject = preg_replace( $pattern, $replacement, $subject, $limit = 1 );
				}
			}
			$found = true;
		}
	}

	private function handleVirtualFields() {
		// The array-field alias can be found in a number of different
		// clauses. Handling depends on which clause it is:
		// "where" - make sure that "HOLDS" or "HOLDS LIKE" is
		// specified. If it is, "translate" it into required subquery.
		// "join on" - make sure that "HOLDS" is specified, If it is,
		// "translate" it, and add the values table to "tables".
		// "group by" - always "translate" it into the single value.
		// "having" - same as "group by".
		// "fields" - "translate" it, where the translation (i.e.
		// the true field) depends on whether or not the values
		// table is included.
		// "order by" - same as "fields".

		// First, create an array of the virtual fields in the current
		// set of tables.
		$virtualFields = [];
		foreach ( $this->mTableSchemas as $tableName => $tableSchema ) {
			foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
				if ( !$fieldDescription->mIsList ) {
					continue;
				}
				foreach ( $this->mAliasedTableNames as $tableAlias => $tableName2 ) {
					if ( $tableName == $tableName2 ) {
						$virtualFields[] = [
							'fieldName' => $fieldName,
							'tableAlias' => $tableAlias,
							'tableName' => $tableName,
							'fieldType' => $fieldDescription->mType,
							'isHierarchy' => $fieldDescription->mIsHierarchy
						];
					}
				}
			}
		}

		// "where"
		$matches = [];
		$numHoldsExpressions = 0;
		foreach ( $virtualFields as $virtualField ) {
			$fieldName = $virtualField['fieldName'];
			$tableAlias = $virtualField['tableAlias'];
			$tableName = $virtualField['tableName'];
			$fieldType = $virtualField['fieldType'];
			$isHierarchy = $virtualField['isHierarchy'];

			$fieldTableName = $tableName . '__' . $fieldName;
			$fieldTableAlias = $tableAlias . '__' . $fieldName;
			$fieldReplaced = false;
			$throwException = false;

			$patternSimple = [
				CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName ),
				CargoUtils::getSQLFieldPattern( $fieldName )
				];
			$patternRoot = [
				CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName, false ) . '\s+',
				CargoUtils::getSQLFieldPattern( $fieldName, false ) . '\s+'
				];

			for ( $i = 0; $i < 2; $i++ ) {
				if ( preg_match( $patternSimple[$i], $this->mWhereStr ) ) {

					$this->substVirtualFieldName(
						$this->mWhereStr,
						$patternRoot[$i] . 'HOLDS\s+NOT\s+LIKE',
						$tableAlias,
						$notOperation = true,
						$fieldTableName,
						$compareOperation = "LIKE ",
						$fieldReplaced
					);

					$this->substVirtualFieldName(
						$this->mWhereStr,
						$patternRoot[$i] . 'HOLDS\s+LIKE',
						$tableAlias,
						$notOperation = false,
						$fieldTableName,
						$compareOperation = "LIKE ",
						$fieldReplaced
					);

					$this->substVirtualFieldName(
						$this->mWhereStr,
						$patternRoot[$i] . 'HOLDS\s+NOT',
						$tableAlias,
						$notOperation = true,
						$fieldTableName,
						$compareOperation = "= ",
						$fieldReplaced
					);

					$this->substVirtualFieldName(
						$this->mWhereStr,
						$patternRoot[$i] . 'HOLDS',
						$tableAlias,
						$notOperation = false,
						$fieldTableName,
						$compareOperation = "= ",
						$fieldReplaced
					);

					if ( preg_match( $patternSimple[$i], $this->mWhereStr ) ) {
						if ( $isHierarchy ) {
							throw new MWException( "Error: operator for the hierarchy field '" .
								"$tableName.$fieldName' must be 'HOLDS', 'HOLDS NOT', '" .
								"HOLDS WITHIN', 'HOLDS LIKE' or 'HOLDS NOT LIKE'." );
						} else {
							throw new MWException( "Error: operator for the virtual field '" .
								"$tableName.$fieldName' must be 'HOLDS', 'HOLDS NOT', '" .
								"HOLDS LIKE' or 'HOLDS NOT LIKE'." );
						}
					}
				}
			}
			// Always use the "field table" if it's a date field,
			// and it's being queried.
			$isFieldInQuery = in_array( $fieldName, $this->mAliasedFieldNames ) ||
				in_array( "$tableAlias.$fieldName", $this->mAliasedFieldNames );
			if ( $isFieldInQuery && ( $fieldType == 'Date' || $fieldType == 'Datetime' ) ) {
				$fieldReplaced = true;
			}
		}
		// "join on"
		$newCargoJoinConds = [];
		foreach ( $this->mCargoJoinConds as $i => $joinCond ) {
			// We only handle 'HOLDS' here - no joining on
			// 'HOLDS LIKE'.
			if ( !array_key_exists( 'joinOperator', $joinCond ) || $joinCond['joinOperator'] != ' HOLDS ' ) {
				continue;
			}

			foreach ( $virtualFields as $virtualField ) {
				$fieldName = $virtualField['fieldName'];
				$tableAlias = $virtualField['tableAlias'];
				$tableName = $virtualField['tableName'];
				if ( $fieldName != $joinCond['field1'] || $tableAlias != $joinCond['table1'] ) {
					continue;
				}
				$fieldTableName = $tableName . '__' . $fieldName;
				$fieldTableAlias = $tableAlias . '__' . $fieldName;
				$this->addFieldTableToTableNames( $fieldTableName, $fieldTableAlias, $tableAlias );
				$newJoinCond = [
					'joinType' => 'LEFT OUTER JOIN',
					'table1' => $tableAlias,
					'field1' => '_ID',
					'table2' => $fieldTableAlias,
					'field2' => '_rowID'
				];
				$newCargoJoinConds[] = $newJoinCond;
				$newJoinCond2 = [
					'joinType' => 'RIGHT OUTER JOIN',
					'table1' => $fieldTableAlias,
					'field1' => '_value',
					'table2' => $this->mCargoJoinConds[$i]['table2'],
					'field2' => $this->mCargoJoinConds[$i]['field2']
				];
				$newCargoJoinConds[] = $newJoinCond2;
				// Is it safe to unset an array value while
				// cycling through the array? Hopefully.
				unset( $this->mCargoJoinConds[$i] );
			}
		}
		$this->addToCargoJoinConds( $newCargoJoinConds );

		// "group by" and "having"
		// We handle these before "fields" and "order by" because,
		// unlike those two, a virtual field here can affect the
		// set of tables and fields being included - which will
		// affect the other two.
		$matches = [];
		foreach ( $virtualFields as $virtualField ) {
			$fieldName = $virtualField['fieldName'];
			$tableAlias = $virtualField['tableAlias'];
			$tableName = $virtualField['tableName'];
			$pattern1 = CargoUtils::getSQLTableAndFieldPattern( $tableName, $fieldName );
			$foundMatch1 = preg_match( $pattern1, $this->mGroupByStr, $matches );
			$pattern2 = CargoUtils::getSQLFieldPattern( $fieldName );
			$foundMatch2 = false;

			if ( !$foundMatch1 ) {
				$foundMatch2 = preg_match( $pattern2, $this->mGroupByStr, $matches );
			}
			if ( $foundMatch1 || $foundMatch2 ) {
				$fieldTableName = $tableName . '__' . $fieldName;
				$fieldTableAlias = $tableAlias . '__' . $fieldName;
				if ( !$this->fieldTableIsIncluded( $fieldTableAlias ) ) {
					$this->addFieldTableToTableNames( $fieldTableName, $fieldTableAlias, $tableAlias );
					$this->mCargoJoinConds[] = [
						'joinType' => 'LEFT OUTER JOIN',
						'table1' => $tableAlias,
						'field1' => '_ID',
						'table2' => $fieldTableAlias,
						'field2' => '_rowID'
					];
				}
				$replacement = "$fieldTableAlias._value";

				if ( $foundMatch1 ) {
					$this->mGroupByStr = preg_replace( $pattern1, $replacement, $this->mGroupByStr );
					$this->mHavingStr = preg_replace( $pattern1, $replacement, $this->mHavingStr );
				} elseif ( $foundMatch2 ) {
					$this->mGroupByStr = preg_replace( $pattern2, $replacement, $this->mGroupByStr );
					$this->mHavingStr = preg_replace( $pattern2, $replacement, $this->mHavingStr );
				}
			}
		}

		// "fields"
		foreach ( $this->mAliasedFieldNames as $alias => $fieldName ) {
			$fieldDescription = $this->mFieldDescriptions[$alias];

			if ( strpos( $fieldName, '.' ) !== false ) {
				// This could probably be done better with
				// regexps.
				list( $tableAlias, $fieldName ) = explode( '.', $fieldName, 2 );
			} else {
				$tableAlias = $this->mFieldTables[$alias];
			}

			// We're only interested in virtual list fields.
			$isVirtualField = false;
			foreach ( $virtualFields as $virtualField ) {
				if ( $fieldName == $virtualField['fieldName'] && $tableAlias == $virtualField['tableAlias'] ) {
					$isVirtualField = true;
					break;
				}
			}
			if ( !$isVirtualField ) {
				continue;
			}

			// Since the field name is an alias, it should get
			// translated, to either the "full" equivalent or to
			// the "value" field in the field table - depending on
			// whether or not that field has been "joined" on.
			$fieldTableAlias = $tableAlias . '__' . $fieldName;
			if ( $this->fieldTableIsIncluded( $fieldTableAlias ) ) {
				$fieldName = $fieldTableAlias . '._value';
			} else {
				$fieldName .= '__full';
			}
			$this->mAliasedFieldNames[$alias] = $fieldName;
		}

		// "order by"
		$matches = [];
		foreach ( $virtualFields as $virtualField ) {
			$fieldName = $virtualField['fieldName'];
			$tableAlias = $virtualField['tableAlias'];
			$tableName = $virtualField['tableName'];
			$pattern1 = CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName );
			$pattern2 = CargoUtils::getSQLFieldPattern( $fieldName );
			$foundMatch1 = $foundMatch2 = false;
			foreach ( $this->mOrderBy as &$orderByElem ) {
				$foundMatch1 = preg_match( $pattern1, $orderByElem, $matches );

				if ( !$foundMatch1 ) {
					$foundMatch2 = preg_match( $pattern2, $orderByElem, $matches );
				}
				if ( !$foundMatch1 && !$foundMatch2 ) {
					continue;
				}
				$fieldTableAlias = $tableAlias . '__' . $fieldName;
				if ( $this->fieldTableIsIncluded( $fieldTableAlias ) ) {
					$replacement = "$fieldTableAlias._value";
				} else {
					$replacement = $tableAlias . '.' . $fieldName . '__full ';
				}
				if ( isset( $matches[2] ) && ( $matches[2] == ',' ) ) {
					$replacement .= ',';
				}
				if ( $foundMatch1 ) {
					$orderByElem = preg_replace( $pattern1, $replacement, $orderByElem );
				} else { // $foundMatch2
					$orderByElem = preg_replace( $pattern2, $replacement, $orderByElem );
				}
			}
		}
	}

	/**
	 * Similar to handleVirtualFields(), but handles coordinates fields
	 * instead of fields that hold lists. This handling is much simpler.
	 */
	private function handleVirtualCoordinateFields() {
		// Coordinate fields can be found in the "fields" and "where"
		// clauses. The following handling is done:
		// "fields" - "translate" it, where the translation (i.e.
		// the true field) depends on whether or not the values
		// table is included.
		// "where" - make sure that "NEAR" is specified. If it is,
		// translate the clause accordingly.

		// First, create an array of the coordinate fields in the
		// current set of tables.
		$coordinateFields = [];
		foreach ( $this->mTableSchemas as $tableName => $tableSchema ) {
			foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
				if ( $fieldDescription->mType == 'Coordinates' ) {
					foreach ( $this->mAliasedTableNames as $tableAlias => $tableName2 ) {
						if ( $tableName == $tableName2 ) {
							$coordinateFields[] = [
								'fieldName' => $fieldName,
								'tableName' => $tableName,
								'tableAlias' => $tableAlias,
							];
							break;
						}
					}
				}
			}
		}

		// "fields"
		foreach ( $this->mAliasedFieldNames as $alias => $fieldName ) {
			$fieldDescription = $this->mFieldDescriptions[$alias];

			if ( strpos( $fieldName, '.' ) !== false ) {
				// This could probably be done better with
				// regexps.
				list( $tableAlias, $fieldName ) = explode( '.', $fieldName, 2 );
			} else {
				$tableAlias = $this->mFieldTables[$alias];
			}

			// We have to do this roundabout checking, instead
			// of just looking at the type of each field alias,
			// because we want to find only the *virtual*
			// coordinate fields.
			$isCoordinateField = false;
			foreach ( $coordinateFields as $coordinateField ) {
				if ( $fieldName == $coordinateField['fieldName'] &&
					$tableAlias == $coordinateField['tableAlias'] ) {
					$isCoordinateField = true;
					break;
				}
			}
			if ( !$isCoordinateField ) {
				continue;
			}

			// Since the field name is an alias, it should get
			// translated to its "full" equivalent.
			$fullFieldName = $fieldName . '__full';
			$this->mAliasedFieldNames[$alias] = $fullFieldName;

			// Add in the 'lat' and 'lon' fields as well - we'll
			// need them, if a map is being displayed.
			$this->mAliasedFieldNames[$fieldName . '  lat'] = $fieldName . '__lat';
			$this->mAliasedFieldNames[$fieldName . '  lon'] = $fieldName . '__lon';
		}

		// "where"
		// @TODO - add handling for "HOLDS POINT NEAR"
		$matches = [];
		foreach ( $coordinateFields as $coordinateField ) {
			$fieldName = $coordinateField['fieldName'];
			$tableAlias = $coordinateField['tableAlias'];
			$patternSuffix = '(\s+NEAR\s*)\(([^)]*)\)/i';

			$pattern1 = CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName, false ) . $patternSuffix;
			$foundMatch1 = preg_match( $pattern1, $this->mWhereStr, $matches );
			if ( !$foundMatch1 ) {
				$pattern2 = CargoUtils::getSQLFieldPattern( $fieldName, false ) . $patternSuffix;
				$foundMatch2 = preg_match( $pattern2, $this->mWhereStr, $matches );
			}
			if ( $foundMatch1 || $foundMatch2 ) {
				// If no "NEAR", throw an error.
				if ( count( $matches ) != 4 ) {
					throw new MWException( "Error: operator for the virtual coordinates field "
					. "'$tableAlias.$fieldName' must be 'NEAR'." );
				}
				$coordinatesAndDistance = explode( ',', $matches[3] );
				if ( count( $coordinatesAndDistance ) != 3 ) {
					throw new MWException( "Error: value for the 'NEAR' operator must be of the form "
					. "\"(latitude, longitude, distance)\"." );
				}
				list( $latitude, $longitude, $distance ) = $coordinatesAndDistance;
				$distanceComponents = explode( ' ', trim( $distance ) );
				if ( count( $distanceComponents ) != 2 ) {
					throw new MWException( "Error: the third argument for the 'NEAR' operator, "
					. "representing the distance, must be of the form \"number unit\"." );
				}
				list( $distanceNumber, $distanceUnit ) = $distanceComponents;
				$distanceNumber = trim( $distanceNumber );
				$distanceUnit = trim( $distanceUnit );
				list( $latDistance, $longDistance ) = self::distanceToDegrees( $distanceNumber, $distanceUnit,
						$latitude );
				// There are much better ways to do this, but
				// for now, just make a "bounding box" instead
				// of a bounding circle.
				$newWhere = " $tableAlias.{$fieldName}__lat >= " . max( $latitude - $latDistance, -90 ) .
					" AND $tableAlias.{$fieldName}__lat <= " . min( $latitude + $latDistance, 90 ) .
					" AND $tableAlias.{$fieldName}__lon >= " . max( $longitude - $longDistance, -180 ) .
					" AND $tableAlias.{$fieldName}__lon <= " . min( $longitude + $longDistance, 180 ) . ' ';

				if ( $foundMatch1 ) {
					$this->mWhereStr = preg_replace( $pattern1, $newWhere, $this->mWhereStr );
				} elseif ( $foundMatch2 ) {
					$this->mWhereStr = preg_replace( $pattern2, $newWhere, $this->mWhereStr );
				}
			}
		}

		// "order by"
		// This one is simpler than the others - just add a "__full"
		// to each coordinates field in the "order by" clause.
		$matches = [];
		foreach ( $coordinateFields as $coordinateField ) {
			$fieldName = $coordinateField['fieldName'];
			$tableAlias = $coordinateField['tableAlias'];

			$pattern1 = CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName, true );
			$pattern2 = CargoUtils::getSQLFieldPattern( $fieldName, true );
			foreach ( $this->mOrderBy as &$orderByElem ) {
				$orderByElem = preg_replace( $pattern1, '$1' . "$tableAlias.$fieldName" . '__full$2', $orderByElem );
				$orderByElem = preg_replace( $pattern2, '$1' . $fieldName . '__full$2', $orderByElem );
			}
		}
	}

	/**
	 * Handles Hierarchy fields' "WHERE" operations
	 */
	private function handleHierarchyFields() {
		// "where" - make sure that if
		// "WITHIN" (if not list) or "HOLDS WITHIN" (if list)
		// is specified, then translate the clause accordingly.
		// other translations in case of List fields,
		// are handled by handleVirtualFields().

		// First, create an array of the hierarchy fields in the
		// current set of tables.
		$hierarchyFields = [];
		foreach ( $this->mTableSchemas as $tableName => $tableSchema ) {
			foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
				if ( !$fieldDescription->mIsHierarchy ) {
					continue;
				}
				foreach ( $this->mAliasedTableNames as $tableAlias => $tableName2 ) {
					if ( $tableName == $tableName2 ) {
						$hierarchyFields[] = [
							'fieldName' => $fieldName,
							'tableAlias' => $tableAlias,
							'tableName' => $tableName,
							'isList' => $fieldDescription->mIsList
						];
					}
				}
			}
		}

		// "where"
		foreach ( $hierarchyFields as $hierarchyField ) {
			$fieldName = $hierarchyField['fieldName'];
			$tableName = $hierarchyField['tableName'];
			$tableAlias = $hierarchyField['tableAlias'];
			$fieldIsList = $hierarchyField['isList'];

			$patternSimple = [
				CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName ),
				CargoUtils::getSQLFieldPattern( $fieldName )
				];
			$patternRootArray = [
				CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName, false ),
				CargoUtils::getSQLFieldPattern( $fieldName, false )
				];

			$simpleMatch = false;
			$completeMatch = false;
			$patternRoot = "";

			if ( preg_match( $patternSimple[0], $this->mWhereStr ) ) {
				$simpleMatch = true;
				$patternRoot = $patternRootArray[0];
			} elseif ( preg_match( $patternSimple[1], $this->mWhereStr ) ) {
				$simpleMatch = true;
				$patternRoot = $patternRootArray[1];
			}
			// else we don't have current field in WHERE clause

			if ( !$simpleMatch ) {
				continue;
			}
			$patternSuffix = '([\'"]?[^\'"]*[\'"]?)/i';  // To capture string in quotes or a number
			$hierarchyTable = $this->mCargoDB->tableName( $tableName . '__' . $fieldName . '__hierarchy' );
			$fieldTableName = $this->mCargoDB->tableName( $tableName . '__' . $fieldName );
			$completeSearchPattern = "";
			$matches = [];
			$newWhere = "";
			$leftFieldName = $this->mCargoDB->addIdentifierQuotes( "_left" );
			$rightFieldName = $this->mCargoDB->addIdentifierQuotes( "_right" );
			$valueFieldName = $this->mCargoDB->addIdentifierQuotes( "_value" );

			if ( preg_match( $patternRoot . '(\s+HOLDS WITHIN\s+)' . $patternSuffix, $this->mWhereStr, $matches ) ) {
				if ( !$fieldIsList ) {
					throw new MWException( "Error: \"HOLDS WITHIN\" cannot be used for single hierarchy field, use \"WITHIN\" instead." );
				}
				$completeMatch = true;
				$completeSearchPattern = $patternRoot . '(\s+HOLDS WITHIN\s+)' . $patternSuffix;
				if ( count( $matches ) != 4 || $matches[3] === "" ) {
					throw new MWException( "Error: Please specify a value for \"HOLDS WITHIN\"" );
				}
				$withinValue = $matches[3];
				$subquery = "( SELECT $valueFieldName FROM $hierarchyTable WHERE " .
					"$leftFieldName >= ( SELECT $leftFieldName FROM $hierarchyTable WHERE $valueFieldName = $withinValue ) AND " .
					"$rightFieldName <= ( SELECT $rightFieldName FROM $hierarchyTable WHERE $valueFieldName = $withinValue ) " .
					")";
				$subquery = "( SELECT DISTINCT( " . $this->mCargoDB->addIdentifierQuotes( "_rowID" ) . " ) FROM $fieldTableName WHERE $valueFieldName IN " . $subquery . " )";
				$newWhere = " " . $tableName . "._ID" . " IN " . $subquery;
			}

			if ( preg_match( $patternRoot . '(\s+WITHIN\s+)' . $patternSuffix, $this->mWhereStr, $matches ) ) {
				if ( $fieldIsList ) {
					throw new MWException( "Error: \"WITHIN\" cannot be used for list hierarchy field, use \"HOLDS WITHIN\" instead." );
				}
				$completeMatch = true;
				$completeSearchPattern = $patternRoot . '(\s+WITHIN\s+)' . $patternSuffix;
				if ( count( $matches ) != 4 || $matches[3] === "" ) {
					throw new MWException( "Error: Please specify a value for \"WITHIN\"" );
				}
				$withinValue = $matches[3];
				$subquery = "( SELECT $valueFieldName FROM $hierarchyTable WHERE " .
					"$leftFieldName >= ( SELECT $leftFieldName FROM $hierarchyTable WHERE $valueFieldName = $withinValue ) AND " .
					"$rightFieldName <= ( SELECT $rightFieldName FROM $hierarchyTable WHERE $valueFieldName = $withinValue ) " .
					")";
				$newWhere = " " . $fieldName . " IN " . $subquery;
			}

			if ( $completeMatch ) {
				$this->mWhereStr = preg_replace( $completeSearchPattern, $newWhere, $this->mWhereStr );
			}

			// In case fieldIsList === true, there is a possibility of more Query commands.
			// like "HOLDS" and "HOLDS LIKE", that is handled by handleVirtualFields()
		}
	}

	/**
	 * Returns the number of degrees of both latitude and longitude that
	 * correspond to the passed-in distance (in either kilometers or
	 * miles), based on the passed-in latitude. (Longitude doesn't matter
	 * when doing this conversion, but latitude does.)
	 */
	private static function distanceToDegrees( $distanceNumber, $distanceUnit, $latString ) {
		if ( in_array( $distanceUnit, [ 'kilometers', 'kilometres', 'km' ] ) ) {
			$distanceInKM = $distanceNumber;
		} elseif ( in_array( $distanceUnit, [ 'miles', 'mi' ] ) ) {
			$distanceInKM = $distanceNumber * 1.60934;
		} else {
			throw new MWException( "Error: distance for 'NEAR' operator must be in either miles or "
			. "kilometers (\"$distanceUnit\" specified)." );
		}
		// The calculation of distance to degrees latitude is
		// essentially the same wherever you are on the globe, although
		// the longitude calculation is more complicated.
		$latDistance = $distanceInKM / 111;

		// Convert the latitude string to a latitude number - code is
		// copied from CargoUtils::parseCoordinatesString().
		$latIsNegative = false;
		if ( strpos( $latString, 'S' ) > 0 ) {
			$latIsNegative = true;
		}
		$latString = str_replace( [ 'N', 'S' ], '', $latString );
		if ( is_numeric( $latString ) ) {
			$latNum = floatval( $latString );
		} else {
			$latNum = CargoUtils::coordinatePartToNumber( $latString );
		}
		if ( $latIsNegative ) {
			$latNum *= -1;
		}

		$lengthOfOneDegreeLongitude = cos( deg2rad( $latNum ) ) * 111.321;
		$longDistance = $distanceInKM / $lengthOfOneDegreeLongitude;

		return [ $latDistance, $longDistance ];
	}

	/**
	 * For each date field, also add its corresponding "precisicon"
	 * field (which indicates whether the date is year-only, etc.) to
	 * the query.
	 */
	public function handleDateFields() {
		$dateFields = [];
		foreach ( $this->mOrigAliasedFieldNames as $alias => $fieldName ) {
			if ( !array_key_exists( $alias, $this->mFieldDescriptions ) ) {
				continue;
			}
			$fieldDescription = $this->mFieldDescriptions[$alias];
			if ( ( $fieldDescription->mType == 'Date' || $fieldDescription->mType == 'Datetime' ||
				   $fieldDescription->mType == 'Start date' || $fieldDescription->mType == 'Start datetime' ||
				   $fieldDescription->mType == 'End date' || $fieldDescription->mType == 'End datetime' ) &&
				// Make sure this is an actual field and not a call
				// to a function, like DATE_FORMAT(), by checking for
				// the presence of '(' and ')' - there's probably a
				// more elegant way to do this.
				( strpos( $fieldName, '(' ) == false ) && ( strpos( $fieldName, ')' ) == false ) ) {
				$dateFields[$alias] = $fieldName;
			}
		}
		foreach ( $dateFields as $alias => $dateField ) {
			// Handle fields that are a list of dates.
			if ( substr( $dateField, -6 ) == '__full' ) {
				$dateField = substr( $dateField, 0, -6 );
			}
			$precisionFieldName = $dateField . '__precision';
			$precisionFieldAlias = $alias . '__precision';
			$this->mAliasedFieldNames[$precisionFieldAlias] = $precisionFieldName;
		}
	}

	private function handleSearchTextFields() {
		$searchTextFields = [];
		foreach ( $this->mTableSchemas as $tableName => $tableSchema ) {
			foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
				if ( $fieldDescription->mType != 'Searchtext' ) {
					continue;
				}
				$fieldAlias = array_search( $fieldName, $this->mAliasedFieldNames );
				if ( $fieldAlias === false ) {
					$tableAlias = array_search( $tableName, $this->mAliasedTableNames );
					$fieldAlias = array_search( "$tableAlias.$fieldName", $this->mAliasedFieldNames );
				}
				if ( $fieldAlias === false ) {
					$fieldAlias = $fieldName;
				}
				$searchTextFields[] = [
					'fieldName' => $fieldName,
					'fieldAlias' => $fieldAlias,
					'tableName' => $tableName
				];
			}
		}

		$matches = [];
		foreach ( $searchTextFields as $searchTextField ) {
			$fieldName = $searchTextField['fieldName'];
			$fieldAlias = $searchTextField['fieldAlias'];
			$tableName = $searchTextField['tableName'];
			$tableAlias = array_search( $tableName, $this->mAliasedTableNames );
			$patternSuffix = '(\s+MATCHES\s*)([\'"][^\'"]*[\'"])/i';
			$patternSuffix1 = '(\s+MATCHES\s*)(\'[^\']*\')/i';
			$patternSuffix2 = '(\s+MATCHES\s*)("[^"]*")/i';

			$patterns = [
				CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName, false ) . $patternSuffix1,
				CargoUtils::getSQLFieldPattern( $fieldName, false ) . $patternSuffix1,
				CargoUtils::getSQLTableAndFieldPattern( $tableAlias, $fieldName, false ) . $patternSuffix2,
				CargoUtils::getSQLFieldPattern( $fieldName, false ) . $patternSuffix2
			];
			$matchingPattern = null;
			foreach ( $patterns as $i => $pattern ) {
				$foundMatch = preg_match( $pattern, $this->mWhereStr, $matches );
				if ( $foundMatch ) {
					$matchingPattern = $i;
					break;
				}
			}

			if ( $foundMatch ) {
				$searchString = $matches[3];
				$newWhere = " MATCH($tableAlias.$fieldName) AGAINST ($searchString IN BOOLEAN MODE) ";

				$pattern = $patterns[$matchingPattern];
				$this->mWhereStr = preg_replace( $pattern, $newWhere, $this->mWhereStr );
				$searchEngine = new CargoSearchMySQL();
				$searchTerms = $searchEngine->getSearchTerms( $searchString );
				// @TODO - does $tableName need to be in there?
				$this->mSearchTerms[$fieldAlias] = $searchTerms;
			}
		}
	}

	/**
	 * Adds the "cargo" table prefix for every element in the SQL query
	 * except for 'tables' and 'join on' - for 'tables', the prefix is
	 * prepended automatically by the MediaWiki query, while for
	 * 'join on' the prefixes are added when the object is created.
	 */
	private function addTablePrefixesToAll() {
		foreach ( $this->mAliasedFieldNames as $alias => $fieldName ) {
			$this->mAliasedFieldNames[$alias] = $this->addTablePrefixes( $fieldName );
		}
		if ( $this->mWhereStr !== null ) {
			$this->mWhereStr = $this->addTablePrefixes( $this->mWhereStr );
		}
		$this->mGroupByStr = $this->addTablePrefixes( $this->mGroupByStr );
		$this->mHavingStr = $this->addTablePrefixes( $this->mHavingStr );
		foreach ( $this->mOrderBy as &$orderByElem ) {
			$orderByElem = $this->addTablePrefixes( $orderByElem );
		}
	}

	/**
	 * Calls a database SELECT query given the parts of the query; first
	 * appending the Cargo prefix onto table names where necessary.
	 */
	public function run() {
		foreach ( $this->mAliasedTableNames as $tableName ) {
			if ( !$this->mCargoDB->tableExists( $tableName ) ) {
				throw new MWException( "Error: No database table exists named \"$tableName\"." );
			}
		}

		$selectOptions = [];

		if ( $this->mGroupByStr != '' ) {
			$selectOptions['GROUP BY'] = $this->mGroupByStr;
		}
		if ( $this->mHavingStr != '' ) {
			$selectOptions['HAVING'] = $this->mHavingStr;
		}

		$selectOptions['ORDER BY'] = $this->mOrderBy;
		$selectOptions['LIMIT'] = $this->mQueryLimit;
		$selectOptions['OFFSET'] = $this->mOffset;

		// Aliases need to be surrounded by quotes when we actually
		// call the DB query.
		$realAliasedFieldNames = [];
		foreach ( $this->mAliasedFieldNames as $alias => $fieldName ) {
			// If it's either a field, or a table + field,
			// add quotes around the name(s).
			if ( strpos( $fieldName, '(' ) === false ) {
				if ( strpos( $fieldName, '.' ) === false ) {
					if ( !$this->mCargoDB->isQuotedIdentifier( $fieldName ) && !CargoUtils::isSQLStringLiteral( $fieldName ) ) {
						$fieldName = $this->mCargoDB->addIdentifierQuotes( $fieldName );
					}
				} else {
					list( $realTableName, $realFieldName ) = explode( '.', $fieldName, 2 );
					if ( !$this->mCargoDB->isQuotedIdentifier( $realTableName ) && !CargoUtils::isSQLStringLiteral( $realTableName ) ) {
						$realTableName = $this->mCargoDB->addIdentifierQuotes( $realTableName );
					}
					if ( !$this->mCargoDB->isQuotedIdentifier( $realFieldName ) && !CargoUtils::isSQLStringLiteral( $realFieldName ) ) {
						$realFieldName = $this->mCargoDB->addIdentifierQuotes( $realFieldName );
					}
					$fieldName = "$realTableName.$realFieldName";
				}
			}
			$realAliasedFieldNames[$alias] = $fieldName;
		}

		$res = $this->mCargoDB->select( $this->mAliasedTableNames, $realAliasedFieldNames, $this->mWhereStr, __METHOD__,
			$selectOptions, $this->mJoinConds );

		// Is there a more straightforward way of turning query
		// results into an array?
		$resultArray = [];
		foreach ( $res as $row ) {
			$resultsRow = [];
			foreach ( $this->mAliasedFieldNames as $alias => $fieldName ) {
				if ( !isset( $row->$alias ) ) {
					continue;
				}

				$curValue = $row->$alias;
				if ( $curValue instanceof DateTime ) {
					// MSSQL dates only?
					$resultsRow[$alias] = $curValue->format( DateTime::W3C );
				} else {
					// It's a string.
					// Escape any HTML, to avoid JavaScript
					// injections and the like.
					$resultsRow[$alias] = htmlspecialchars( $curValue );
				}
			}
			$resultArray[] = $resultsRow;
		}

		return $resultArray;
	}

	private function addTablePrefixes( $string ) {
		// Create arrays for doing replacements of table names within
		// the SQL by their "real" equivalents.
		$tableNamePatterns = [];
		foreach ( $this->mAliasedTableNames as $alias => $tableName ) {
			$tableNamePatterns[] = CargoUtils::getSQLTablePattern( $tableName );
			$tableNamePatterns[] = CargoUtils::getSQLTablePattern( $alias );
		}

		return preg_replace_callback( $tableNamePatterns,
			[ $this, 'addQuotes' ], $string );
	}

	private function addQuotes( $matches ) {
		$beforeText = $matches[1];
		$tableName = $matches[2];
		$fieldName = $matches[3];
		$isTableAlias = false;
		if ( array_key_exists( $tableName, $this->mAliasedTableNames ) ) {
			if ( !in_array( $tableName, $this->mAliasedTableNames ) ) {
				$isTableAlias = true;
			}
		}
		if ( $isTableAlias ) {
			return $beforeText . $this->mCargoDB->addIdentifierQuotes( $tableName ) . "." .
				   $this->mCargoDB->addIdentifierQuotes( $fieldName );
		} else {
			return $beforeText . $this->mCargoDB->tableName( $tableName ) . "." .
				   $this->mCargoDB->addIdentifierQuotes( $fieldName );
		}
	}

}
