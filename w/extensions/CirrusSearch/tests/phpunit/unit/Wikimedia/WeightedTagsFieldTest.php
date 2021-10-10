<?php

namespace CirrusSearch\Wikimedia;

/**
 * @covers \CirrusSearch\Wikimedia\WeightedTags
 */
class WeightedTagsFieldTest extends \MediaWikiUnitTestCase {
	public function testField() {
		$searchEngine = $this->createMock( \SearchEngine::class );
		$indexAnalyzer = 'indexAnalyzer';
		$searchAnalyzer = 'searchAnalyzer';
		$similarity = 'sim';
		$fieldName = 'test';
		$typeName = 'unused';
		$field = new WeightedTags( 'test', 'unused', $indexAnalyzer,
			$searchAnalyzer, $similarity );
		$mapping = $field->getMapping( $searchEngine );
		$this->assertSame( [
			'type' => 'text',
			'analyzer' => $indexAnalyzer,
			'search_analyzer' => $searchAnalyzer,
			'index_options' => 'freqs',
			'norms' => false,
			'similarity' => $similarity,
		], $mapping );
		$this->assertSame( $fieldName, $field->getName() );
		$this->assertSame( $typeName, $field->getIndexType() );
	}
}
