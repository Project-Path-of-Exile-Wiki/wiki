<?php
/**
 * CargoQuery - class for the #cargo_query parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoQuery {

	/**
	 * Handles the #cargo_query parser function - calls a query on the
	 * Cargo data stored in the database.
	 *
	 * @param Parser &$parser
	 * @return string
	 */
	public static function run( &$parser ) {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tablesStr = null;
		$fieldsStr = null;
		$whereStr = null;
		$joinOnStr = null;
		$groupByStr = null;
		$havingStr = null;
		$orderByStr = null;
		$limitStr = null;
		$offsetStr = null;
		$noHTML = false;
		$format = 'auto'; // default
		$displayParams = [];

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) == 1 ) {
				if ( $param == 'no html' ) {
					$noHTML = true;
				}
				continue;
			}
			if ( count( $parts ) > 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == 'tables' || $key == 'table' ) {
				$tablesStr = $value;
			} elseif ( $key == 'fields' ) {
				$fieldsStr = $value;
			} elseif ( $key == 'where' ) {
				$whereStr = $value;
			} elseif ( $key == 'join on' ) {
				$joinOnStr = $value;
			} elseif ( $key == 'group by' ) {
				$groupByStr = $value;
			} elseif ( $key == 'having' ) {
				$havingStr = $value;
			} elseif ( $key == 'order by' ) {
				$orderByStr = $value;
			} elseif ( $key == 'limit' ) {
				$limitStr = $value;
			} elseif ( $key == 'offset' ) {
				$offsetStr = $value;
			} elseif ( $key == 'format' ) {
				$format = $value;
			} else {
				// We'll assume it's going to the formatter.
				$displayParams[$key] = $value;
			}
		}
		// Special handling.
		if ( $format == 'dynamic table' && $orderByStr != null ) {
			$displayParams['order by'] = $orderByStr;
		}

		try {
			$sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}
		$queryDisplayer = CargoQueryDisplayer::newFromSQLQuery( $sqlQuery );
		$queryDisplayer->mFormat = $format;
		$queryDisplayer->mDisplayParams = $displayParams;
		$queryDisplayer->mParser = $parser;
		$formatter = $queryDisplayer->getFormatter( $parser->getOutput(), $parser );

		// Let the format run the query itself, if it wants to.
		if ( $formatter->isDeferred() ) {
			// @TODO - fix this inefficiency. Right now a
			// CargoSQLQuery object is constructed three times for
			// deferred formats: the first two times here and the
			// 3rd by Special:CargoExport. It's the first
			// construction that involves a bunch of text
			// processing, and is unneeded.
			// However, this first CargoSQLQuery is passed to
			// the CargoQueryDisplayer, which in turn uses it
			// to figure out the formatting class, so that we
			// know whether it is a deferred class or not. The
			// class is based in part on the set of fields in the
			// query, so in theory (though not in practice),
			// whether or not it's deferred could depend on the
			// fields in the query, making the first 'Query
			// necessary. There has to be some better way, though.
			$sqlQuery = CargoSQLQuery::newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
				$groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr );
			$text = $formatter->queryAndDisplay( [ $sqlQuery ], $displayParams );
			return [ $text, 'noparse' => true, 'isHTML' => true ];
		}

		// If the query limit was set to 0, no need to run the query -
		// all we need to do is show the "more results" link, then exit.
		if ( $sqlQuery->mQueryLimit == 0 ) {
			$text = $queryDisplayer->viewMoreResultsLink( true );
			return [ $text, 'noparse' => true, 'isHTML' => true ];
		}

		try {
			$queryResults = $sqlQuery->run();
		} catch ( Exception $e ) {
			return CargoUtils::formatError( $e->getMessage() );
		}

		// Finally, do the display.
		$text = $queryDisplayer->displayQueryResults( $formatter, $queryResults );
		// If there are no results, then - given that we already know
		// that the limit was not set to 0 - we just need to display an
		// automatic message, so there's no need for special parsing.
		if ( count( $queryResults ) == 0 ) {
			return $text;
		}

		// The 'template' format gets special parsing, because
		// it can be used to display a larger component, like a table,
		// which means that everything needs to be parsed together
		// instead of one instance at a time. Also, the template will
		// contain wikitext, not HTML.
		$displayHTML = ( !$noHTML && $format != 'template' );

		// If there are (seemingly) more results than what we showed,
		// show a "View more" link that links to Special:ViewData.
		if ( count( $queryResults ) == $sqlQuery->mQueryLimit ) {
			$text .= $queryDisplayer->viewMoreResultsLink( $displayHTML );
		}

		if ( $displayHTML ) {
			return [ $text, 'noparse' => true, 'isHTML' => true ];
		} else {
			return [ $text, 'noparse' => false ];
		}
	}

}
