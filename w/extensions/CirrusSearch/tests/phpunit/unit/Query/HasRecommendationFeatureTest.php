<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;

/**
 * @covers \CirrusSearch\Query\HasRecommendationFeature
 * @group CirrusSearch
 */
class HasRecommendationFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function provideQueries() {
		$tooMany = array_map(
			function ( $l ) {
				return (string)$l;
			},
			range( 1, HasRecommendationFeature::QUERY_LIMIT + 5 )
		);
		$actualrecFlags = array_slice( $tooMany, 0, HasRecommendationFeature::QUERY_LIMIT );
		return [
			'simple' => [
				'hasrecommendation:image',
				[ 'recommendationflags' => [ 'image' ] ],
				[ 'bool' => [ 'should' => [
					[ 'match' => [ 'ores_articletopics' => [ 'query' => 'recommendation.image/exists' ] ] ],
					[ 'match' => [ 'weighted_tags' => [ 'query' => 'recommendation.image/exists' ] ] ],
				] ] ],
				[]
			],
			'multiple' => [
				'hasrecommendation:link|image',
				[ 'recommendationflags' => [ 'link', 'image' ] ],
				[ 'bool' => [ 'should' => [
					[ 'match' => [ 'ores_articletopics' => [ 'query' => 'recommendation.link/exists' ] ] ],
					[ 'match' => [ 'weighted_tags' => [ 'query' => 'recommendation.link/exists' ] ] ],
					[ 'match' => [ 'ores_articletopics' => [ 'query' => 'recommendation.image/exists' ] ] ],
					[ 'match' => [ 'weighted_tags' => [ 'query' => 'recommendation.image/exists' ] ] ],
				] ] ],
				[]
			],
			'too many' => [
				'hasrecommendation:' . implode( '|', $tooMany ),
				[ 'recommendationflags' => $actualrecFlags ],
				[ 'bool' => [ 'should' => array_merge( ...array_map(
					function ( $l ) {
						return [
							[ 'match' => [ 'ores_articletopics' => [ 'query' => "recommendation." . $l . '/exists' ] ] ],
							[ 'match' => [ 'weighted_tags' => [ 'query' => "recommendation." . $l . '/exists' ] ] ],
						];
					},
					range( 1, HasRecommendationFeature::QUERY_LIMIT )
				) ) ] ],
				[ [ 'cirrussearch-feature-too-many-conditions', 'hasrecommendation',
					HasRecommendationFeature::QUERY_LIMIT ] ]
			],
		];
	}

	/**
	 * @dataProvider provideQueries()
	 * @param string $term
	 * @param array $expected
	 * @param array $filter
	 * @param array $warnings
	 */
	public function testApply( $term, $expected, array $filter, $warnings ) {
		$feature = new HasRecommendationFeature();
		$this->assertParsedValue( $feature, $term, $expected, $warnings );
		$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		$this->assertExpandedData( $feature, $term, [], [] );
		$this->assertWarnings( $feature, $warnings, $term );
		$this->assertFilter( $feature, $term, $filter, $warnings );
	}
}
