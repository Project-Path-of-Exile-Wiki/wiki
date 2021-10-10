<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 *
 * Abstract class for formats that run the query or queries themselves,
 * instead of getting the results passed in to them.
 */

abstract class CargoDeferredFormat extends CargoDisplayFormat {
	public static function isDeferred() {
		return true;
	}

	/**
	 * Turns one or more Cargo SQL query objects into a set of URL
	 * query string parameters.
	 *
	 * @param array $sqlQueries
	 * @return array
	 */
	public function sqlQueriesToQueryParams( $sqlQueries ) {
		$queryParams = [
			'tables' => [],
			'join on' => [],
			'fields' => [],
			'where' => [],
		];
		if ( count( $sqlQueries ) == 0 ) {
			return null;
		} elseif ( count( $sqlQueries ) == 1 ) {
			$sqlQuery = $sqlQueries[0];
			$queryParams['tables'] = $sqlQuery->mTablesStr;
			if ( $sqlQuery->mJoinOnStr != '' ) {
				$queryParams['join on'] = $sqlQuery->mJoinOnStr;
			}
			if ( $sqlQuery->mFieldsStr != '' ) {
				$queryParams['fields'] = $sqlQuery->mFieldsStr;
			}
			if ( $sqlQuery->mWhereStr != '' ) {
				$queryParams['where'] = $sqlQuery->mOrigWhereStr;
			}
			if ( $sqlQuery->mGroupByStr != '' ) {
				$queryParams['group by'] = $sqlQuery->mOrigGroupByStr;
			}
			if ( $sqlQuery->mHavingStr != '' ) {
				$queryParams['having'] = $sqlQuery->mHavingStr;
			}
			$queryParams['order by'] = implode( ',', $sqlQuery->mOrderBy );
			if ( $sqlQuery->mQueryLimit != '' ) {
				$queryParams['limit'] = $sqlQuery->mQueryLimit;
			}
		} else {
			foreach ( $sqlQueries as $i => $sqlQuery ) {
				$queryParams['tables'][] = $sqlQuery->mTablesStr;
				$queryParams['join on'][] = $sqlQuery->mJoinOnStr;
				$queryParams['fields'][] = $sqlQuery->mFieldsStr;
				$queryParams['where'][] = $sqlQuery->mOrigWhereStr;
				$queryParams['group by'][] = $sqlQuery->mOrigGroupByStr;
				$queryParams['order by'][] = implode( ',', $sqlQuery->mOrderBy );
				$queryParams['limit'][] = $sqlQuery->mQueryLimit;
			}
		}

		return $queryParams;
	}

	/**
	 * Must be defined for any class that inherits from this one.
	 */
	abstract public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null );

}
