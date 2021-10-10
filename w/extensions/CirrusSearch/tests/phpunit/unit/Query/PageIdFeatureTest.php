<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;

/**
 * @covers \CirrusSearch\Query\PageIdFeature
 * @group CirrusSearch
 */
class PageIdFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function parseProvider() {
		return [
			'basic usage' => [
				[ 'ids' => [
					'values' => [ 1, 2, 3 ],
				] ],
				[ 'pageids' => [ 1, 2, 3 ] ],
				'pageid:1|2|3',
			],
			'invalid id' => [
				[ 'ids' => [
					'values' => [ 1, 3, 5 ],
				] ],
				[ 'pageids' => [ 1, 3, 5 ] ],
				'pageid:1|x|3|y|5',
				[ [
					'cirrussearch-feature-pageid-invalid-id',
					[ 'list' => [ 'x', 'y' ], 'type' => 'comma' ],
					2,
				] ]
			],
			'no valid ids' => [
				null,
				[ 'pageids' => [] ],
				'pageid:a|b|c',
				[ [
					'cirrussearch-feature-pageid-invalid-id',
					[ 'list' => [ 'a', 'b', 'c' ], 'type' => 'comma' ],
					3,
				] ]
			],
		];
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParse(
		?array $expected, array $expectedParsedValue, $term, $expectedWarnings = []
	) {
		$feature = new PageIdFeature();
		$this->assertParsedValue( $feature, $term, $expectedParsedValue, $expectedWarnings );
		$this->assertExpandedData( $feature, $term, [], [] );
		$this->assertFilter( $feature, $term, $expected, $expectedWarnings );
	}

	public function testInvalidIds() {
		$feature = new PageIdFeature();
		$expectedParsedValue = [ 'pageids' => [ 1, 2, 3 ] ];
		$expectedWarnings = [ [
			'cirrussearch-feature-pageid-invalid-id',
			[ 'list' => [ 'duck', 'goose' ], 'type' => 'comma' ],
			2,
		] ];
		$this->assertParsedValue( $feature, 'pageid:1|2|duck|3|goose', $expectedParsedValue,
			$expectedWarnings );
	}

	public function testTooManyyIds() {
		$feature = new PageIdFeature();
		$ids = range( 1, PageIdFeature::MAX_VALUES + 1 );
		$parsedIds = range( 1, PageIdFeature::MAX_VALUES );
		$expectedParsedValue = [ 'pageids' => $parsedIds ];
		$expectedWarnings = [ [
			'cirrussearch-feature-too-many-conditions',
			'pageid',
			PageIdFeature::MAX_VALUES,
		] ];
		$this->assertParsedValue( $feature, 'pageid:' . implode( '|', $ids ),
			$expectedParsedValue, $expectedWarnings );
	}
}
