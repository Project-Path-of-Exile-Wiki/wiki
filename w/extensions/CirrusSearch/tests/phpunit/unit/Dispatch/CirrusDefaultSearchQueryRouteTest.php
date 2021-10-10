<?php

namespace CirrusSearch\Dispatch;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\SearchQuery;

/**
 * @covers \CirrusSearch\Dispatch\CirrusDefaultSearchQueryRoute
 */
class CirrusDefaultSearchQueryRouteTest extends CirrusTestCase {
	public function testSearchTextDefaultRoute() {
		$route = CirrusDefaultSearchQueryRoute::searchTextDefaultRoute();
		$score = $route->score( $this->getNewFTSearchQueryBuilder( new HashSearchConfig( [] ), "foo" )->build() );
		$this->assertEquals( $score, SearchQueryDispatchService::CIRRUS_DEFAULTS_SCORE );
		$this->assertEquals( SearchQuery::SEARCH_TEXT, $route->getSearchEngineEntryPoint() );
		$this->assertEquals( SearchProfileService::CONTEXT_DEFAULT, $route->getProfileContext() );
	}
}
