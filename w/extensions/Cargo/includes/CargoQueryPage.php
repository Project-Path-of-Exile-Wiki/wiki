<?php

class CargoQueryPage extends QueryPage {
	public function __construct( $name = 'CargoQuery' ) {
		parent::__construct( $name );

		$req = $this->getRequest();
		$tablesStr = trim( $req->getVal( 'tables' ) );
		$fieldsStr = trim( $req->getVal( 'fields' ) );
		$whereStr = trim( $req->getVal( 'where' ) );
		$joinOnStr = trim( $req->getVal( 'join_on' ) );
		$groupByStr = trim( $req->getVal( 'group_by' ) );
		if ( substr( $groupByStr, -1, 1 ) == ',' ) {
			$groupByStr = substr( $groupByStr, 0, -1 ); // Remove last comma for group by
		}
		$havingStr = trim( $req->getVal( 'having' ) );

		$orderByStr = "";
		$orderByValues = $req->getArray( 'order_by' );
		$orderByOptions = $req->getArray( 'order_by_options' );
		if ( is_array( $orderByValues ) ) {
			foreach ( $orderByValues as $i => $curOrderBy ) {
				if ( $curOrderBy == '' ) {
					continue;
				}
				$orderByStr .= $curOrderBy;
				if ( $orderByOptions != null ) {
					$orderByStr .= ' ' . $orderByOptions[$i];
				}
				$orderByStr .= ',';
			}
		}
		if ( substr( $orderByStr, -1, 1 ) == ',' ) {
			$orderByStr = substr( $orderByStr, 0, -1 ); // Remove last comma for order by
		}
		$limitStr = trim( $req->getVal( 'limit' ) );
		$offsetStr = trim( $req->getVal( 'offset' ) );

		$this->sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );

		$formatStr = trim( $req->getVal( 'format' ) );
		$this->format = $formatStr;

		// This is needed for both the results display and the
		// navigation links.
		$this->displayParams = [];
		$queryStringValues = $this->getRequest()->getValues();
		foreach ( $queryStringValues as $key => $value ) {
			// For some reason, getValues() turns all spaces
			// into underlines.
			$paramName = str_replace( '_', ' ', $key );
			if ( !in_array( $paramName,
					[ 'title', 'tables', 'fields', 'join on', 'order by', 'group by', 'having', 'format',
					'offset' ] ) ) {
				$this->displayParams[$paramName] = $value;
			}
		}

		// 'dynamic table' makes its own use of 'order by'.
		if ( $this->format == 'dynamic table' ) {
			$this->displayParams['order by'] = $orderByStr;
		}
	}

	public function isExpensive() {
		return false;
	}

	public function isSyndicated() {
		return false;
	}

	// @todo - declare a getPageHeader() function, to show some
	// information about the query?

	/**
	 * @return string
	 */
	public function getRecacheDB() {
		return CargoUtils::getDB();
	}

	public function getQueryInfo() {
		$selectOptions = [];
		if ( $this->sqlQuery->mGroupByStr != '' ) {
			$selectOptions['GROUP BY'] = $this->sqlQuery->mGroupByStr;
		}
		if ( $this->sqlQuery->mHavingStr != '' ) {
			$selectOptions['HAVING'] = $this->sqlQuery->mHavingStr;
		}

		// "order by" is handled elsewhere, in getOrderFields().

		// Field aliases need to have quotes placed around them
		// before running the query.
		$cdb = CargoUtils::getDB();
		$aliasedFieldNames = [];
		foreach ( $this->sqlQuery->mAliasedFieldNames as $alias => $fieldName ) {
			foreach ( $this->sqlQuery->mAliasedFieldNames as $alias => $fieldName ) {
				// If it's really a field name, add quotes around it.
				if ( strpos( $fieldName, '(' ) === false && strpos( $fieldName, '.' ) === false &&
					!$cdb->isQuotedIdentifier( $fieldName ) && !CargoUtils::isSQLStringLiteral( $fieldName ) ) {
					$fieldName = $cdb->addIdentifierQuotes( $fieldName );
				}
				$aliasedFieldNames[$alias] = $fieldName;
			}
		}

		$queryInfo = [
			'tables' => $this->sqlQuery->mAliasedTableNames,
			'fields' => $aliasedFieldNames,
			'options' => $selectOptions
		];
		if ( $this->sqlQuery->mWhereStr != '' ) {
			$queryInfo['conds'] = $this->sqlQuery->mWhereStr;
		}
		if ( !empty( $this->sqlQuery->mJoinConds ) ) {
			$queryInfo['join_conds'] = $this->sqlQuery->mJoinConds;
		}
		return $queryInfo;
	}

	/**
	 * Returns an associative array that will be encoded and added to the
	 * paging links
	 * @return array
	 */
	public function linkParameters() {
		$possibleParams = [
			'tables', 'fields', 'where', 'join_on', 'order_by', 'group_by', 'having', 'format'
		];
		$linkParams = [];
		$req = $this->getRequest();
		foreach ( $possibleParams as $possibleParam ) {
			if ( $req->getCheck( $possibleParam ) ) {
				$linkParams[$possibleParam] = $req->getVal( $possibleParam );
			}
		}

		foreach ( $this->displayParams as $key => $value ) {
			$linkParams[$key] = $value;
		}

		return $linkParams;
	}

	public function getOrderFields() {
		return $this->sqlQuery->mOrderBy;
	}

	public function sortDescending() {
		return false;
	}

	public function formatResult( $skin, $result ) {
		// This function needs to be declared, but it is not called.
	}

	/**
	 * Format and output report results using the given information plus
	 * OutputPage
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use
	 * @param DatabaseBase $dbr Database (read) connection to use
	 * @param int $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
	 */
	public function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		$valuesTable = [];
		for ( $i = 0; $i < $num && $row = $res->fetchObject(); $i++ ) {
			$valuesTable[] = get_object_vars( $row );
		}
		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $this->sqlQuery );
		$queryDisplayer->mFieldDescriptions = $this->sqlQuery->mFieldDescriptions;
		$queryDisplayer->mFormat = $this->format;
		$formatter = $queryDisplayer->getFormatter( $out );

		if ( $formatter->isDeferred() ) {
			$text = $formatter->queryAndDisplay( [ $this->sqlQuery ], $this->displayParams );
			$out->addHTML( $text );
			return;
		}

		$this->displayParams['offset'] = $offset;
		$queryDisplayer->mDisplayParams = $this->displayParams;
		$html = $queryDisplayer->displayQueryResults( $formatter, $valuesTable );
		$out->addHTML( $html );
	}
}
