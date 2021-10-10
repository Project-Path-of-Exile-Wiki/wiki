<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Search\SearchContext;

/**
 * Various utility functions that can be shared across cirrus query builders
 */
trait QueryBuilderTraits {
	/**
	 * Short circuits query execution with zero results when
	 * the search is longer than possible. Query builders may
	 * short circuit themselves based on the return value.
	 *
	 * @param string $term Term being searched for
	 * @param SearchContext $searchContext Context to short circuit
	 * @return bool True when $term is an acceptable length.
	 */
	public function checkTitleSearchRequestLength( $term, SearchContext $searchContext ) {
		$requestLength = mb_strlen( $term );
		if ( $requestLength > CirrusSearch::MAX_TITLE_SEARCH ) {
			$searchContext->setResultsPossible( false );
			$searchContext->addWarning(
				'cirrussearch-query-too-long',
				$requestLength,
				CirrusSearch::MAX_TITLE_SEARCH
			);
			return false;
		} else {
			return true;
		}
	}
}
