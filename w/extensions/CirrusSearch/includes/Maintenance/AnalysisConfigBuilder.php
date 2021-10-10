<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusSearch;
use CirrusSearch\CirrusSearchHookRunner;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\SearchConfig;
use MediaWiki\MediaWikiServices;

/**
 * Builds elasticsearch analysis config arrays.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class AnalysisConfigBuilder {
	/**
	 * Version number for the core analysis. Increment the major
	 * version when the analysis changes in an incompatible way,
	 * and change the minor version when it changes but isn't
	 * incompatible.
	 *
	 * You may also need to increment MetaStoreIndex::METASTORE_MAJOR_VERSION
	 * manually as well.
	 */
	public const VERSION = '0.12';

	/**
	 * Maximum number of characters allowed in keyword terms.
	 */
	private const KEYWORD_IGNORE_ABOVE = 5000;

	/**
	 * @var bool is the icu plugin available?
	 */
	private $icu;

	/**
	 * @var array Similarity algo (tf/idf, bm25, etc) configuration
	 */
	private $similarity;

	/**
	 * @var SearchConfig cirrus config
	 */
	protected $config;
	/**
	 * @var string[]
	 */
	private $plugins;

	/**
	 * @var string
	 */
	protected $defaultLanguage;

	/**
	 * @var CirrusSearchHookRunner
	 */
	private $cirrusSearchHookRunner;

	/**
	 * @param string $langCode The language code to build config for
	 * @param string[] $plugins list of plugins installed in Elasticsearch
	 * @param SearchConfig|null $config
	 * @param CirrusSearchHookRunner|null $cirrusSearchHookRunner
	 */
	public function __construct(
		$langCode,
		array $plugins,
		SearchConfig $config = null,
		CirrusSearchHookRunner $cirrusSearchHookRunner = null
	) {
		$this->defaultLanguage = $langCode;
		$this->plugins = $plugins;
		foreach ( $this->elasticsearchLanguageAnalyzersFromPlugins as $pluginSpec => $extra ) {
			$pluginsPresent = 1;
			$pluginList = explode( ',', $pluginSpec );
			foreach ( $pluginList as $plugin ) {
				if ( !in_array( $plugin, $plugins ) ) {
					$pluginsPresent = 0;
					break;
				}
			}
			if ( $pluginsPresent ) {
				$this->elasticsearchLanguageAnalyzers = array_merge( $this->elasticsearchLanguageAnalyzers, $extra );
			}
		}
		$this->icu = in_array( 'analysis-icu', $plugins );
		if ( $config === null ) {
			$config = MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'CirrusSearch' );
		}
		$similarity = $config->getProfileService()->loadProfile( SearchProfileService::SIMILARITY );
		if ( !array_key_exists( 'similarity', $similarity ) ) {
			$similarity['similarity'] = [];
		}
		$this->cirrusSearchHookRunner = $cirrusSearchHookRunner ?: new CirrusSearchHookRunner(
			MediaWikiServices::getInstance()->getHookContainer() );
		$this->cirrusSearchHookRunner->onCirrusSearchSimilarityConfig( $similarity['similarity'] );
		$this->similarity = $similarity;

		$this->config = $config;
	}

	/**
	 * Determine if ascii folding should be used
	 * @param string $language Config language
	 * @return bool true if icu folding should be enabled
	 */
	public function shouldActivateIcuFolding( $language ) {
		if ( !$this->icu || !in_array( 'extra', $this->plugins ) ) {
			// ICU folding requires the icu plugin and the extra plugin
			return false;
		}
		$in_config = $this->config->get( 'CirrusSearchUseIcuFolding' );
		// BC code, this config var was originally a simple boolean
		if ( $in_config === true ) {
			$in_config = 'yes';
		}
		if ( $in_config === false ) {
			$in_config = 'no';
		}
		switch ( $in_config ) {
		case 'yes':
			return true;
		case 'no':
			return false;
		case 'default':
			return $this->languagesWithIcuFolding[$language] ?? false;
		default:
			return false;
		}
	}

	/**
	 * Determine if the icu tokenizer can be enabled
	 * @param string $language Config language
	 * @return bool
	 */
	public function shouldActivateIcuTokenization( $language ) {
		if ( !$this->icu ) {
			// requires the icu plugin
			return false;
		}
		$in_config = $this->config->get( 'CirrusSearchUseIcuTokenizer' );
		switch ( $in_config ) {
		case 'yes':
			return true;
		case 'no':
			return false;
		case 'default':
			return $this->languagesWithIcuTokenization[$language] ?? false;
		default:
			return false;
		}
	}

	/**
	 * Build the analysis config.
	 *
	 * @param string|null $language Config language
	 * @return array the analysis config
	 */
	public function buildConfig( $language = null ) {
		if ( $language === null ) {
			$language = $this->defaultLanguage;
		}
		$config = $this->customize( $this->defaults( $language ), $language );
		$this->cirrusSearchHookRunner->onCirrusSearchAnalysisConfig( $config, $this );
		$config = $this->enableHomoglyphPlugin( $config, $language );
		if ( $this->shouldActivateIcuTokenization( $language ) ) {
			$config = $this->enableICUTokenizer( $config );
		}
		if ( $this->shouldActivateIcuFolding( $language ) ) {
			$config = $this->enableICUFolding( $config, $language );
		}
		$config = $this->fixAsciiFolding( $config );

		return $config;
	}

	/**
	 * Build the similarity config
	 *
	 * @return array|null the similarity config
	 */
	public function buildSimilarityConfig() {
		return $this->similarity['similarity'] ?? null;
	}

	/**
	 * replace the standard tokenizer with icu_tokenizer
	 * @param mixed[] $config
	 * @return mixed[] update config
	 */
	public function enableICUTokenizer( array $config ) {
		foreach ( $config[ 'analyzer' ] as $name => &$value ) {
			if ( isset( $value[ 'type' ] ) && $value[ 'type' ] != 'custom' ) {
				continue;
			}
			if ( isset( $value[ 'tokenizer' ] ) && $value[ 'tokenizer' ] === 'standard' ) {
				$value[ 'tokenizer' ] = 'icu_tokenizer';
			}
		}
		return $config;
	}

	/**
	 * Activate ICU folding instead of asciifolding
	 * @param mixed[] $config
	 * @param string $language Config language
	 * @return mixed[] update config
	 */
	public function enableICUFolding( array $config, $language ) {
		$unicodeSetFilter = $this->getICUSetFilter( $language );
		$filter = [
			'type' => 'icu_folding',
		];
		if ( !empty( $unicodeSetFilter ) ) {
			$filter[ 'unicodeSetFilter' ] = $unicodeSetFilter;
		}
		$config[ 'filter' ][ 'icu_folding' ] = $filter;

		// Adds a simple nfkc normalizer for cases where
		// we preserve original but the lowercase filter
		// is not used before
		$config[ 'filter' ][ 'icu_nfkc_normalization' ] = [
			'type' => 'icu_normalizer',
			'name' => 'nfkc',
		];

		$newfilters = [];
		foreach ( $config[ 'analyzer' ] as $name => $value ) {
			if ( isset( $value[ 'type' ] ) && $value[ 'type' ] != 'custom' ) {
				continue;
			}
			if ( !isset( $value[ 'filter' ] ) ) {
				continue;
			}
			if ( in_array( 'asciifolding', $value[ 'filter' ] ) ) {
				$newfilters[ $name ] = $this->switchFiltersToICUFolding( $value[ 'filter' ] );
			}
			if ( in_array( 'asciifolding_preserve', $value[ 'filter' ] ) ) {
				$newfilters[ $name ] = $this->switchFiltersToICUFoldingPreserve( $value[ 'filter' ] );
			}
		}

		foreach ( $newfilters as $name => $filters ) {
			$config[ 'analyzer' ][ $name ][ 'filter' ] = $filters;
		}
		// Explicitly enable icu_folding on plain analyzers if it's not
		// already enabled
		foreach ( [ 'plain' ] as $analyzer ) {
			if ( !isset( $config[ 'analyzer' ][ $analyzer ] ) ) {
				continue;
			}
			if ( !isset( $config[ 'analyzer' ][ $analyzer ][ 'filter' ] ) ) {
				$config[ 'analyzer' ][ $analyzer ][ 'filter' ] = [];
			}
			$config[ 'analyzer' ][ $analyzer ][ 'filter' ] =
				$this->switchFiltersToICUFoldingPreserve(
					// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
					$config[ 'analyzer' ][ $analyzer ][ 'filter' ], true );
		}

		return $config;
	}

	/**
	 * Replace occurrence of asciifolding to icu_folding
	 * @param string[] $filters
	 * @return string[] new list of filters
	 */
	private function switchFiltersToICUFolding( array $filters ) {
		array_splice( $filters, array_search( 'asciifolding', $filters ), 1,
			[ 'icu_folding', 'remove_empty' ] );
		return $filters;
	}

	/**
	 * Replace occurrence of asciifolding_preserve with a set
	 * of compatible filters to enable icu_folding
	 * @param string[] $filters
	 * @param bool $append append icu_folding even if asciifolding is not present
	 * @return string[] new list of filters
	 */
	private function switchFiltersToICUFoldingPreserve( array $filters, $append = false ) {
		if ( in_array( 'icu_folding', $filters ) ) {
			// ICU folding already here
			return $filters;
		}
		$ap_idx = array_search( 'asciifolding_preserve', $filters );
		if ( $ap_idx === false && $append ) {
			$ap_idx = count( $filters );
			// fake an asciifolding_preserve so we can
			// reuse code that replaces it
			$filters[] = 'asciifolding_preserve';
		}
		if ( $ap_idx === false ) {
			return $filters;
		}
		// with ICU lowercase is replaced by icu_normalizer/nfkc_cf
		// thus unicode normalization is already done.
		$lc_idx = array_search( 'icu_normalizer', $filters );
		$newfilters = [];
		if ( $lc_idx === false || $lc_idx > $ap_idx ) {
			// If lowercase is not detected before we
			// will have to do some icu normalization
			// this is to prevent preserving "un-normalized"
			// unicode chars.
			$newfilters[] = 'icu_nfkc_normalization';
		}
		$newfilters[] = 'preserve_original_recorder';
		$newfilters[] = 'icu_folding';
		$newfilters[] = 'preserve_original';
		$newfilters[] = 'remove_empty';
		array_splice( $filters, $ap_idx, 1, $newfilters );
		return $filters;
	}

	/**
	 * Return the list of chars to exclude from ICU folding
	 * @param string $language Config language
	 * @return null|string
	 */
	protected function getICUSetFilter( $language ) {
		if ( $this->config->get( 'CirrusSearchICUFoldingUnicodeSetFilter' ) !== null ) {
			return $this->config->get( 'CirrusSearchICUFoldingUnicodeSetFilter' );
		}
		switch ( $language ) {
		/* @todo: complete the default filters per language
		 * For Swedish (sv), see https://www.mediawiki.org/wiki/User:TJones_(WMF)/T160562
		 * For Serbian (sr), see https://www.mediawiki.org/wiki/User:TJones_(WMF)/T183015
		 * For Bosnian (bs), Croatian (hr), and Serbo-Croatian (sh),
		 *   see https://www.mediawiki.org/wiki/User:TJones_(WMF)/T192395
		 * For Esperanto (eo), see https://www.mediawiki.org/wiki/User:TJones_(WMF)/T202173
		 * For Slovak (sk)—which has no folding configured here!—see:
		 *   https://www.mediawiki.org/wiki/User:TJones_(WMF)/T223787
		 */
		case 'bs':
		case 'hr':
		case 'sh':
		case 'sr':
			return '[^ĐđŽžĆćŠšČč]';
		case 'eo':
			return '[^ĈĉĜĝĤĥĴĵŜŝŬŭ]';
		case 'fi':
			return '[^åäöÅÄÖ]';
		case 'ru':
			return '[^йЙ]';
		case 'sv':
			return '[^åäöÅÄÖ]';
		default:
			return null;
		}
	}

	/**
	 * Build an analysis config with sane defaults.
	 *
	 * @param string $language Config language
	 * @return array
	 */
	private function defaults( $language ) {
		$defaults = [
			'analyzer' => [
				'text' => [
					// These defaults are not applied to non-custom
					// analysis chains, i.e., those that use the
					// default language analyzers on 'text'
					'type' => $this->getDefaultTextAnalyzerType( $language ),
					'char_filter' => [ 'word_break_helper' ],
				],
				// text_search is not configured here because it will be copied from text
				'plain' => [
					// Surprisingly, the Lucene docs claim this works for
					// Chinese, Japanese, and Thai as well.
					// The difference between this and the 'standard'
					// analyzer is the lack of english stop words.
					'type' => 'custom',
					'char_filter' => [ 'word_break_helper' ],
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase' ],
				],
				'plain_search' => [
					// In accent squashing languages this will not contain accent
					// squashing to allow searches with accents to only find accents
					// and searches without accents to find both.
					'type' => 'custom',
					'char_filter' => [ 'word_break_helper' ],
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase' ],
				],
				// Used by ShortTextIndexField
				'short_text' => [
					'type' => 'custom',
					'tokenizer' => 'whitespace',
					'filter' => [ 'lowercase', 'aggressive_splitting', 'asciifolding_preserve' ],
				],
				'short_text_search' => [
					'type' => 'custom',
					'tokenizer' => 'whitespace',
					'filter' => [ 'lowercase', 'aggressive_splitting' ],
				],
				'source_text_plain' => [
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase' ],
					'char_filter' => [ 'word_break_helper_source_text' ],
				],
				'source_text_plain_search' => [
					'type' => 'custom',
					'char_filter' => [ 'word_break_helper_source_text' ],
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase' ],
				],
				'suggest' => [
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase', 'suggest_shingle' ],
				],
				'suggest_reverse' => [
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase', 'suggest_shingle', 'reverse' ],
				],
				'token_reverse' => [
					'type' => 'custom',
					'tokenizer' => 'no_splitting',
					'filter' => [ 'reverse' ]
				],
				'near_match' => [
					'type' => 'custom',
					'char_filter' => [ 'near_space_flattener' ],
					'tokenizer' => 'no_splitting',
					'filter' => [ 'lowercase' ],
				],
				'near_match_asciifolding' => [
					'type' => 'custom',
					'char_filter' => [ 'near_space_flattener' ],
					'tokenizer' => 'no_splitting',
					'filter' => [ 'truncate_keyword', 'lowercase', 'asciifolding' ],
				],
				'prefix' => [
					'type' => 'custom',
					'char_filter' => [ 'near_space_flattener' ],
					'tokenizer' => 'prefix',
					'filter' => [ 'lowercase' ],
				],
				'prefix_asciifolding' => [
					'type' => 'custom',
					'char_filter' => [ 'near_space_flattener' ],
					'tokenizer' => 'prefix',
					'filter' => [ 'lowercase', 'asciifolding' ],
				],
				'word_prefix' => [
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase', 'prefix_ngram_filter' ],
				],
				'keyword' => [
					'type' => 'custom',
					'tokenizer' => 'no_splitting',
					'filter' => [ 'truncate_keyword' ],
				],
				'lowercase_keyword' => [
					'type' => 'custom',
					'tokenizer' => 'no_splitting',
					'filter' => [ 'truncate_keyword', 'lowercase' ],
				],
				'trigram' => [
					'type' => 'custom',
					'tokenizer' => 'trigram',
					'filter' => [ 'lowercase' ],
				],
			],
			'filter' => [
				'suggest_shingle' => [
					'type' => 'shingle',
					'min_shingle_size' => 2,
					'max_shingle_size' => 3,
					'output_unigrams' => true,
				],
				'lowercase' => [
					'type' => 'lowercase',
				],
				'aggressive_splitting' => [
					'type' => 'word_delimiter',
					'stem_english_possessive' => false,
					// 'catenate_words' => true, // Might be useful but causes errors on indexing
					// 'catenate_numbers' => true, // Might be useful but causes errors on indexing
					// 'catenate_all' => true, // Might be useful but causes errors on indexing
					'preserve_original' => false // "wi-fi-555" finds "wi-fi-555".  Not needed because of plain analysis.
				],
				'prefix_ngram_filter' => [
					'type' => 'edgeNGram',
					'max_gram' => CirrusSearch::MAX_TITLE_SEARCH,
				],
				'asciifolding' => [
					'type' => 'asciifolding',
					'preserve_original' => false
				],
				'asciifolding_preserve' => [
					'type' => 'asciifolding',
					'preserve_original' => true
				],
				// The 'keyword' type in ES seems like a hack
				// and doesn't allow normalization (like lowercase)
				// prior to 5.2. Instead we consistently use 'text'
				// and truncate where necessary.
				'truncate_keyword' => [
					'type' => 'truncate',
					'length' => self::KEYWORD_IGNORE_ABOVE,
				],
				'remove_empty' => [
					'type' => 'length',
					'min' => 1,
				],
			],
			'tokenizer' => [
				'prefix' => [
					'type' => 'edgeNGram',
					'max_gram' => CirrusSearch::MAX_TITLE_SEARCH,
				],
				'no_splitting' => [ // Just grab the whole term.
					'type' => 'keyword',
				],
				'trigram' => [
					'type' => 'nGram',
					'min_gram' => 3,
					'max_gram' => 3,
				],
			],
			'char_filter' => [
				// Flattens things that are space like to spaces in the near_match style analyzers
				'near_space_flattener' => [
					'type' => 'mapping',
					'mappings' => [
						"'=>\u0020",       // Useful for finding names
						'\u2019=>\u0020',  // Unicode right single quote
						'\u02BC=>\u0020',  // Unicode modifier letter apostrophe
						'_=>\u0020',       // Mediawiki loves _ and people are used to it but it usually means space
						'-=>\u0020',       // Useful for finding hyphenated names unhyphenated
					],
				],
				// Converts things that don't always count as word breaks into spaces which always
				// count as word breaks.
				'word_break_helper' => [
					'type' => 'mapping',
					'mappings' => [
						'_=>\u0020',
						// These are more useful for code:
						'.=>\u0020',
						'(=>\u0020',
						')=>\u0020',
					],
				],
				'word_break_helper_source_text' => [
					'type' => 'mapping',
					'mappings' => [
						'_=>\u0020',
						// These are more useful for code:
						'.=>\u0020',
						'(=>\u0020',
						')=>\u0020',
						':=>\u0020', // T145023
					],
				],
			],
		];
		foreach ( $defaults[ 'analyzer' ] as &$analyzer ) {
			if ( $analyzer[ 'type' ] === 'default' ) {
				$analyzer = [
					'type' => 'custom',
					'tokenizer' => 'standard',
					'filter' => [ 'lowercase' ],
				];
			}
		}
		if ( $this->icu ) {
			$defaults[ 'filter' ][ 'icu_normalizer' ] = [
				'type' => 'icu_normalizer',
				'name' => 'nfkc_cf',
			];
		}

		return $defaults;
	}

	/**
	 * Customize the default config for the language.
	 *
	 * @param array $config
	 * @param string $language Config language
	 * @return array
	 */
	private function customize( $config, $language ) {
		// A common regression caused by unpacking is that İ is no longer treated correctly,
		// so specify the mapping just once and re-use.
		$dottedI = [ '\u0130=>I' ];

		switch ( $this->getDefaultTextAnalyzerType( $language ) ) {
		// Please add languages in alphabetical order.
		case 'bosnian':
		case 'croatian':
		case 'serbian':
		case 'serbo-croatian':
			// Unpack default analyzer to add Serbian stemming and custom folding
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T183015
			// and https://www.mediawiki.org/wiki/User:TJones_(WMF)/T192395
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'asciifolding', 'serbian_stemmer' ],
			];
			break;
		case 'chinese':
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T158203
			$config[ 'char_filter' ][ 'stconvertfix' ] = [
				// hack for STConvert errors (still present as of March 2021)
				// see https://github.com/medcl/elasticsearch-analysis-stconvert/issues/13
				'type' => 'mapping',
				'mappings' => [
					'\u606d\u5f18=>\u606d \u5f18',
					'\u5138=>\u3469',
				],
			];
			$config[ 'char_filter' ][ 'tsconvert' ] = [
				'type' => 'stconvert',
				'delimiter' => '#',
				'keep_both' => false,
				'convert_type' => 't2s',
			];
			// SmartCN converts lots of punctuation to ',' but we don't want to index it
			$config[ 'filter' ][ 'smartcn_stop' ] = $this->stopFilter( [ "," ] );

			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'stconvertfix', 'tsconvert' ],
				'tokenizer' => 'smartcn_tokenizer',
				'filter' => [ 'smartcn_stop', 'lowercase' ],
			];

			$config[ 'analyzer' ][ 'plain' ][ 'filter' ] = [ 'smartcn_stop', 'lowercase' ];
			$config[ 'analyzer' ][ 'plain_search' ][ 'filter' ] = $config[ 'analyzer' ][ 'plain' ][ 'filter' ];
			break;
		case 'english':
			// Map hiragana (\u3041-\u3096) to katakana (\u30a1-\u30f6), currently only for English
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T176197
			$hkmap = [];
			for ( $i = 0x3041; $i <= 0x3096; $i++ ) {
			  $hkmap[] = sprintf( "\\u%04x=>\\u%04x", $i, $i + 0x60 );
			}
			$config[ 'char_filter' ][ 'kana_map' ] = [
					'type' => 'mapping',
					'mappings' => $hkmap,
				];
			$config[ 'filter' ][ 'possessive_english' ] = $this->stemmerFilter( 'possessive_english' );

			// Setup custom stemmer
			$config[ 'filter' ][ 'custom_stem' ] = [
				'type' => 'stemmer_override',
				'rules' => 'guidelines => guideline',
			];

			// Replace English analyzer with a rebuilt copy with asciifolding inserted before stemming
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T142037
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'word_break_helper', 'kana_map' ],
				'tokenizer' => 'standard',
				'filter' => [ 'aggressive_splitting', 'possessive_english', 'lowercase',
					'stop', 'asciifolding', 'kstem', 'custom_stem' ],
			];

			// Add asciifolding_preserve to the plain analyzer as well (but not plain_search)
			$config[ 'analyzer' ][ 'plain' ][ 'filter' ][] = 'asciifolding_preserve';
			// Add asciifolding_preserve filters
			$config[ 'analyzer' ][ 'lowercase_keyword' ][ 'filter' ][] = 'asciifolding_preserve';
			break;
		case 'esperanto':
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T202173
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'asciifolding', 'esperanto_stemmer' ],
			];
			break;
		case 'french':
			// Add asciifolding_preserve to filters
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T142620
			$config[ 'analyzer' ][ 'lowercase_keyword' ][ 'filter' ][] = 'asciifolding_preserve';

			$config[ 'char_filter' ][ 'french_charfilter' ] = [
				'type' => 'mapping',
				'mappings' => array_merge( $dottedI, [
					'\u02BC=>\u0027',	// modifier apostrophe to straight quote T146804
				] ),
			];
			$config[ 'filter' ][ 'french_elision' ] = $this->elisionFilter( true, [ 'l', 'm', 't', 'qu', 'n',
				's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu' ] );
			$config[ 'filter' ][ 'french_stop' ] = $this->stopFilter( '_french_' );
			$config[ 'filter' ][ 'french_stemmer' ] = $this->stemmerFilter( 'light_french' );

			// Replace French analyzer with a rebuilt copy with asciifolding_preserve tacked on the end
			// T141216 / T142620
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'french_charfilter' ],
				'tokenizer' => 'standard',
				'filter' => [ 'french_elision', 'lowercase', 'french_stop', 'french_stemmer',
					'asciifolding_preserve' ],
			];
			break;
		case 'greek':
			$config[ 'filter' ][ 'lowercase' ][ 'language' ] = 'greek';

			// Unpack Greek analysis so we can add empty-token filter
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T203117
			$config[ 'filter' ][ 'greek_stop' ] = $this->stopFilter( '_greek_' );
			$config[ 'filter' ][ 'greek_stemmer' ] = $this->stemmerFilter( 'greek' );

			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'greek_stop', 'greek_stemmer', 'remove_empty' ],
			];
			break;
		case 'hebrew':
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'tokenizer' => 'hebrew',
				'filter' => [ 'niqqud', 'hebrew_lemmatizer', 'lowercase', 'asciifolding' ],
			];
			break;
		case 'indonesian':
		case 'malay':
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T196780
			$config[ 'char_filter' ][ 'indonesian_charfilter' ] = [
				'type' => 'mapping',
				'mappings' => $dottedI,
			];

			$config[ 'filter' ][ 'indonesian_stop' ] = $this->stopFilter( '_indonesian_' );
			$config[ 'filter' ][ 'indonesian_stemmer' ] = $this->stemmerFilter( 'indonesian' );

			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'indonesian_charfilter' ],
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'indonesian_stop', 'indonesian_stemmer' ],
			];
			break;
		case 'irish':
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T217602
			$config[ 'filter' ][ 'lowercase' ][ 'language' ] = 'irish';
			break;
		case 'italian':
			$config[ 'filter' ][ 'italian_elision' ] = $this->elisionFilter( false, [ 'c', 'l', 'all',
				'dall', 'dell', 'nell', 'sull', 'coll', 'pell', 'gl', 'agl', 'dagl', 'degl', 'negl',
				'sugl', 'un', 'm', 't', 's', 'v', 'd' ] );
			$config[ 'filter' ][ 'italian_stop' ] = $this->stopFilter( '_italian_' );
			$config[ 'filter' ][ 'light_italian_stemmer' ] = $this->stemmerFilter( 'light_italian' );

			// Replace the default Italian analyzer with a rebuilt copy with asciifolding tacked on the end
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'word_break_helper' ],
				'tokenizer' => 'standard',
				'filter' => [ 'italian_elision', 'aggressive_splitting', 'lowercase', 'italian_stop',
					'light_italian_stemmer', 'asciifolding' ]
			];

			// Add asciifolding_preserve to the plain analyzer as well (but not plain_search)
			$config[ 'analyzer' ][ 'plain' ][ 'filter' ][] = 'asciifolding_preserve';
			// Add asciifolding_preserve to filters
			$config[ 'analyzer' ][ 'lowercase_keyword' ][ 'filter' ][] = 'asciifolding_preserve';
			break;
		case 'japanese':
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T166731

			// pre-convert fullwidth numbers because Kuromoji tokenizer treats them weirdly
			$config[ 'char_filter' ][ 'fullwidthnumfix' ] = $this->numberCharFilter( 0xff10 );

			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'fullwidthnumfix' ],
				'tokenizer' => 'kuromoji_tokenizer',
				'filter' => [ 'kuromoji_baseform', 'cjk_width', 'ja_stop', 'kuromoji_stemmer', 'lowercase' ],
			];
			break;
		case 'khmer':
			// See Khmer: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T185721
			$config[ 'char_filter' ][ 'khmer_numbers' ] = $this->numberCharFilter( 0x17e0 );
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'khmer_syll_reorder', 'khmer_numbers' ],
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase' ],
			];
			break;
		case 'korean':
			// Unpack nori analyzer to add ICU normalization and custom filters
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T206874

			// 'mixed' mode keeps the original token plus the compound parts
			// the default is 'discard' which only keeps the parts
			$config[ 'tokenizer' ][ 'nori_tok' ] = [
				'type' => 'nori_tokenizer',
				'decompound_mode' => 'mixed',
			];

			// Nori-specific character filters
			$config[ 'char_filter' ][ 'nori_charfilter' ] = [
				'type' => 'mapping',
				'mappings' => array_merge( $dottedI, [
					'\u00B7=>\u0020', // convert middle dot to space
					'\u318D=>\u0020', // arae-a to space
					'\u00AD=>',		  // remove soft hyphens
					'\u200C=>',		  // remove zero-width non-joiners
				] ),
			];

			// Nori-specific pattern_replace to strip combining diacritics
			$config[ 'char_filter' ][ 'nori_combo_filter' ] = [
				'type' => 'pattern_replace',
				'pattern' => '[\\u0300-\\u0331]',
				'replacement' => '',
			];

			// Nori-specific part of speech filter (add 'VCP', 'VCN', 'VX' to default)
			$config[ 'filter' ][ 'nori_posfilter' ] = [
				'type' => 'nori_part_of_speech',
				'stoptags' => [ 'E', 'IC', 'J', 'MAG', 'MAJ', 'MM', 'SP', 'SSC', 'SSO', 'SC',
					'SE', 'XPN', 'XSA', 'XSN', 'XSV', 'UNA', 'NA', 'VSV', 'VCP', 'VCN', 'VX' ],
			];

			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'nori_charfilter', 'nori_combo_filter' ],
				'tokenizer' => 'nori_tok',
				'filter' => [ 'nori_posfilter', 'nori_readingform', 'lowercase', 'remove_empty' ],
			];
			break;
		case 'mirandese':
			// Unpack default analyzer to add Mirandese-specific elision and stop words
			// See phab ticket T194941

			$config[ 'filter' ][ 'mirandese_elision' ] = $this->elisionFilter( true, [ 'l', 'd', 'qu' ] );
			$config[ 'filter' ][ 'mirandese_stop' ] = $this->stopFilter( require __DIR__ .
				'/AnalysisLanguageData/mirandeseStopwords.php' );
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'mirandese_elision', 'mirandese_stop' ],
			];
			break;
		case 'polish':
			$config[ 'char_filter' ][ 'polish_charfilter' ] = [
				'type' => 'mapping',
				'mappings' => $dottedI,
			];

			// these are real stop words for Polish
			$config[ 'filter' ][ 'polish_stop' ] = $this->stopFilter( require __DIR__ .
				'/AnalysisLanguageData/polishStopwords.php' );

			// Stempel is statistical, and certain stems are really terrible, so we filter them
			// after stemming. See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T186046

			// Stempel-specific pattern filter [a-zął]?[a-zćń] for unreliable stems
			$config[ 'filter' ][ 'stempel_pattern_filter' ] = [
				'type' => 'pattern_replace',
				'pattern' => '^([a-zął]?[a-zćń]|..ć|\d.*ć)$',
				'replacement' => '',
			];

			// Stempel-specific stop words--additional unreliable stems
			$config[ 'filter' ][ 'stempel_stop' ] = $this->stopFilter( [ 'ować', 'iwać', 'obić',
				'snąć', 'ywać', 'ium', 'my', 'um' ] );

			// unpacked Stempel
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'polish_charfilter' ],
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'polish_stop', 'polish_stem', 'stempel_pattern_filter',
					'remove_empty', 'stempel_stop' ],
			];
			break;
		case 'russian':
			$ruCharMap = [
					'\u0301=>',				// combining acute accent, only used to show stress T102298
					'\u0435\u0308=>\u0435',	// T124592 fold ё=>е and Ё=>Е, with combining diacritic...
					'\u0415\u0308=>\u0415',
					'\u0451=>\u0435',		// ... or precomposed
					'\u0401=>\u0415',
				];

			$config[ 'char_filter' ][ 'russian_charfilter' ] = [
				'type' => 'mapping',
				'mappings' => array_merge( $ruCharMap, $dottedI ),
			];

			// add Russian character mappings to near_space_flattener
			array_push( $config[ 'char_filter' ][ 'near_space_flattener' ][ 'mappings' ],
				...$ruCharMap );

			// Drop acute stress marks and fold ё=>е everywhere
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T124592
			$config[ 'analyzer' ][ 'plain' ][ 'char_filter' ][] = 'russian_charfilter';
			$config[ 'analyzer' ][ 'plain_search' ][ 'char_filter' ][] = 'russian_charfilter';

			$config[ 'analyzer' ][ 'suggest' ][ 'char_filter' ][] = 'russian_charfilter';
			$config[ 'analyzer' ][ 'suggest_reverse' ][ 'char_filter' ][] = 'russian_charfilter';

			// unpack built-in Russian analyzer and add character filter
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T124592
			$config[ 'filter' ][ 'russian_stop' ] = $this->stopFilter( '_russian_' );
			$config[ 'filter' ][ 'russian_stemmer' ] = $this->stemmerFilter( 'russian' );
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'char_filter' => [ 'russian_charfilter' ],
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'russian_stop', 'russian_stemmer' ]
			];
			break;
		case 'slovak':
			/* Unpack default analyzer to add Slovak stemming and custom folding
			 * See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T190815
			 *   and https://www.mediawiki.org/wiki/User:TJones_(WMF)/T223787
			 */
			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'slovak_stemmer', 'asciifolding' ],
			];
			break;
		case 'swedish':
			// Add asciifolding_preserve to filters
			// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T160562
			$config[ 'analyzer' ][ 'lowercase_keyword' ][ 'filter' ][] = 'asciifolding_preserve';

			// Unpack built-in swedish analyzer to add asciifolding_preserve
			$config[ 'filter' ][ 'swedish_stop' ] = $this->stopFilter( '_swedish_' );
			$config[ 'filter' ][ 'swedish_stemmer' ] = $this->stemmerFilter( 'swedish' );

			$config[ 'analyzer' ][ 'text' ] = [
				'type' => 'custom',
				'tokenizer' => 'standard',
				'filter' => [ 'lowercase', 'swedish_stop', 'swedish_stemmer', 'asciifolding_preserve' ],
			];
			break;
		case 'turkish':
			$config[ 'filter' ][ 'lowercase' ][ 'language' ] = 'turkish';
			break;
		}

		// text_search is just a copy of text
		// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
		$config[ 'analyzer' ][ 'text_search' ] = $config[ 'analyzer' ][ 'text' ];

		// replace lowercase filters with icu_normalizer filter
		if ( $this->icu ) {
			foreach ( $config[ 'analyzer' ] as &$analyzer ) {
				if ( !isset( $analyzer[ 'filter'  ] ) ) {
					continue;
				}

				$tmpFilters = [];
				foreach ( $analyzer[ 'filter' ] as $filter ) {
					if ( $filter === 'lowercase' ) {
						// If lowercase filter has language-specific processing, keep it,
						// and do it before ICU normalization, particularly for Greek,
						// Irish, and Turkish
						// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T203117
						// See https://www.mediawiki.org/wiki/User:TJones_(WMF)/T217602
						if ( isset( $config[ 'filter' ][ 'lowercase' ][ 'language' ] ) ) {
							$tmpFilters[] = 'lowercase';
						}
						$tmpFilters[] = 'icu_normalizer';
					} else {
						$tmpFilters[] = $filter;
					}
				}
				$analyzer[ 'filter' ] = $tmpFilters;

			}
		}

		return $config;
	}

	/**
	 * Create an elision filter with the "articles" provided; $case determines whether
	 * stripping is case sensitive or not
	 *
	 * @param bool $case
	 * @param array $articles
	 * @return mixed[] token filter
	 */
	private function elisionFilter( bool $case, array $articles ): array {
		return [ 'type' => 'elision', 'articles_case' => $case, 'articles' => $articles ];
	}

	/**
	 * Create a stop word filter with the provided config. The config can be a list
	 * of stop words, or a string like _french_ that refers to a pre-defined list.
	 *
	 * @param mixed $stopwords
	 * @return mixed[] token filter
	 */
	private function stopFilter( $stopwords ): array {
		return [ 'type' => 'stop', 'stopwords' => $stopwords ];
	}

	/**
	 * Create a stemmer filter with the provided config.
	 *
	 * @param string $stemmer
	 * @return mixed[] token filter
	 */
	private function stemmerFilter( string $stemmer ): array {
		return [ 'type' => 'stemmer', 'language' => $stemmer ];
	}

	/**
	 * Create a character filter that maps non-Arabic digits (e.g., ០-៩ or ０-９) to
	 * Arabic digits (0-9). Since they are usually all in a row, we just need the
	 * starting digit (equal to 0)
	 *
	 * @param int $start
	 * @return mixed[] character filter
	 */
	private function numberCharFilter( int $start ): array {
		$numMap = [];
		for ( $i = 0; $i <= 9; $i++ ) {
		  $numMap[] = sprintf( "\\u%04x=>%d", $start + $i, $i );
		}
		return [ 'type' => 'mapping', 'mappings' => $numMap ];
	}

	/**
	 * Workaround for https://issues.apache.org/jira/browse/LUCENE-7468
	 * The preserve_original duplicates token even if they are
	 * not modified, leading to more space used and wrong term frequencies.
	 * Workaround is to append a unique filter to remove the dups.
	 * (made public for unit tests)
	 *
	 * @param mixed[] $config
	 * @return mixed[] update mapping
	 */
	public function fixAsciiFolding( array $config ) {
		$needDedupFilter = false;
		foreach ( $config[ 'analyzer' ] as $name => &$value ) {
			if ( isset( $value[ 'type' ] ) && $value[ 'type' ] != 'custom' ) {
				continue;
			}
			if ( !isset( $value[ 'filter' ] ) ) {
				continue;
			}
			$ascii_idx = array_search( 'asciifolding_preserve', $value[ 'filter' ] );
			if ( $ascii_idx !== false ) {
				$needDedupFilter = true;
				array_splice( $value[ 'filter' ], $ascii_idx + 1, 0, [ 'dedup_asciifolding' ] );
			}
		}
		if ( $needDedupFilter ) {
			$config[ 'filter' ][ 'dedup_asciifolding' ] = [
				'type' => 'unique',
				'only_on_same_position' => true,
			];
		}
		return $config;
	}

	/**
	 * Pick the appropriate default analyzer based on the language.  Rather than think of
	 * this as per language customization you should think of this as an effort to pick a
	 * reasonably default in case CirrusSearch isn't customized for the language.
	 *
	 * @param string $language Config language
	 * @return string the analyzer type
	 */
	public function getDefaultTextAnalyzerType( $language ) {
		// If we match a language exactly, use it
		if ( array_key_exists( $language, $this->elasticsearchLanguageAnalyzers ) ) {
			return $this->elasticsearchLanguageAnalyzers[ $language ];
		}

		return 'default';
	}

	/**
	 * Get list of filters that are mentioned in analyzers but not defined
	 * explicitly.
	 * @param array[] &$config Full configuration array
	 * @param string[] $analyzers List of analyzers to consider.
	 * @return array List of default filters, each containing only filter type
	 */
	private function getDefaultFilters( array &$config, array $analyzers ) {
		$defaultFilters = [];
		foreach ( $analyzers as $analyzer ) {
			if ( empty( $config[ 'analyzer' ][ $analyzer ][ 'filter' ] ) ) {
				continue;
			}
			foreach ( $config[ 'analyzer' ][ $analyzer ][ 'filter' ] as $filterName ) {
				if ( !isset( $config[ 'filter' ][ $filterName ] ) ) {
					// This is default definition for the built-in filter
					$defaultFilters[ $filterName ] = [ 'type' => $filterName ];
				}
			}
		}
		return $defaultFilters;
	}

	/**
	 * Check every filter in the config - if it's the same as in old config,
	 * ignore it. If it has the same name, but different content - create new filter
	 * with different name by prefixing it with language code.
	 *
	 * @param array[] &$config Configuration being processed
	 * @param array[] $standardFilters Existing filters list
	 * @param array[] $defaultFilters List of default filters already mentioned in the config
	 * @param string $prefix Prefix for disambiguation
	 * @return array[] The list of filters not in the old config.
	 */
	private function resolveFilters( array &$config, array $standardFilters, array $defaultFilters, $prefix ) {
		$resultFilters = [];
		foreach ( $config[ 'filter' ] as $name => $filter ) {
			$existingFilter = null;
			if ( isset( $standardFilters[ $name ] ) ) {
				$existingFilter = $standardFilters[ $name ];
			} elseif ( isset( $defaultFilters[ $name ] ) ) {
				$existingFilter = $defaultFilters[ $name ];
			}

			if ( $existingFilter ) { // Filter with this name already exists
				if ( $existingFilter != $filter ) {
					// filter with the same name but different config - need to
					// rename by adding prefix
					$newName = $prefix . '_' . $name;
					$this->replaceFilter( $config, $name, $newName );
					$resultFilters[ $newName ] = $filter;
				}
			} else {
				$resultFilters[ $name ] = $filter;
			}
		}
		return $resultFilters;
	}

	/**
	 * Replace certain filter name in all configs with different name.
	 * @param array[] &$config Configuration being processed
	 * @param string $oldName
	 * @param string $newName
	 */
	private function replaceFilter( array &$config, $oldName, $newName ) {
		foreach ( $config[ 'analyzer' ] as &$analyzer ) {
			if ( !isset( $analyzer[ 'filter' ] ) ) {
				continue;
			}
			$analyzer[ 'filter' ] = array_map( function ( $filter ) use ( $oldName, $newName ) {
				if ( $filter === $oldName ) {
					return $newName;
				}
				return $filter;
			}, $analyzer[ 'filter' ] );
		}
	}

	/**
	 * Merge per-language config into the main config.
	 * It will copy specific analyzer and all dependant filters and char_filters.
	 * @param array &$config Main config
	 * @param array $langConfig Per-language config
	 * @param string $name Name for analyzer whose config we're merging
	 * @param string $prefix Prefix for this configuration
	 */
	private function mergeConfig( array &$config, array $langConfig, $name, $prefix ) {
		$analyzer = $langConfig[ 'analyzer' ][ $name ];
		$config[ 'analyzer' ][ $prefix . '_' . $name ] = $analyzer;
		if ( !empty( $analyzer[ 'filter' ] ) ) {
			// Add private filters for this analyzer
			foreach ( $analyzer[ 'filter' ] as $filter ) {
				// Copy filters that are in language config but not in the main config.
				// We would not copy the same filter into the main config since due to
				// the resolution step we know they are the same (otherwise we would have
				// renamed it).
				if ( isset( $langConfig[ 'filter' ][ $filter ] ) &&
					!isset( $config[ 'filter' ][ $filter ] ) ) {
					$config[ 'filter' ][ $filter ] = $langConfig[ 'filter' ][ $filter ];
				}
			}
		}
		if ( !empty( $analyzer[ 'char_filter' ] ) ) {
			// Add private char_filters for this analyzer
			foreach ( $analyzer[ 'char_filter' ] as $filter ) {
				// Here unlike above we do not check for $langConfig since we assume
				// language config is not broken and all char filters are namespaced
				// nicely, so if the filter is mentioned in analyzer it is also defined.
				if ( !isset( $config[ 'char_filter' ][ $filter ] ) ) {
					$config[ 'char_filter' ][ $filter ] = $langConfig[ 'char_filter' ][ $filter ];
				}
			}
		}
		if ( !empty( $analyzer[ 'tokenizer' ] ) ) {
			$tokenizer = $analyzer[ 'tokenizer' ];
			if ( isset( $langConfig[ 'tokenizer' ][ $tokenizer ] ) && !isset( $config[ 'tokenizer' ][ $tokenizer ] ) ) {
				$config[ 'tokenizer' ][ $tokenizer ] = $langConfig[ 'tokenizer' ][ $tokenizer ];
			}
		}
	}

	/**
	 * Create per-language configs for specific analyzers which separates and namespaces
	 * filters that are different between languages.
	 * @param array &$config Existing config, will be modified
	 * @param string[] $languages List of languages to process
	 * @param string[] $analyzers List of analyzers to process
	 */
	public function buildLanguageConfigs( array &$config, array $languages, array $analyzers ) {
		$defaultFilters = $this->getDefaultFilters( $config, $analyzers );
		foreach ( $languages as $lang ) {
			$langConfig = $this->buildConfig( $lang );
			$defaultFilters += $this->getDefaultFilters( $langConfig, $analyzers );
		}
		foreach ( $languages as $lang ) {
			$langConfig = $this->buildConfig( $lang );
			// Analyzer is: tokenizer + filter + char_filter
			// Char filters & Tokenizers are nicely namespaced
			// Filters are NOT - e.g. lowercase & icu_folding filters are different for different
			// languages! So we need to do some disambiguation here.
			$langConfig[ 'filter' ] = $this->resolveFilters( $langConfig, $config[ 'filter' ], $defaultFilters, $lang );
			// Merge configs
			foreach ( $analyzers as $analyzer ) {
				$this->mergeConfig( $config, $langConfig, $analyzer, $lang );
			}
		}
	}

	/**
	 * @return bool true if the icu analyzer is available.
	 */
	public function isIcuAvailable() {
		return $this->icu;
	}

	/**
	 * update languages with homoglyph plugin
	 * @param mixed[] $config
	 * @param string $language language to add plugin to
	 * @return mixed[] update config
	 */
	public function enableHomoglyphPlugin( array $config, string $language ) {
		$inDenyList = $this->homoglyphPluginDenyList[$language] ?? false;
		if ( in_array( 'extra-analysis-homoglyph', $this->plugins ) && !$inDenyList ) {
			$config = $this->insertHomoglyphFilter( $config, 'text' );
			$config = $this->insertHomoglyphFilter( $config, 'text_search' );
		}
		return $config;
	}

	private function insertHomoglyphFilter( array $config, string $analyzer ) {
		if ( !array_key_exists( $analyzer, $config['analyzer'] ) ) {
			return $config;
		}

		if ( $config['analyzer'][$analyzer]['type'] == 'custom' ) {
			$filters = $config['analyzer'][$analyzer]['filter'] ?? [];

			$lastBadFilter = -1;
			foreach ( $this->homoglyphIncompatibleFilters as $badFilter ) {
				$badFilterIdx = array_keys( $filters, $badFilter );
				$badFilterIdx = end( $badFilterIdx );
				if ( $badFilterIdx !== false && $badFilterIdx > $lastBadFilter ) {
					$lastBadFilter = $badFilterIdx;
				}
			}
			array_splice( $filters, $lastBadFilter + 1, 0, 'homoglyph_norm' );

			$config['analyzer'][$analyzer]['filter'] = $filters;
		}
		return $config;
	}

	/**
	 * Languages for which elasticsearch provides a built in analyzer.  All
	 * other languages default to the default analyzer which isn't too good.  Note
	 * that this array is sorted alphabetically by value and sourced from
	 * http://www.elasticsearch.org/guide/reference/index-modules/analysis/lang-analyzer/
	 *
	 * @var string[]
	 */
	private $elasticsearchLanguageAnalyzers = [
		'ar' => 'arabic',
		'hy' => 'armenian',
		'eu' => 'basque',
		'pt-br' => 'brazilian',
		'bg' => 'bulgarian',
		'ca' => 'catalan',
		'ja' => 'cjk',
		'ko' => 'cjk',
		'cs' => 'czech',
		'da' => 'danish',
		'nl' => 'dutch',
		'en' => 'english',
		'en-ca' => 'english',
		'en-gb' => 'english',
		'simple' => 'english',
		'fi' => 'finnish',
		'fr' => 'french',
		'gl' => 'galician',
		'de' => 'german',
		'el' => 'greek',
		'hi' => 'hindi',
		'hu' => 'hungarian',
		'id' => 'indonesian',
		'ga' => 'irish',
		'it' => 'italian',
		'lt' => 'lithuanian',
		'lv' => 'latvian',
		'ms' => 'malay',
		'mwl' => 'mirandese',
		'nb' => 'norwegian',
		'nn' => 'norwegian',
		'fa' => 'persian',
		'pt' => 'portuguese',
		'ro' => 'romanian',
		'ru' => 'russian',
		'ckb' => 'sorani',
		'es' => 'spanish',
		'sv' => 'swedish',
		'tr' => 'turkish',
		'th' => 'thai',
	];

	/**
	 * @var bool[] indexed by language code, languages where ICU folding
	 * can be enabled by default
	 */
	private $languagesWithIcuFolding = [
		'bs' => true,
		'el' => true,
		'en' => true,
		'en-ca' => true,
		'en-gb' => true,
		'simple' => true,
		'eo' => true,
		'fr' => true,
		'he' => true,
		'hr' => true,
		'sh' => true,
		'sk' => true,
		'sr' => true,
		'sv' => true,
	];

	/**
	 * @var bool[] indexed by language code, languages where ICU tokenization
	 * can be enabled by default
	 */
	private $languagesWithIcuTokenization = [
		"bo" => true,
		"dz" => true,
		"gan" => true,
		"ja" => true,
		"km" => true,
		"lo" => true,
		"my" => true,
		"th" => true,
		"wuu" => true,
		"zh" => true,
		"lzh" => true, // zh-classical
		"zh-classical" => true, // deprecated code for lzh
		"yue" => true, // zh-yue
		"zh-yue" => true, // deprecated code for yue
		// This list below are languages that may use use mixed scripts
		"bug" => true,
		"cdo" => true,
		"cr" => true,
		"hak" => true,
		"jv" => true,
		"nan" => true, // zh-min-nan
		"zh-min-nan" => true, // deprecated code for nan
	];

	/**
	 * @var array[]
	 */
	private $elasticsearchLanguageAnalyzersFromPlugins = [
		// multiple plugin requirement can be comma separated
		/**
		 * Polish: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T154517
		 * Ukrainian: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T160106
		 * Chinese: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T158203
		 * Hebrew: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T162741
		 * Serbian: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T183015
		 * Bosnian, Croatian, and Serbo-Croatian:
		 *    https://www.mediawiki.org/wiki/User:TJones_(WMF)/T192395
		 * Slovak: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T190815
		 * Esperanto: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T202173
		 * Korean: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T206874
		 * Khmer: https://www.mediawiki.org/wiki/User:TJones_(WMF)/T185721
		 */

		'analysis-stempel' => [ 'pl' => 'polish' ],
		'analysis-kuromoji' => [ 'ja' => 'japanese' ],
		'analysis-stconvert,analysis-smartcn' => [ 'zh' => 'chinese' ],
		'analysis-hebrew' => [ 'he' => 'hebrew' ],
		'analysis-ukrainian' => [ 'uk' => 'ukrainian' ],
		'extra-analysis-esperanto' => [ 'eo' => 'esperanto' ],
		'extra-analysis-serbian' => [ 'bs' => 'bosnian', 'hr' => 'croatian',
			'sh' => 'serbo-croatian', 'sr' => 'serbian' ],
		'extra-analysis-slovak' => [ 'sk' => 'slovak' ],
		'analysis-nori' => [ 'ko' => 'korean' ],
		'extra-analysis-khmer' => [ 'km' => 'khmer' ],
	];

	/**
	 * @var bool[] indexed by language code, languages that will not have the homoglyph
	 * plugin included in the analysis chain
	 */
	public $homoglyphPluginDenyList = [];

	/**
	 * @var string[] list of token-splitting token filters that interact poorly with the
	 * homoglyph filter. see T268730
	 */
	public $homoglyphIncompatibleFilters = [ 'aggressive_splitting' ];

}
