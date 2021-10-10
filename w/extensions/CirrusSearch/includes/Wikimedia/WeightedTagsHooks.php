<?php

namespace CirrusSearch\Wikimedia;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\SearchConfig;
use Config;
use MediaWiki\MediaWikiServices;
use SearchEngine;

/**
 * Functionality related to the (Wikimedia-specific) weighted_tags search feature.
 * @package CirrusSearch\Wikimedia
 * @see ArticleTopicFeature
 */
class WeightedTagsHooks {
	public const FIELD_NAME = 'weighted_tags';
	public const FIELD_SIMILARITY = 'weighted_tags_similarity';
	public const FIELD_INDEX_ANALYZER = 'weighted_tags';
	public const FIELD_SEARCH_ANALYZER = 'keyword';
	public const WMF_EXTRA_FEATURES = 'CirrusSearchWMFExtraFeatures';
	public const CONFIG_OPTIONS = 'weighted_tags';
	public const BUILD_OPTION = 'build';
	public const USE_OPTION = 'use';
	public const MAX_SCORE_OPTION = 'max_score';

	/**
	 * Configure the similarity needed for the article topics field
	 * @param array &$similarity similarity settings to update
	 * @see https://www.mediawiki.org/wiki/Extension:CirrusSearch/Hooks/CirrusSearchSimilarityConfig
	 */
	public static function onCirrusSearchSimilarityConfig( array &$similarity ) {
		self::configureWeightedTagsSimilarity( $similarity,
			MediaWikiServices::getInstance()->getMainConfig() );
	}

	/**
	 * Visible for testing.
	 * @param array &$similarity similarity settings to update
	 * @param Config $config current configuration
	 */
	public static function configureWeightedTagsSimilarity(
		array &$similarity,
		Config $config
	) {
		if ( !self::canBuild( $config ) ) {
			return;
		}
		$maxScore = self::maxScore( $config );
		$similarity[self::FIELD_SIMILARITY] = [
			'type' => 'scripted',
			// no weight=>' script we do not want doc independent weighing
			'script' => [
				// apply boost close to docFreq to force int->float conversion
				'source' => "return (doc.freq*query.boost)/$maxScore;"
			]
		];
	}

	/**
	 * Define mapping for the weighted_tags field.
	 * @param array &$fields array of field definitions to update
	 * @param SearchEngine $engine the search engine requesting field definitions
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SearchIndexFields
	 */
	public static function onSearchIndexFields( array &$fields, SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			return;
		}
		self::configureWeightedTagsFieldMapping( $fields,
			MediaWikiServices::getInstance()->getMainConfig() );
	}

	/**
	 * Visible for testing
	 * @param \SearchIndexField[] &$fields array of field definitions to update
	 * @param Config $config the wiki configuration
	 */
	public static function configureWeightedTagsFieldMapping(
		array &$fields,
		Config $config
	) {
		if ( !self::canBuild( $config ) ) {
			return;
		}

		$fields[self::FIELD_NAME] = new WeightedTags(
			self::FIELD_NAME,
			self::FIELD_NAME,
			self::FIELD_INDEX_ANALYZER,
			self::FIELD_SEARCH_ANALYZER,
			self::FIELD_SIMILARITY
		);
	}

	/**
	 * Configure default analyzer for the weighted_tags field.
	 * @param array &$config analysis settings to update
	 * @param AnalysisConfigBuilder $analysisConfigBuilder unneeded
	 * @see https://www.mediawiki.org/wiki/Extension:CirrusSearch/Hooks/CirrusSearchAnalysisConfig
	 */
	public static function onCirrusSearchAnalysisConfig( array &$config, AnalysisConfigBuilder $analysisConfigBuilder ) {
		self::configureWeightedTagsFieldAnalysis( $config,
			MediaWikiServices::getInstance()->getMainConfig() );
	}

	/**
	 * Make weighted_tags search features available
	 * @param SearchConfig $config
	 * @param array &$extraFeatures Array holding KeywordFeature objects
	 * @see ArticleTopicFeature
	 */
	public static function onCirrusSearchAddQueryFeatures( SearchConfig $config, array &$extraFeatures ) {
		if ( self::canUse( $config ) ) {
			// articletopic keyword, matches by ORES topic scores
			$extraFeatures[] = new ArticleTopicFeature();
		}
	}

	/**
	 * Visible only for testing
	 * @param array &$analysisConfig panalysis settings to update
	 * @param Config $config the wiki configuration
	 * @internal
	 */
	public static function configureWeightedTagsFieldAnalysis(
		array &$analysisConfig,
		Config $config
	) {
		if ( !self::canBuild( $config ) ) {
			return;
		}
		$maxScore = self::maxScore( $config );
		$analysisConfig['analyzer'][self::FIELD_INDEX_ANALYZER] = [
			'type' => 'custom',
			'tokenizer' => 'keyword',
			'filter' => [
				'weighted_tags_term_freq',
			]
		];
		$analysisConfig['filter']['weighted_tags_term_freq'] = [
			'type' => 'term_freq',
			// must be a char that never appears in the topic names/ids
			'split_char' => '|',
			// max score (clamped), we assume that orig_score * 1000
			'max_tf' => $maxScore,
		];
	}

	/**
	 * Check whether weighted_tags data should be processed.
	 * @param Config $config
	 * @return bool
	 */
	private static function canBuild( Config $config ): bool {
		$extraFeatures = $config->get( self::WMF_EXTRA_FEATURES );
		$weightedTagsOptions = $extraFeatures[self::CONFIG_OPTIONS] ?? [];
		return (bool)( $weightedTagsOptions[self::BUILD_OPTION] ?? false );
	}

	/**
	 * Check whether weighted_tags data is available for searching.
	 * @param Config $config
	 * @return bool
	 */
	private static function canUse( Config $config ): bool {
		$extraFeatures = $config->get( self::WMF_EXTRA_FEATURES );
		$weightedTagsOptions = $extraFeatures[self::CONFIG_OPTIONS] ?? [];
		return (bool)( $weightedTagsOptions[self::USE_OPTION] ?? false );
	}

	private static function maxScore( Config $config ): int {
		$extraFeatures = $config->get( self::WMF_EXTRA_FEATURES );
		$weightedTagsOptions = $extraFeatures[self::CONFIG_OPTIONS] ?? [];
		return (int)( $weightedTagsOptions[self::MAX_SCORE_OPTION] ?? 1000 );
	}
}
