<?php

namespace CirrusSearch;

use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use CirrusSearch\Maintenance\MappingConfigBuilder;
use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Search\Rescore\BoostFunctionBuilder;
use Content;
use Elastica\Document;
use MediaWiki\HookContainer\HookContainer;
use ParserOutput;
use Title;

/**
 * @internal
 */
class CirrusSearchHookRunner {
	/**
	 * @var HookContainer
	 */
	private $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * Register FullText query classifiers
	 * @param Parser\ParsedQueryClassifiersRepository $repository
	 */
	public function onCirrusSearchRegisterFullTextQueryClassifiers( Parser\ParsedQueryClassifiersRepository $repository ): void {
		$this->hookContainer->run( 'CirrusSearchRegisterFullTextQueryClassifiers', [ $repository ] );
	}

	/**
	 * Register new search keywords
	 * @param SearchConfig $config
	 * @param SimpleKeywordFeature[] &$extraFeatures
	 */
	public function onCirrusSearchAddQueryFeatures( SearchConfig $config, array &$extraFeatures ): void {
		$this->hookContainer->run( 'CirrusSearchAddQueryFeatures', [ $config, &$extraFeatures ] );
	}

	/**
	 * Use of this hook is deprecated, integration should happen through content handler
	 * interfaces.
	 *
	 * @param Document $document
	 * @param Title $title
	 * @param Content|null $content
	 * @param ParserOutput $parserOutput
	 * @param Connection $connection
	 */
	public function onCirrusSearchBuildDocumentParse(
		Document $document,
		Title $title,
		?Content $content,
		ParserOutput $parserOutput,
		Connection $connection
	): void {
		$this->hookContainer->run( 'CirrusSearchBuildDocumentParse', [ $document, $title, $content, $parserOutput, $connection ] );
	}

	/**
	 * Register new similarity configurations
	 *
	 * @param array &$similarityConfig
	 */
	public function onCirrusSearchSimilarityConfig( array &$similarityConfig ): void {
		$this->hookContainer->run( 'CirrusSearchSimilarityConfig', [ &$similarityConfig ] );
	}

	/**
	 * Alter the analysis configuration
	 *
	 * @param array &$config
	 * @param AnalysisConfigBuilder $analyisConfigBuilder
	 */
	public function onCirrusSearchAnalysisConfig( array &$config, AnalysisConfigBuilder $analyisConfigBuilder ): void {
		$this->hookContainer->run( 'CirrusSearchAnalysisConfig', [ &$config, $analyisConfigBuilder ] );
	}

	/**
	 * Alter the mapping configuration
	 *
	 * @param array &$mappingConfig
	 * @param MappingConfigBuilder $mappingConfigBuilder
	 */
	public function onCirrusSearchMappingConfig( array &$mappingConfig, MappingConfigBuilder $mappingConfigBuilder ): void {
		$this->hookContainer->run( 'CirrusSearchMappingConfig', [ &$mappingConfig, $mappingConfigBuilder ] );
	}

	/**
	 * Register search profiles
	 *
	 * @param Profile\SearchProfileService $service
	 */
	public function onCirrusSearchProfileService( Profile\SearchProfileService $service ): void {
		$this->hookContainer->run( 'CirrusSearchProfileService', [ $service ] );
	}

	/**
	 * @param array $definition
	 * @param Search\SearchContext $context
	 * @param BoostFunctionBuilder|null &$builder
	 */
	public function onCirrusSearchScoreBuilder( array $definition, Search\SearchContext $context, ?BoostFunctionBuilder &$builder ): void {
		$this->hookContainer->run( 'CirrusSearchScoreBuilder', [ $definition, $context, &$builder ] );
	}
}
