<?php
/**
 * Adds and handles the 'cargoquery' action to the MediaWiki API.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

class CargoQueryAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$tablesStr = $params['tables'];
		$fieldsStr = $params['fields'];
		$whereStr = $params['where'];
		$joinOnStr = $params['join_on'];
		$orderByStr = $params['order_by'];
		$groupByStr = $params['group_by'];
		$havingStr = $params['having'];
		$limitStr = $params['limit'];
		$offsetStr = $params['offset'];

		if ( $tablesStr == '' ) {
			$this->dieWithError( 'The tables must be specified', 'param_substr' );
		}

		$sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );

		foreach ( $sqlQuery->mFieldStringAliases as $fieldStr => $fieldStrAlias ) {
			if ( $fieldStrAlias[0] == '_' ) {
				throw new MWException( "Error: Field alias \"$fieldStrAlias\" starts with an underscore (_). This is not allowed in Cargo API queries." );
			}
		}

		try {
			$queryResults = $sqlQuery->run();
		} catch ( Exception $e ) {
			$this->dieWithError( $e, 'db_error' );
		}

		// Format data as the API requires it.
		$formattedData = [];
		foreach ( $queryResults as $row ) {
			$formattedData[] = [ 'title' => $row ];
		}

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $formattedData, 'p' );
		$result->addValue( null, $this->getModuleName(), $formattedData );
	}

	protected function getAllowedParams() {
		return [
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 50,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'tables' => null,
			'fields' => null,
			'where' => null,
			'join_on' => null,
			'group_by' => null,
			'having' => null,
			'order_by' => null,
			'offset' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_DFLT => 0,
				ApiBase::PARAM_MIN => 0,
			],
		];
	}

	protected function getParamDescription() {
		return [
			'tables' => 'The Cargo database table or tables on which to search',
			'fields' => 'The table field(s) to retrieve',
			'where' => 'The conditions for the query, corresponding to an SQL WHERE clause',
			'join_on' => 'Conditions for joining multiple tables, corresponding to an SQL JOIN ON clause',
			'order_by' => 'The order of results, corresponding to an SQL ORDER BY clause',
			'group_by' => 'Field(s) on which to group results, corresponding to an SQL GROUP BY clause',
			'having' => 'Conditions for grouped values, corresponding to an SQL HAVING clause',
			'limit' => 'A limit on the number of results returned',
			'offset' => 'The query offset number',
		];
	}

	protected function getDescription() {
		return 'An SQL-style query used for data tables, provided by the Cargo extension '
			. '(https://www.mediawiki.org/Extension:Cargo)';
	}

	protected function getExamples() {
		return [
			'api.php?action=cargoquery&tables=Items&fields=_pageName=Item,Source,Date=Publication_date'
			. '&where=Source+LIKE+\'%New%\'&order_by=Date&limit=100'
		];
	}

}
