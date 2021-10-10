<?php

namespace CirrusSearch\Wikimedia;

use CirrusSearch\HashSearchConfig;
use CirrusSearch\Query\ArticleTopicFeature;

/**
 * @covers \CirrusSearch\Wikimedia\WeightedTagsHooks
 */
class WeightedTagsHooksTest extends \MediaWikiUnitTestCase {
	public function testConfigureWeightedTagsSimilarity() {
		$sim = [];
		$maxScore = 17389;
		$config = new \HashConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::BUILD_OPTION => true,
					WeightedTagsHooks::MAX_SCORE_OPTION => $maxScore,
					]
				]
		] );
		WeightedTagsHooks::configureWeightedTagsSimilarity( $sim, $config );
		$this->assertArrayHasKey( WeightedTagsHooks::FIELD_SIMILARITY, $sim );
		$this->assertStringContainsString( $maxScore,
			$sim[WeightedTagsHooks::FIELD_SIMILARITY]['script']['source'] );
	}

	public function testConfigureWeightedTagsSimilarityDisabled() {
		$config = new \HashConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::BUILD_OPTION => false,
				]
			]
		] );
		$sim = [];
		WeightedTagsHooks::configureWeightedTagsSimilarity( $sim, $config );
		$this->assertSame( [], $sim );
	}

	public function testConfigureWeightedTagsFieldMapping() {
		$config = new \HashConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::BUILD_OPTION => true,
				]
			]
		] );
		$searchEngine = $this->createMock( \SearchEngine::class );
		/**
		 * @var \SearchIndexField $fields
		 */
		$fields = [];
		WeightedTagsHooks::configureWeightedTagsFieldMapping( $fields, $config );
		$this->assertArrayHasKey( WeightedTagsHooks::FIELD_NAME, $fields );
		$field = $fields[WeightedTagsHooks::FIELD_NAME];
		$this->assertInstanceOf( WeightedTags::class, $field );
		$mapping = $field->getMapping( $searchEngine );
		$this->assertSame( 'text', $mapping['type'] );
		$this->assertSame( WeightedTagsHooks::FIELD_SEARCH_ANALYZER, $mapping['search_analyzer'] );
		$this->assertSame( WeightedTagsHooks::FIELD_INDEX_ANALYZER, $mapping['analyzer'] );
		$this->assertSame( WeightedTagsHooks::FIELD_SIMILARITY, $mapping['similarity'] );
	}

	public function testConfigureWeightedTagsFieldMappingDisabled() {
		$config = new \HashConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::BUILD_OPTION => false,
				]
			]
		] );
		$fields = [];
		WeightedTagsHooks::configureWeightedTagsFieldMapping( $fields, $config );
		$this->assertSame( [], $fields );
	}

	public function testConfigureWeightedTagsFieldAnalysis() {
		$maxScore = 41755;
		$config = new \HashConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::BUILD_OPTION => true,
					WeightedTagsHooks::MAX_SCORE_OPTION => $maxScore,
				]
			]
		] );
		$analysisConfig = [];
		WeightedTagsHooks::configureWeightedTagsFieldAnalysis( $analysisConfig, $config );
		$this->assertArrayHasKey( 'analyzer', $analysisConfig );
		$this->assertArrayHasKey( 'filter', $analysisConfig );
		$analyzers = $analysisConfig['analyzer'];
		$filters = $analysisConfig['filter'];
		$this->assertArrayHasKey( WeightedTagsHooks::FIELD_INDEX_ANALYZER, $analyzers );
		$this->assertArrayHasKey( 'weighted_tags_term_freq', $filters );
		$this->assertSame( $maxScore, $filters['weighted_tags_term_freq']['max_tf'] );
	}

	public function testConfigureWeightedTagsFieldAnalysisDisabled() {
		$config = new \HashConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::BUILD_OPTION => false,
				]
			]
		] );
		$analysisConfig = [];
		WeightedTagsHooks::configureWeightedTagsFieldAnalysis( $analysisConfig, $config );
		$this->assertSame( [], $analysisConfig );
	}

	public function testOnCirrusSearchAddQueryFeatures() {
		$config = new HashSearchConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::USE_OPTION => false,
				],
			],
		] );
		$extraFeatures = [];
		WeightedTagsHooks::onCirrusSearchAddQueryFeatures( $config, $extraFeatures );
		$this->assertEmpty( $extraFeatures );

		$config = new HashSearchConfig( [
			WeightedTagsHooks::WMF_EXTRA_FEATURES => [
				WeightedTagsHooks::CONFIG_OPTIONS => [
					WeightedTagsHooks::USE_OPTION => true,
				],
			],
		] );
		WeightedTagsHooks::onCirrusSearchAddQueryFeatures( $config, $extraFeatures );
		$this->assertNotEmpty( $extraFeatures );
		$this->assertInstanceOf( ArticleTopicFeature::class, $extraFeatures[0] );
	}
}
