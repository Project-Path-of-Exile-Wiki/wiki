<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\Test\DummySearchResultSet;
use HtmlArmor;

/**
 * @covers \CirrusSearch\Fallbacks\PhraseSuggestFallbackMethod
 * @covers \CirrusSearch\Fallbacks\FallbackMethodTrait
 */
class PhraseSuggestFallbackMethodTest extends BaseFallbackMethodTest {

	public function provideTest() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'phraseSuggestResponses/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, -7 );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$resultSet = $this->newResultSet( $fixture['response'] );
			$tests[$testName] = [
				$fixture['query'],
				$resultSet,
				$fixture['approxScore'],
				$fixture['suggestion'],
				$fixture['suggestionSnippet'],
				$fixture['rewritten']
			];
		}

		return $tests;
	}

	/**
	 * @dataProvider provideTest
	 */
	public function test(
		$queryString,
		CirrusSearchResultSet $initialResults,
		$expectedApproxScore,
		$suggestion,
		$suggestionSnippet,
		$rewritten
	) {
		$config = $this->newHashSearchConfig( [ 'CirrusSearchEnablePhraseSuggest' => true ] );
		$query = $this->getNewFTSearchQueryBuilder( $config, $queryString )
			->setAllowRewrite( true )
			->build();

		$rewrittenResults = $rewritten ? DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), 1 ) : null;
		$rewrittenQuery = $rewritten ? SearchQueryBuilder::forRewrittenQuery( $query, $suggestion, $this->namespacePrefixParser(),
				$this->createCirrusSearchHookRunner() )->build() : null;
		$searcherFactory = $this->getSearcherFactoryMock( $rewrittenQuery, $rewrittenResults );
		$fallback = PhraseSuggestFallbackMethod::build( $query, [ 'profile' => 'default' ] );
		if ( $expectedApproxScore > 0.0 ) {
			$this->assertNotNull( $fallback->getSuggestQueries() );
		}
		$context = new FallbackRunnerContextImpl( $initialResults, $searcherFactory,
			$this->namespacePrefixParser(), $this->createCirrusSearchHookRunner() );
		$this->assertEquals( $expectedApproxScore, $fallback->successApproximation( $context ) );
		if ( $expectedApproxScore > 0 ) {
			$status = $fallback->rewrite( $context );
			$actualNewResults = $status->apply( $initialResults );
			if ( $rewrittenResults === null ) {
				$this->assertEquals(
					$suggestion === null ? FallbackStatus::NO_ACTION : FallbackStatus::ACTION_SUGGEST_QUERY,
					$status->getAction() );
				$this->assertSame( $initialResults, $actualNewResults );
				$this->assertNull( $actualNewResults->getQueryAfterRewrite() );
				$this->assertNull( $actualNewResults->getQueryAfterRewriteSnippet() );
				$this->assertSame( $suggestion, $actualNewResults->getSuggestionQuery() );
				$this->assertEquals( new HtmlArmor( $suggestionSnippet ),
					$actualNewResults->getSuggestionSnippet() );
			} else {
				$this->assertSame( FallbackStatus::ACTION_REPLACE_LOCAL_RESULTS, $status->getAction
				() );
				$this->assertSame( $suggestion, $rewrittenResults->getQueryAfterRewrite() );
				$this->assertEquals( new HtmlArmor( $suggestionSnippet ),
					$rewrittenResults->getQueryAfterRewriteSnippet() );
				$this->assertSame( $rewrittenResults, $actualNewResults );
			}
		}
	}

	public function provideTestSuggestQueries() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'phraseSuggest/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, -7 );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[$testName] = [
				$expectedFile,
				$fixture['query'],
				$fixture['namespaces'],
				$fixture['offset'],
				$fixture['with_dym'] ?? true,
				$fixture['profile'] ?? 'default',
				$fixture['config']
			];
		}
		return $tests;
	}

	/**
	 * @dataProvider provideTestSuggestQueries
	 */
	public function testSuggestQuery(
		$expectedFile,
		$query,
		$namespaces,
		$offset,
		$withDYMSuggestion,
		$profile,
		$config
	) {
		$query = $this->getNewFTSearchQueryBuilder( $this->newHashSearchConfig( $config ), $query )
			->setInitialNamespaces( $namespaces )
			->setOffset( $offset )
			->setWithDYMSuggestion( $withDYMSuggestion )
			->build();
		$method = PhraseSuggestFallbackMethod::build( $query, [ 'profile' => $profile ] );
		$suggestQueries = null;
		if ( $method !== null ) {
			$suggestQueries = $method->getSuggestQueries();
		}

		$this->assertFileContains(
			CirrusIntegrationTestCase::fixturePath( $expectedFile ),
			CirrusIntegrationTestCase::encodeFixture( $suggestQueries ),
			self::canRebuildFixture()
		);
	}

	public function testBuild() {
		$query = $this->getNewFTSearchQueryBuilder(
				new HashSearchConfig( [] ),
				'foo bar'
			)
			->setWithDYMSuggestion( false )
			->build();
		$this->assertNull( PhraseSuggestFallbackMethod::build( $query, [ 'profile' => 'default' ] ) );

		$query = $this->getNewFTSearchQueryBuilder(
				new HashSearchConfig( [] ),
				'foo bar'
			)
			->setWithDYMSuggestion( true )
			->build();
		$this->assertNull( PhraseSuggestFallbackMethod::build( $query, [ 'profile' => 'default' ] ) );

		$query = $this->getNewFTSearchQueryBuilder(
				new HashSearchConfig( [ 'CirrusSearchEnablePhraseSuggest' => false ] ),
				'foo bar'
			)
			->setWithDYMSuggestion( true )
			->build();
		$this->assertNull( PhraseSuggestFallbackMethod::build( $query, [ 'profile' => 'default' ] ) );

		$query = $this->getNewFTSearchQueryBuilder(
				new HashSearchConfig( [ 'CirrusSearchEnablePhraseSuggest' => true ] ),
				'foo bar'
			)
			->setWithDYMSuggestion( true )
			->build();
		$this->assertNotNull( PhraseSuggestFallbackMethod::build( $query, [ 'profile' => 'default' ] ) );

		$query = $this->getNewFTSearchQueryBuilder(
				new HashSearchConfig( [ 'CirrusSearchEnablePhraseSuggest' => true ] ),
				'foo bar'
			)
			->setWithDYMSuggestion( false )
			->build();
		$this->assertNull( PhraseSuggestFallbackMethod::build( $query, [ 'profile' => 'default' ] ) );
	}

	/**
	 * @covers \CirrusSearch\Fallbacks\FallbackRunnerContextImpl
	 */
	public function testDisabledIfHasASuggestionOrWasRewritten() {
		$query = $this->getNewFTSearchQueryBuilder(
				$this->newHashSearchConfig( [ 'CirrusSearchEnablePhraseSuggest' => true ] ), "foo bar" )
			->setWithDYMSuggestion( true )
			->build();
		/**
		 * @var $method PhraseSuggestFallbackMethod
		 */
		$method = PhraseSuggestFallbackMethod::build( $query, [ 'profile' => 'default' ] );
		$this->assertNotNull( $method->getSuggestQueries() );

		$rset = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), 10 );
		$rset->setSuggestionQuery( "test", "test" );
		$factory = $this->createMock( SearcherFactory::class );
		$factory->expects( $this->never() )->method( 'makeSearcher' );
		$context = new FallbackRunnerContextImpl( $rset, $factory, $this->namespacePrefixParser(),
			$this->createCirrusSearchHookRunner() );
		$method->rewrite( $context );
		$this->assertTrue( $context->costlyCallAllowed() );

		$rset = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), 10 );
		$factory = $this->createMock( SearcherFactory::class );
		$factory->expects( $this->never() )->method( 'makeSearcher' );
		$context = new FallbackRunnerContextImpl( $rset, $factory, $this->namespacePrefixParser(),
			$this->createCirrusSearchHookRunner() );
		$this->assertTrue( $context->costlyCallAllowed() );
		$rset->setRewrittenQuery( "test", "test" );
		$this->assertEquals( FallbackStatus::NO_ACTION, $method->rewrite( $context )->getAction() );
	}
}
