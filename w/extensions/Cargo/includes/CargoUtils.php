<?php
/**
 * Utility functions for the Cargo extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;

class CargoUtils {

	private static $CargoDB = null;

	/**
	 * @global string $wgDBuser
	 * @global string $wgDBpassword
	 * @global string $wgCargoDBserver
	 * @global string $wgCargoDBname
	 * @global string $wgCargoDBuser
	 * @global string $wgCargoDBpasswordd
	 * @global string $wgCargoDBprefix
	 * @global string $wgCargoDBtype
	 * @return Database or DatabaseBase
	 */
	public static function getDB() {
		if ( self::$CargoDB != null && self::$CargoDB->isOpen() ) {
			return self::$CargoDB;
		}

		global $wgDBuser, $wgDBpassword, $wgDBprefix, $wgDBservers;
		global $wgCargoDBserver, $wgCargoDBname, $wgCargoDBuser, $wgCargoDBpassword, $wgCargoDBprefix, $wgCargoDBtype;

		$dbr = wfGetDB( DB_REPLICA );
		$server = $dbr->getServer();
		$name = $dbr->getDBname();
		$type = $dbr->getType();

		// We need $wgCargoDBtype for other functions.
		if ( $wgCargoDBtype === null ) {
			$wgCargoDBtype = $type;
		}
		$dbServer = $wgCargoDBserver === null ? $server : $wgCargoDBserver;
		$dbName = $wgCargoDBname === null ? $name : $wgCargoDBname;

		// Server (host), db name, and db type can be retrieved from $dbr via
		// public methods, but username and password cannot. If these values are
		// not set for Cargo, get them from either $wgDBservers or wgDBuser and
		// $wgDBpassword, depending on whether or not there are multiple DB servers.
		if ( $wgCargoDBuser !== null ) {
			$dbUsername = $wgCargoDBuser;
		} elseif ( is_array( $wgDBservers ) && isset( $wgDBservers[0] ) ) {
			$dbUsername = $wgDBservers[0]['user'];
		} else {
			$dbUsername = $wgDBuser;
		}
		if ( $wgCargoDBpassword !== null ) {
			$dbPassword = $wgCargoDBpassword;
		} elseif ( is_array( $wgDBservers ) && isset( $wgDBservers[0] ) ) {
			$dbPassword = $wgDBservers[0]['password'];
		} else {
			$dbPassword = $wgDBpassword;
		}

		if ( $wgCargoDBprefix !== null ) {
			$dbTablePrefix = $wgCargoDBprefix;
		} else {
			$dbTablePrefix = $wgDBprefix . 'cargo__';
		}

		$params = [
			'host' => $dbServer,
			'user' => $dbUsername,
			'password' => $dbPassword,
			'dbname' => $dbName,
			'tablePrefix' => $dbTablePrefix,
		];

		if ( $type === 'sqlite' ) {
			$params['dbFilePath'] = $dbr->getDbFilePath();
		} elseif ( $type === 'postgres' ) {
			global $wgDBport;
			// @TODO - a $wgCargoDBport variable is still needed.
			$params['port'] = $wgDBport;
		}

		self::$CargoDB = Database::factory( $wgCargoDBtype, $params );
		return self::$CargoDB;
	}

	/**
	 * Gets a page property for the specified page ID and property name.
	 */
	public static function getPageProp( $pageID, $pageProp ) {
		$dbr = wfGetDB( DB_REPLICA );
		$value = $dbr->selectField( 'page_props', [
				'pp_value'
			], [
				'pp_page' => $pageID,
					'pp_propname' => $pageProp,
			]
		);

		if ( !$value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Similar to getPageProp().
	 */
	public static function getAllPageProps( $pageProp ) {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'page_props', [
			'pp_page',
			'pp_value'
			], [
			'pp_propname' => $pageProp
			]
		);

		$pagesPerValue = [];
		foreach ( $res as $row ) {
			$pageID = $row->pp_page;
			$pageValue = $row->pp_value;
			if ( array_key_exists( $pageValue, $pagesPerValue ) ) {
				$pagesPerValue[$pageValue][] = $pageID;
			} else {
				$pagesPerValue[$pageValue] = [ $pageID ];
			}
		}

		return $pagesPerValue;
	}

	/**
	 * Gets the template page where this table is defined -
	 * hopefully there's exactly one of them.
	 */
	public static function getTemplateIDForDBTable( $tableName ) {
		$dbr = wfGetDB( DB_REPLICA );
		$page = $dbr->selectField( 'page_props', [
			'pp_page'
			], [
			'pp_value' => $tableName,
			'pp_propname' => 'CargoTableName'
			]
		);
		if ( !$page ) {
			return null;
		}
		return $page;
	}

	public static function formatError( $errorString ) {
		return Html::element( 'div', [ 'class' => 'error' ], $errorString );
	}

	public static function displayErrorMessage( OutputPage $out, Message $message ) {
		if ( method_exists( $out, 'wrapWikiTextAsInterface' ) ) {
			// MW 1.32+
			$out->wrapWikiTextAsInterface( 'error', $message->plain() );
		} else {
			$out->addHTML( self::formatError( $message->parse() ) );
		}
	}

	public static function getTables() {
		$tableNames = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', 'main_table' );
		foreach ( $res as $row ) {
			$tableName = $row->main_table;
			// Skip "replacement" tables.
			if ( substr( $tableName, -6 ) == '__NEXT' ) {
				continue;
			}
			$tableNames[] = $tableName;
		}
		return $tableNames;
	}

	public static function getParentTables( $tableName ) {
		$parentTables = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', [ 'template_id', 'main_table' ] );
		foreach ( $res as $row ) {
			if ( $tableName == $row->main_table ) {
				$parentTables = self::getPageProp( $row->template_id, 'CargoParentTables' );
			}
		}
		if ( $parentTables ) {
			return unserialize( $parentTables );
		}
	}

	public static function getChildTables( $tableName ) {
		$childTables = [];
		$allParentTablesInfo = self::getAllPageProps( 'CargoParentTables' );
		foreach ( $allParentTablesInfo as $parentTablesInfoStr => $templateIDs ) {
			$parentTablesInfo = unserialize( $parentTablesInfoStr );
			foreach ( $parentTablesInfo as $alias => $parentTableInfo ) {
				$remoteTable = $parentTableInfo['Name'];
				if ( $remoteTable !== $tableName ) {
					continue;
				}
				$localField = $parentTableInfo['_localField'];
				$remoteField = $parentTableInfo['_remoteField'];
				// There should only ever be one ID here... right?
				foreach ( $templateIDs as $templateID ) {
					$childTable = self::getPageProp( $templateID, 'CargoTableName' );
					$childTables[] = [
						'childTable' => $childTable,
						'childField' => $localField,
						'parentTable' => $remoteTable,
						'parentField' => $remoteField
					];
				}
			}
		}
		return $childTables;
	}

	public static function getDrilldownTabsParams( $tableName ) {
		$drilldownTabs = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', [ 'template_id', 'main_table' ] );
		foreach ( $res as $row ) {
			if ( $tableName == $row->main_table ) {
				$drilldownTabs = self::getPageProp( $row->template_id, 'CargoDrilldownTabsParams' );
			}
		}
		if ( $drilldownTabs ) {
			return unserialize( $drilldownTabs );
		}
	}

	public static function getTableSchemas( $tableNames ) {
		$mainTableNames = [];
		foreach ( $tableNames as $tableName ) {
			if ( strpos( $tableName, '__' ) !== false &&
				strpos( $tableName, '__NEXT' ) === false ) {
				// We just want the first part of it.
				$tableNameParts = explode( '__', $tableName );
				$tableName = $tableNameParts[0];
			}
			if ( !in_array( $tableName, $mainTableNames ) ) {
				$mainTableNames[] = $tableName;
			}
		}
		$tableSchemas = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', [ 'main_table', 'table_schema' ],
			[ 'main_table' => $mainTableNames ] );
		foreach ( $res as $row ) {
			$tableName = $row->main_table;
			$tableSchemaString = $row->table_schema;
			$tableSchemas[$tableName] = CargoTableSchema::newFromDBString( $tableSchemaString );
		}

		// Validate the table names.
		if ( count( $tableSchemas ) < count( $mainTableNames ) ) {
			foreach ( $mainTableNames as $tableName ) {
				if ( !array_key_exists( $tableName, $tableSchemas ) ) {
					throw new MWException( wfMessage( "cargo-unknowntable", $tableName )->parse() );
				}
			}
		}

		return $tableSchemas;
	}

	/**
	 * Get the Cargo table for the passed-in template specified via
	 * either #cargo_declare or #cargo_attach, if the template has a
	 * call to either one.
	 */
	public static function getTableNameForTemplate( $templateTitle ) {
		$templatePageID = $templateTitle->getArticleID();
		$declaredTableName = self::getPageProp( $templatePageID, 'CargoTableName' );
		if ( $declaredTableName !== null ) {
			return [ $declaredTableName, true ];
		}
		$attachedTableName = self::getPageProp( $templatePageID, 'CargoAttachedTable' );
		return [ $attachedTableName, false ];
	}

	/**
	 * Make the alias different from table name to avoid removal of aliases (when passed
	 * in to Mediawiki's select() call) if the alias and table name are the same.
	 * Aliases are needed because sometimes the same table can be joined more than once, if
	 * it serves as two different parent tables
	 * @param string $tableName
	 * @return string
	 */
	public static function makeDifferentAlias( $tableName ) {
		$tableAlias = $tableName . "_alias";
		return $tableAlias;
	}

	/**
	 * Splits a string by the delimiter, but ensures that parenthesis, separators
	 * and "the other quote" (single quote in a double quoted string or double
	 * quote in a single quoted string) inside a quoted string are not considered
	 * lexically.
	 * @param string $delimiter The delimiter to split by.
	 * @param string $string The string to split.
	 * @param bool $includeBlankValues Whether to include blank values in the returned array.
	 * @return string[] Array of substrings (with or without blank values).
	 * @throws MWException On unmatched quotes or incomplete escape sequences.
	 */
	public static function smartSplit( $delimiter, $string, $includeBlankValues = false ) {
		if ( $string == '' ) {
			return [];
		}

		$ignoreNextChar = false;
		$returnValues = [];
		$numOpenParentheses = 0;
		$curReturnValue = '';
		$strLength = strlen( $string );
		for ( $i = 0; $i < $strLength; $i++ ) {
			$curChar = $string[$i];

			if ( $ignoreNextChar ) {
				// If previous character was a backslash,
				// ignore the current one, since it's escaped.
				// What if this one is a backslash too?
				// Doesn't matter - it's escaped.
				$ignoreNextChar = false;
			} elseif ( $curChar == '(' ) {
				$numOpenParentheses++;
			} elseif ( $curChar == ')' ) {
				$numOpenParentheses--;
			} elseif ( $curChar == '\'' || $curChar == '"' ) {
				$pos = self::findQuotedStringEnd( $string, $curChar, $i + 1 );
				if ( $pos === false ) {
					throw new MWException( "Error: unmatched quote in SQL string constant." );
				}
				$curReturnValue .= substr( $string, $i, $pos - $i );
				$i = $pos;
			} elseif ( $curChar == '\\' ) {
				$ignoreNextChar = true;
			}

			if ( $curChar == $delimiter && $numOpenParentheses == 0 ) {
				$returnValues[] = trim( $curReturnValue );
				$curReturnValue = '';
			} else {
				$curReturnValue .= $curChar;
			}
		}
		$returnValues[] = trim( $curReturnValue );

		if ( $ignoreNextChar ) {
			throw new MWException( "Error: incomplete escape sequence." );
		}

		if ( $includeBlankValues ) {
			return $returnValues;
		}

		// Remove empty strings (but not other quasi-empty values, like '0') and re-key the array.
		$noEmptyStrings = function ( $s ) {
			return $s !== '';
		};
		return array_values( array_filter( $returnValues, $noEmptyStrings ) );
	}

	/**
	 * Finds the end of a quoted string.
	 */
	public static function findQuotedStringEnd( $string, $quoteChar, $pos ) {
		$ignoreNextChar = false;
		$strLength = strlen( $string );
		for ( $i = $pos; $i < $strLength; $i++ ) {
			$curChar = $string[$i];
			if ( $ignoreNextChar ) {
				$ignoreNextChar = false;
			} elseif ( $curChar == $quoteChar ) {
				if ( $i + 1 < $strLength && $string[$i + 1] == $quoteChar ) {
					$i++;
				} else {
					return $i;
				}
			} elseif ( $curChar == '\\' ) {
				$ignoreNextChar = true;
			}
		}
		if ( $ignoreNextChar ) {
			throw new MWException( "Error: incomplete escape sequence." );
		}
		return false;
	}

	/**
	 * Deletes text within quotes and raises and exception if a quoted string
	 * is not closed.
	 */
	public static function removeQuotedStrings( $string ) {
		$noQuotesPattern = '/("|\')([^\\1\\\\]|\\\\.)*?\\1/';
		$string = preg_replace( $noQuotesPattern, '', $string );
		if ( strpos( $string, '"' ) !== false || strpos( $string, "'" ) !== false ) {
			throw new MWException( "Error: unclosed string literal." );
		}
		return $string;
	}

	/**
	 * Get rid of the "File:" or "Image:" (in the wiki's language) at the
	 * beginning of a file name, if it's there.
	 */
	public static function removeNamespaceFromFileName( $fileName ) {
		$fileTitle = Title::newFromText( $fileName, NS_FILE );
		if ( $fileTitle == null ) {
			return null;
		}
		return $fileTitle->getText();
	}

	/**
	 * Generates a Regular Expression to match $fieldName in a SQL string.
	 * Allows for $ as valid identifier character.
	 */
	public static function getSQLFieldPattern( $fieldName, $closePattern = true ) {
		$fieldName = str_replace( '$', '\$', $fieldName );
		$pattern = '/([^\w$.,]|^)' . $fieldName;
		return $pattern . ( $closePattern ? '([^\w$]|$)/' : '' );
	}

	/**
	 * Generates a Regular Expression to match $tableName.$fieldName in a
	 * SQL string. Allows for $ as valid identifier character.
	 */
	public static function getSQLTableAndFieldPattern( $tableName, $fieldName, $closePattern = true ) {
		$fieldName = str_replace( '$', '\$', $fieldName );
		$tableName = str_replace( '$', '\$', $tableName );
		$pattern = '/([^\w$,]|^)' . $tableName . '\.' . $fieldName;
		return $pattern . ( $closePattern ? '([^\w$]|$)/ui' : '' );
	}

	/**
	 * Generates a Regular Expression to match $tableName in a SQL string.
	 * Allows for $ as valid identifier character.
	 */
	public static function getSQLTablePattern( $tableName, $closePattern = true ) {
		$tableName = str_replace( '$', '\$', $tableName );
		$pattern = '/([^\w$]|^)(' . $tableName . ')\.(\w*)';
		return $pattern . ( $closePattern ? '/ui' : '' );
	}

	/**
	 * Determines whether a string is a literal.
	 * This may need different handling for different (non-MySQL) DB types.
	 */
	public static function isSQLStringLiteral( $string ) {
		return $string[0] == "'" && substr( $string, -1, 1 ) == "'";
	}

	public static function getDateFunctions( $dateDBField ) {
		global $wgCargoDBtype;

		// Unfortunately, date handling in general - and date extraction
		// specifically - is done differently in almost every DB
		// system. If support were ever added for SQLite or Oracle,
		// those would require special handling as well.
		if ( $wgCargoDBtype == 'postgres' ) {
			$yearValue = "EXTRACT(YEAR FROM $dateDBField)";
			$monthValue = "EXTRACT(MONTH FROM $dateDBField)";
			$dayValue = "EXTRACT(DAY FROM $dateDBField)";
		} else { // MySQL, MS SQL Server
			$yearValue = "YEAR($dateDBField)";
			$monthValue = "MONTH($dateDBField)";
			// SQL Server only supports DAY(), not DAYOFMONTH().
			$dayValue = "DAY($dateDBField)";
		}
		return [ $yearValue, $monthValue, $dayValue ];
	}

	/**
	 * Parses a piece of wikitext differently depending on whether
	 * we're in a special or regular page.
	 *
	 * @global WebRequest $wgRequest
	 * @param string $value
	 * @param Parser $parser
	 * @return string
	 */
	public static function smartParse( $value, $parser ) {
		global $wgRequest;

		// This decode() call is here in case the value was
		// set using {{PAGENAME}}, which for some reason
		// HTML-encodes some of its characters - see
		// https://www.mediawiki.org/wiki/Help:Magic_words#Page_names
		// Of course, String and Page fields could be set using
		// {{PAGENAME}} as well, but those seem less likely.
		$value = htmlspecialchars_decode( $value );

		// Add a newline at the beginning if it looks like the value
		// starts with a bulleted or numbered list, to make sure that
		// the first line gets formatted correctly.
		if ( strpos( $value, '*' ) === 0 || strpos( $value, '#' ) === 0 ) {
			$value = "\n" . $value;
		}

		// Add __NOTOC__ and __NOEDITSECTION__ "behavior switches"
		// to the beginning of this value, so that, on the off chance
		// that it contains section headers, a table of contents and
		// edit links will not appear in the parsed output.
		// We avoid newlines and extra spaces here (we don't need
		// them) to not mess up the formatting.
		$value = "__NOTOC____NOEDITSECTION__$value";

		// Parse it as if it's wikitext. The exact call
		// depends on whether we're in a special page or not.
		if ( $parser === null ) {
			$parser = MediaWikiServices::getInstance()->getParser();
		}

		// Since MW 1.35, Parser::getTitle() throws a TypeError if it
		// would have returned null, so just catch the error.
		// Why would the title be null? It's not clear, but it seems to
		// happen in at least once case: in "action=pagevalues" for a
		// page with non-ASCII characters in its name.
		try {
			$title = $parser->getTitle();
		} catch ( TypeError $e ) {
			$title = null;
		}

		if ( $title === null ) {
			global $wgTitle;
			$title = $wgTitle;
		}

		if ( $title != null && $title->isSpecial( 'RunJobs' ) ) {
			// Conveniently, if this is called from within a job
			// being run, the name of the page will be
			// Special:RunJobs.
			// If that's the case, do nothing - we don't need to
			// parse the value.
		// This next clause should only be called for Cargo's special
		// pages, not for SF's Special:RunQuery. Don't know about other
		// special pages.
		} elseif ( ( $title != null && $title->isSpecialPage() && !$wgRequest->getCheck( 'wpRunQuery' ) ) ||
			// The 'pagevalues' action is also a Cargo special page.
			$wgRequest->getVal( 'action' ) == 'pagevalues' ) {
			$parserOptions = ParserOptions::newFromAnon();
			if ( !defined( 'ParserOutput::SUPPORTS_UNWRAP_TRANSFORM' ) ) {
				// Remove '<div class="mw-parser-output">' from around
				// the value, if it was parsed - this method was
				// deprecated in MW 1.31.
				$parserOptions->setWrapOutputClass( false );
			}
			$parserOutput = $parser->parse( $value, $title, $parserOptions, false );
			$value = $parserOutput->getText( [ 'unwrap' => true ] );
		} else {
			$value = $parser->internalParse( $value );
		}
		return $value;
	}

	public static function parsePageForStorage( $title, $pageContents ) {
		// Special handling for the Approved Revs extension.
		$approvedContent = null;
		if ( class_exists( 'ApprovedRevs' ) ) {
			$approvedContent = ApprovedRevs::getApprovedContent( $title );
		}
		if ( $approvedContent != null ) {
			if ( method_exists( $approvedContent, 'getText' ) ) {
				// Approved Revs 1.0+
				$pageText = $approvedContent->getText();
			} else {
				$pageText = $approvedContent;
			}
		} else {
			$pageText = $pageContents;
		}
		$parser = MediaWikiServices::getInstance()->getParser();
		$parserOptions = ParserOptions::newFromAnon();
		$parser->parse( $pageText, $title, $parserOptions );
	}

	/**
	 * Drop, and then create again, the database table(s) holding the
	 * data for this template.
	 * Why "tables"? Because every field that holds a list of values gets
	 * its own helper table.
	 *
	 * @param int $templatePageID
	 * @return bool
	 * @throws MWException
	 */
	public static function recreateDBTablesForTemplate(
		$templatePageID,
		$createReplacement,
		User $user,
		$tableName = null
	) {
		$tableSchemaString = self::getPageProp( $templatePageID, 'CargoFields' );
		// First, see if there even is DB storage for this template -
		// if not, exit.
		if ( $tableSchemaString === null ) {
			return false;
		}
		$tableSchema = CargoTableSchema::newFromDBString( $tableSchemaString );

		if ( $tableName == null ) {
			$tableName = self::getPageProp( $templatePageID, 'CargoTableName' );
		}

		$parentTablesStr = self::getPageProp( $templatePageID, 'CargoParentTables' );
		if ( $parentTablesStr ) {
			$parentTables = unserialize( $parentTablesStr );
		} else {
			$parentTables = [];
		}

		$dbw = wfGetDB( DB_MASTER );
		$cdb = self::getDB();

		// Cannot run any recreate if a replacement table exists.
		$possibleReplacementTable = $tableName . '__NEXT';
		if ( self::tableFullyExists( $tableName ) && self::tableFullyExists( $possibleReplacementTable ) ) {
			throw new MWException( wfMessage( 'cargo-recreatedata-replacementexists', $tableName, $possibleReplacementTable )->parse() );
		}

		if ( $createReplacement ) {
			$tableName .= '__NEXT';
			if ( $cdb->tableExists( $possibleReplacementTable ) ) {
				// The replacement table exists, but it does
				// not have a row in cargo_tables - this is
				// hopefully a rare occurrence.
				try {
					$cdb->begin();
					$cdb->dropTable( $tableName );
					$cdb->commit();
				} catch ( Exception $e ) {
					throw new MWException( "Caught exception ($e) while trying to drop Cargo table. "
					. "Please make sure that your database user account has the DROP permission." );
				}
			}
		} else {
			// @TODO - is an array really necessary? Shouldn't it
			// always be just one table name? Tied in with that,
			// if a table name was already specified, do we need
			// to do a lookup here?
			$tableNames = [];
			$res = $dbw->select( 'cargo_tables', 'main_table', [ 'template_id' => $templatePageID ] );
			foreach ( $res as $row ) {
				$tableNames[] = $row->main_table;
			}

			// For whatever reason, that DB query might have failed -
			// if so, just add the table name here.
			if ( $tableName != null && !in_array( $tableName, $tableNames ) ) {
				$tableNames[] = $tableName;
			}

			$mainTableAlreadyExists = self::tableFullyExists( $tableNames[0] );
			foreach ( $tableNames as $curTable ) {
				try {
					$cdb->begin();
					$cdb->dropTable( $curTable );
					$cdb->commit();
				} catch ( Exception $e ) {
					throw new MWException( "Caught exception ($e) while trying to drop Cargo table. "
					. "Please make sure that your database user account has the DROP permission." );
				}
				$dbw->delete( 'cargo_pages', [ 'table_name' => $curTable ] );
			}

			$dbw->delete( 'cargo_tables', [ 'template_id' => $templatePageID ] );
		}

		self::createCargoTableOrTables( $cdb, $dbw, $tableName, $tableSchema, $tableSchemaString, $templatePageID );

		if ( !$createReplacement ) {
			// Log this.
			if ( $mainTableAlreadyExists ) {
				self::logTableAction( 'recreatetable', $tableName, $user );
			} else {
				self::logTableAction( 'createtable', $tableName, $user );
			}
		}

		return true;
	}

	public static function tableFullyExists( $tableName ) {
		$dbr = wfGetDB( DB_REPLICA );
		$numRows = $dbr->selectRowCount( 'cargo_tables', '*', [ 'main_table' => $tableName ], __METHOD__ );
		if ( $numRows == 0 ) {
			return false;
		}

		$cdb = self::getDB();
		return $cdb->tableExists( $tableName );
	}

	public static function fieldTypeToSQLType( $fieldType, $dbType, $size = null ) {
		global $wgCargoDefaultStringBytes;

		// Possible values for $dbType: "mssql", "mysql", "oracle",
		// "postgres", "sqlite"
		// @TODO - make sure it's one of these.
		if ( $fieldType == 'Integer' ) {
			switch ( $dbType ) {
				case "mssql":
				case "mysql":
				case "postgres":
					return 'Int';
				case "sqlite":
					return 'INTEGER';
				case "oracle":
					return 'Number';
			}
		} elseif ( $fieldType == 'Float' || $fieldType == 'Rating' ) {
			switch ( $dbType ) {
				case "mssql":
					return 'Float';
				case "mysql":
					return 'Double';
				case "postgres":
					return 'Numeric';
				case "sqlite":
					return 'REAL';
				case "oracle":
					return 'Number';
			}
		} elseif ( $fieldType == 'Boolean' ) {
			switch ( $dbType ) {
				case "mssql":
					return 'Bit';
				case "mysql":
				case "postgres":
					return 'Boolean';
				case "sqlite":
					return 'INTEGER';
				case "oracle":
					return 'Byte';
			}
		} elseif ( $fieldType == 'Date' || $fieldType == 'Start date' || $fieldType == 'End date' ) {
			switch ( $dbType ) {
				case "mssql":
				case "mysql":
				case "postgres":
				case "oracle":
					return 'Date';
				case "sqlite":
					// Should really be 'REAL', with
					// accompanying handling.
					return 'TEXT';
			}
		} elseif ( $fieldType == 'Datetime' || $fieldType == 'Start datetime' || $fieldType == 'End datetime' ) {
			// Some DB types have a datetime type that includes
			// the time zone, but MySQL unfortunately doesn't,
			// so the best solution for time zones is probably
			// to have a separate field for them.
			switch ( $dbType ) {
				case "mssql":
					return 'Datetime2';
				case "mysql":
					return 'Datetime';
				case "postgres":
				case "oracle":
					return 'Timestamp';
				case "sqlite":
					// Should really be 'REAL', with
					// accompanying handling.
					return 'TEXT';
			}
		} elseif ( $fieldType == 'Text' || $fieldType == 'Wikitext' ) {
			switch ( $dbType ) {
				case "mssql":
					return 'Varchar(Max)';
				case "mysql":
				case "postgres":
				case "sqlite":
					return 'Text';
				case "oracle":
					return 'Varchar2(4000)';
			}
		} elseif ( $fieldType == 'Searchtext' ) {
			if ( $dbType != 'mysql' ) {
				throw new MWException( "Error: a \"Searchtext\" field can currently only be defined for MySQL databases." );
			}
			return 'Mediumtext';
		} else { // 'String', 'Page', 'Wikitext string', etc.
			if ( $size == null ) {
				$size = $wgCargoDefaultStringBytes;
			}
			switch ( $dbType ) {
				case "mssql":
					return "Varchar($size)";
				case "mysql":
				case "postgres":
					// For at least MySQL, there's a limit
					// on how many total bytes a table's
					// fields can have, and "Text" and
					// "Blob" fields don't get added to the
					// total, so if it's a big piece of
					// text, just make it a "Text" field.
					if ( $size > 1000 ) {
						return 'Text';
					} else {
						return "Varchar($size)";
					}
				case "oracle":
					return "Varchar2($size)";
				case "sqlite":
					return 'TEXT';
			}
		}
	}

	public static function createCargoTableOrTables( $cdb, $dbw, $tableName, $tableSchema, $tableSchemaString, $templatePageID ) {
		$cdb->begin();
		$cdbTableName = $cdb->addIdentifierQuotes( $cdb->tableName( $tableName, 'plain' ) );
		$fieldsInMainTable = [
			'_ID' => 'Integer',
			'_pageName' => 'String',
			'_pageTitle' => 'String',
			'_pageNamespace' => 'Integer',
			'_pageID' => 'Integer',
		];

		$containsFileType = false;
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			$size = $fieldDescription->mSize;
			$isList = $fieldDescription->mIsList;
			$fieldType = $fieldDescription->mType;

			if ( $isList || $fieldType == 'Coordinates' ) {
				// No field will be created with this name -
				// instead, we'll have one called
				// fieldName + '__full', and a separate table
				// for holding each value.
				// The field holding the full list will always
				// just be text - and it could be long.
				$fieldsInMainTable[$fieldName . '__full'] = 'Text';
			} else {
				$fieldsInMainTable[$fieldName] = $fieldDescription;
			}

			if ( !$isList && $fieldType == 'Coordinates' ) {
				$fieldsInMainTable[$fieldName . '__lat'] = 'Float';
				$fieldsInMainTable[$fieldName . '__lon'] = 'Float';
			} elseif ( $fieldType == 'Date' || $fieldType == 'Datetime' ||
					$fieldType == 'Start date' || $fieldType == 'Start datetime' ||
					$fieldType == 'End date' || $fieldType == 'End datetime' ) {
				$fieldsInMainTable[$fieldName . '__precision'] = 'Integer';
			} elseif ( $fieldType == 'File' ) {
				$containsFileType = true;
			}
		}

		self::createTable( $cdb, $tableName, $fieldsInMainTable );

		// Now also create tables for each of the 'list' fields,
		// if there are any.
		$fieldTableNames = []; // Names of tables that store data regarding pages
		$fieldHelperTableNames = []; // Names of tables that store metadata regarding template or fields
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( $fieldDescription->mIsList ) {
				// The double underscore in this table name
				// should prevent anyone from giving this name
				// to a "real" table.
				$fieldTableName = $tableName . '__' . $fieldName;
				$cdb->dropTable( $fieldTableName );

				$fieldsInTable = [ '_rowID' => 'Integer' ];
				$fieldType = $fieldDescription->mType;
				if ( $fieldType == 'Coordinates' ) {
					$fieldsInTable['_lat'] = 'Float';
					$fieldsInTable['_lon'] = 'Float';
				} else {
					$fieldsInTable['_value'] = $fieldType;
				}
				$fieldsInTable['_position'] = 'Integer';

				self::createTable( $cdb, $fieldTableName, $fieldsInTable );
				$fieldTableNames[] = $fieldTableName;
			}
			if ( $fieldDescription->mIsHierarchy ) {
				$fieldHelperTableName = $tableName . '__' . $fieldName . '__hierarchy';
				$cdb->dropTable( $fieldHelperTableName );
				$fieldType = $fieldDescription->mType;
				$fieldsInTable = [
					'_value' => $fieldType,
					'_left' => 'Integer',
					'_right' => 'Integer',
				];
				self::createTable( $cdb, $fieldHelperTableName, $fieldsInTable, true );

				$fieldHelperTableNames[] = $fieldHelperTableName;
				// Insert hierarchy information in the __hierarchy table
				$hierarchyTree = CargoHierarchyTree::newFromWikiText( $fieldDescription->mHierarchyStructure );
				$hierarchyStructureTableData = $hierarchyTree->generateHierarchyStructureTableData();
				foreach ( $hierarchyStructureTableData as $entry ) {
					$cdb->insert( $fieldHelperTableName, $entry );
				}
			}
		}

		// And create a helper table holding all the files stored in
		// this table, if there are any.
		if ( $containsFileType ) {
			$fileTableName = $tableName . '___files';
			$cdb->dropTable( $fileTableName );
			$fieldsInTable = [
				'_pageName' => 'String',
				'_pageID' => 'Integer',
				'_fieldName' => 'String',
				'_fileName' => 'String'
			];
			self::createTable( $cdb, $fileTableName, $fieldsInTable );
		}

		// End transaction and apply DB changes.
		$cdb->commit();

		// Finally, store all the info in the cargo_tables table.
		$dbw->insert( 'cargo_tables', [
			'template_id' => $templatePageID,
			'main_table' => $tableName,
			'field_tables' => serialize( $fieldTableNames ),
			'field_helper_tables' => serialize( $fieldHelperTableNames ),
			'table_schema' => $tableSchemaString
		] );
	}

	public static function createTable( $cdb, $tableName, $fieldsInTable, $multipleColumnIndex = false ) {
		global $wgCargoDBRowFormat;

		// Unfortunately, there is not yet a 'CREATE TABLE' wrapper
		// in the MediaWiki DB API, so we have to call SQL directly.
		$dbType = $cdb->getType();
		$sqlTableName = $cdb->tableName( $tableName );
		$createSQL = "CREATE TABLE $sqlTableName ( ";
		$firstField = true;
		foreach ( $fieldsInTable as $fieldName => $fieldDescOrType ) {
			$fieldOptionsText = '';
			if ( is_object( $fieldDescOrType ) ) {
				$fieldType = $fieldDescOrType->mType;
				$fieldSize = $fieldDescOrType->mSize;
				$sqlType = self::fieldTypeToSQLType( $fieldType, $dbType, $fieldSize );

				if ( $fieldDescOrType->mIsMandatory ) {
					$fieldOptionsText .= ' NOT NULL';
				}
				if ( $fieldDescOrType->mIsUnique ) {
					$fieldOptionsText .= ' UNIQUE';
				}
			} else {
				$fieldType = $fieldDescOrType;
				$sqlType = self::fieldTypeToSQLType( $fieldType, $dbType );
				if ( $fieldName == '_ID' ) {
					$fieldOptionsText .= ' PRIMARY KEY';
				} elseif ( $fieldName == '_rowID' ) {
					$fieldOptionsText .= ' NOT NULL';
				}
			}
			if ( $firstField ) {
				$firstField = false;
			} else {
				$createSQL .= ', ';
			}
			$sqlFieldName = $cdb->addIdentifierQuotes( $fieldName );
			$createSQL .= "$sqlFieldName $sqlType $fieldOptionsText";
			if ( $fieldType == 'Searchtext' ) {
				$createSQL .= ", FULLTEXT KEY $fieldName ( $sqlFieldName )";
			}
		}

		$createSQL .= ' )';
		// Allow for setting a format like COMPRESSED, DYNAMIC etc.
		if ( $wgCargoDBRowFormat != null ) {
			$createSQL .= " ROW_FORMAT=$wgCargoDBRowFormat";
		}
		$cdb->query( $createSQL );

		// Add an index for any field that's not of type Text,
		// Searchtext or Wikitext.
		$indexedFields = [];
		foreach ( $fieldsInTable as $fieldName => $fieldDescOrType ) {
			// We don't need to index _ID, because it's already
			// the primary key.
			if ( $fieldName == '_ID' ) {
				continue;
			}

			// @HACK - MySQL does not allow more than 64 keys/
			// indexes per table. We are indexing most fields -
			// so if a table has more than 64 fields, there's a
			// good chance that it will overrun this limit.
			// So we just stop indexing after the first 60.
			if ( count( $indexedFields ) >= 60 ) {
				break;
			}

			if ( is_object( $fieldDescOrType ) ) {
				$fieldType = $fieldDescOrType->mType;
			} else {
				$fieldType = $fieldDescOrType;
			}
			if ( in_array( $fieldType, [ 'Text', 'Searchtext', 'Wikitext' ] ) ) {
				continue;
			}
			$indexedFields[] = $fieldName;
		}

		if ( $multipleColumnIndex ) {
			$indexName = "nested_set_$tableName";
			$sqlFieldNames = array_map(
				[ $cdb, 'addIdentifierQuotes' ],
				$indexedFields
			);
			$sqlFieldNamesStr = implode( ', ', $sqlFieldNames );
			$createIndexSQL = "CREATE INDEX $indexName ON " .
				"$sqlTableName ($sqlFieldNamesStr)";
			$cdb->query( $createIndexSQL );
		} else {
			foreach ( $indexedFields as $fieldName ) {
				$indexName = $fieldName . '_' . $tableName;
				// MySQL doesn't allow index names with more than 64 characters.
				$indexName = substr( $indexName, 0, 64 );
				$sqlFieldName = $cdb->addIdentifierQuotes( $fieldName );
				$sqlIndexName = $cdb->addIdentifierQuotes( $indexName );
				$createIndexSQL = "CREATE INDEX $sqlIndexName ON " .
					"$sqlTableName ($sqlFieldName)";
				$cdb->query( $createIndexSQL );
			}
		}
	}

	public static function fullTextMatchSQL( $cdb, $tableName, $fieldName, $searchTerm ) {
		$fullFieldName = self::escapedFieldName( $cdb, $tableName, $fieldName );
		$searchTerm = $cdb->addQuotes( $searchTerm );
		return " MATCH($fullFieldName) AGAINST ($searchTerm IN BOOLEAN MODE) ";
	}

	/**
	 * Parses one half of a set of coordinates into a number.
	 *
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/MDVCoordinates.js)
	 * - though that one is in Javascript.
	 */
	public static function coordinatePartToNumber( $coordinateStr ) {
		$degreesSymbols = [ "\x{00B0}", "d" ];
		$minutesSymbols = [ "'", "\x{2032}", "\x{00B4}" ];
		$secondsSymbols = [ '"', "\x{2033}", "\x{00B4}\x{00B4}" ];

		$numDegrees = null;
		$numMinutes = null;
		$numSeconds = null;

		foreach ( $degreesSymbols as $degreesSymbol ) {
			$pattern = '/([\d\.]+)' . $degreesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numDegrees = floatval( $matches[1] );
				break;
			}
		}
		if ( $numDegrees == null ) {
			throw new MWException( "Error: could not parse degrees in \"$coordinateStr\"." );
		}

		foreach ( $minutesSymbols as $minutesSymbol ) {
			$pattern = '/([\d\.]+)' . $minutesSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numMinutes = floatval( $matches[1] );
				break;
			}
		}
		if ( $numMinutes == null ) {
			// This might not be an error - the number of minutes
			// might just not have been set.
			$numMinutes = 0;
		}

		foreach ( $secondsSymbols as $secondsSymbol ) {
			$pattern = '/(\d+)' . $secondsSymbol . '/u';
			if ( preg_match( $pattern, $coordinateStr, $matches ) ) {
				$numSeconds = floatval( $matches[1] );
				break;
			}
		}
		if ( $numSeconds == null ) {
			// This might not be an error - the number of seconds
			// might just not have been set.
			$numSeconds = 0;
		}

		return ( $numDegrees + ( $numMinutes / 60 ) + ( $numSeconds / 3600 ) );
	}

	/**
	 * Parses a coordinate string in (hopefully) any standard format.
	 *
	 * Copied from Miga, also written by Yaron Koren
	 * (https://github.com/yaronkoren/miga/blob/master/MDVCoordinates.js)
	 * - though that one is in Javascript.
	 */
	public static function parseCoordinatesString( $coordinatesString ) {
		$coordinatesString = trim( $coordinatesString );
		if ( $coordinatesString == null ) {
			return;
		}

		// This is safe to do, right?
		$coordinatesString = str_replace( [ '[', ']' ], '', $coordinatesString );
		// See if they're separated by commas.
		if ( strpos( $coordinatesString, ',' ) > 0 ) {
			$latAndLonStrings = explode( ',', $coordinatesString );
		} else {
			// If there are no commas, the first half, for the
			// latitude, should end with either 'N' or 'S', so do a
			// little hack to split up the two halves.
			$coordinatesString = str_replace( [ 'N', 'S' ], [ 'N,', 'S,' ], $coordinatesString );
			$latAndLonStrings = explode( ',', $coordinatesString );
		}

		if ( count( $latAndLonStrings ) != 2 ) {
			throw new MWException( "Error parsing coordinates string: \"$coordinatesString\"." );
		}
		list( $latString, $lonString ) = $latAndLonStrings;

		// Handle strings one at a time.
		$latIsNegative = false;
		if ( strpos( $latString, 'S' ) > 0 ) {
			$latIsNegative = true;
		}
		$latString = str_replace( [ 'N', 'S' ], '', $latString );
		if ( is_numeric( $latString ) ) {
			$latNum = floatval( $latString );
		} else {
			$latNum = self::coordinatePartToNumber( $latString );
		}
		if ( $latIsNegative ) {
			$latNum *= -1;
		}

		$lonIsNegative = false;
		if ( strpos( $lonString, 'W' ) > 0 ) {
			$lonIsNegative = true;
		}
		$lonString = str_replace( [ 'E', 'W' ], '', $lonString );
		if ( is_numeric( $lonString ) ) {
			$lonNum = floatval( $lonString );
		} else {
			$lonNum = self::coordinatePartToNumber( $lonString );
		}
		if ( $lonIsNegative ) {
			$lonNum *= -1;
		}

		return [ $latNum, $lonNum ];
	}

	public static function escapedFieldName( $cdb, $tableName, $fieldName ) {
		if ( is_array( $tableName ) ) {
			$tableAlias = key( $tableName );
			return $cdb->addIdentifierQuotes( $tableAlias ) . '.' .
				$cdb->addIdentifierQuotes( $fieldName );
		}
		return $cdb->tableName( $tableName ) . '.' .
			$cdb->addIdentifierQuotes( $fieldName );
	}

	public static function joinOfMainAndFieldTable( $cdb, $mainTableName, $fieldTableName ) {
		return [
			'LEFT OUTER JOIN',
			self::escapedFieldName( $cdb, $mainTableName, '_ID' ) .
				' = ' .
				self::escapedFieldName( $cdb, $fieldTableName, '_rowID' )
		];
	}

	public static function joinOfMainAndParentTable( $cdb, $mainTable, $mainTableField,
			$parentTable, $parentTableField ) {
		return [
			'LEFT OUTER JOIN',
			self::escapedFieldName( $cdb, $mainTable, $mainTableField ) .
			' = ' .
			self::escapedFieldName( $cdb, $parentTable, $parentTableField )
		];
	}

	public static function joinOfFieldAndMainTable( $cdb, $fieldTable, $mainTable,
			$isHierarchy = false, $hierarchyFieldName = null ) {
		if ( $isHierarchy ) {
			return [
				'LEFT OUTER JOIN',
				self::escapedFieldName( $cdb, $fieldTable, '_value' ) . ' = ' .
				self::escapedFieldName( $cdb, $mainTable, $hierarchyFieldName ),
			];
		} else {
			return [
				'LEFT OUTER JOIN',
				self::escapedFieldName( $cdb, $fieldTable, '_rowID' ) . ' = ' .
				self::escapedFieldName( $cdb, $mainTable, '_ID' ),
			];
		}
	}

	public static function joinOfSingleFieldAndHierarchyTable( $cdb, $singleFieldTableName, $fieldColumnName, $hierarchyTableName ) {
		return [
			'LEFT OUTER JOIN',
			self::escapedFieldName( $cdb, $singleFieldTableName, $fieldColumnName ) .
				' = ' .
				self::escapedFieldName( $cdb, $hierarchyTableName, '_value' )
		];
	}

	public static function escapedInsert( $db, $tableName, $fieldValues ) {
		// Put quotes around the field names - needed for Postgres,
		// which otherwise lowercases all field names.
		$quotedFieldValues = [];
		foreach ( $fieldValues as $fieldName => $fieldValue ) {
			$quotedFieldName = $db->addIdentifierQuotes( $fieldName );
			$quotedFieldValues[$quotedFieldName] = $fieldValue;
		}
		$db->insert( $tableName, $quotedFieldValues );
	}

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param LinkTarget|Title $title
	 * @param string|null $msg Must already be HTML escaped
	 * @param array $attrs link attributes
	 * @param array $params query parameters
	 *
	 * @return string HTML link
	 */
	public static function makeLink( $linkRenderer, $title, $msg = null, $attrs = [], $params = [] ) {
		global $wgTitle;

		if ( $title === null ) {
			return null;
		} elseif ( $wgTitle !== null && $title->equals( $wgTitle ) ) {
			// Display bolded text instead of a link.
			return Linker::makeSelfLinkObj( $title, $msg );
		} else {
			$html = ( $msg == null ) ? null : new HtmlArmor( $msg );
			return $linkRenderer->makeLink( $title, $html, $attrs, $params );
		}
	}

	public static function getSpecialPage( $pageName ) {
		if ( class_exists( 'MediaWiki\Special\SpecialPageFactory' ) ) {
			// MW 1.32+
			return MediaWikiServices::getInstance()
				->getSpecialPageFactory()
				->getPage( $pageName );
		} else {
			return SpecialPageFactory::getPage( $pageName );
		}
	}

	/**
	 * Get the wiki's content language.
	 * This is a wrapper to maintain backwards-compatibility for MediaWiki 1.31 and earlier.
	 * @since 2.6
	 * @return Language
	 */
	public static function getContentLang() {
		if ( method_exists( MediaWikiServices::class, 'getContentLanguage' ) ) {
			// MW >= 1.32
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		} else {
			global $wgContLang;
			$contLang = $wgContLang;
		}
		return $contLang;
	}

	public static function logTableAction( $actionName, $tableName, User $user ) {
		$log = new LogPage( 'cargo', false );
		$ctPage = self::getSpecialPage( 'CargoTables' );
		$ctTitle = $ctPage->getPageTitle();
		if ( $actionName == 'deletetable' ) {
			$logParams = [ $tableName ];
		} else {
			$ctURL = $ctTitle->getFullURL();
			$tableURL = "$ctURL/$tableName";
			$tableLink = Html::element(
				'a',
				[ 'href' => $tableURL ],
				$tableName
			);
			$logParams = [ $tableLink ];
		}
		// Every log entry requires an associated title; Cargo table
		// actions don't involve an actual page, so we just use
		// Special:CargoTables as the title.
		$log->addEntry( $actionName, $ctTitle, '', $logParams, $user );
	}

	public static function validateHierarchyStructure( $hierarchyStructure ) {
		$hierarchyNodesArray = explode( "\n", $hierarchyStructure );
		$matches = [];
		preg_match( '/^([*]*)[^*]*/i', $hierarchyNodesArray[0], $matches );
		if ( strlen( $matches[1] ) != 1 ) {
			throw new MWException( "Error: First entry of hierarchy values should start with exact one '*', the entry \"" .
				$hierarchyNodesArray[0] . "\" has " . strlen( $matches[1] ) . " '*'" );
		}
		$level = 0;
		foreach ( $hierarchyNodesArray as $node ) {
			if ( !preg_match( '/^([*]*)( *)(.*)/i', $node, $matches ) ) {
				throw new MWException( "Error: The \"" . $node . "\" entry of hierarchy values does not follow syntax. " .
					"The entry should be of the form : * entry" );
			}
			if ( strlen( $matches[1] ) < 1 ) {
				throw new MWException( "Error: Each entry of hierarchy values should start with atleast one '*', the entry \"" .
					$node . "\" has " . strlen( $matches[1] ) . " '*'" );
			}
			if ( strlen( $matches[1] ) - $level > 1 ) {
				throw new MWException( "Error: Level or count of '*' in hierarchy values should be increased only by count of 1, the entry \"" .
					$node . "\" should have " . ( $level + 1 ) . " or less '*'" );
			}
			$level = strlen( $matches[1] );
			if ( strlen( $matches[3] ) == 0 ) {
				throw new MWException( "Error: The entry of hierarchy values cannot be empty." );
			}
		}
	}

}
