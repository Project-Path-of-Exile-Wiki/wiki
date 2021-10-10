<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusSearch;
use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchContext;

/**
 * @covers \CirrusSearch\Query\QueryBuilderTraits
 */
class QueryBuilderTraitsTest extends CirrusTestCase {

	public function testTitleLength() {
		$context = new SearchContext( new HashSearchConfig( [] ), null, null, null, null,
			$this->createCirrusSearchHookRunner() );
		$qb = new class {
			use QueryBuilderTraits;
		};
		$term = 'some example query';
		$this->assertTrue( $qb->checkTitleSearchRequestLength( $term, $context ) );
		$this->assertTrue( $context->areResultsPossible() );
		$this->assertEmpty( $context->getWarnings() );

		$term .= str_repeat( 'a', CirrusSearch::MAX_TITLE_SEARCH );
		$this->assertFalse( $qb->checkTitleSearchRequestLength( $term, $context ) );
		$this->assertFalse( $context->areResultsPossible() );
		$this->assertNotEmpty( $context->getWarnings() );
	}
}
