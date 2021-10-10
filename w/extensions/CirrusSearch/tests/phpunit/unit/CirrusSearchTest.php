<?php

namespace CirrusSearch;

use CirrusSearch\Wikimedia\WeightedTagsHooks;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageIdentityValue;
use Wikimedia\Assert\AssertionException;

/**
 * @group CirrusSearch
 */
class CirrusSearchTest extends CirrusTestCase {

	public function provideProfiles() {
		return [
			'completion' => [
				\SearchEngine::COMPLETION_PROFILE_TYPE,
				CirrusSearch::AUTOSELECT_PROFILE,
				[ CirrusSearch::AUTOSELECT_PROFILE, CirrusSearch::COMPLETION_PREFIX_FALLBACK_PROFILE ],
			],
			'fulltext query independent' => [
				\SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE,
				CirrusSearch::AUTOSELECT_PROFILE,
				[ CirrusSearch::AUTOSELECT_PROFILE, 'classic' ],
			],
			'unknown' => [
				'unknown',
				null,
				[],
			],
		];
	}

	/**
	 * @dataProvider provideProfiles
	 * @covers \CirrusSearch\CirrusSearch::getProfiles()
	 */
	public function testGetProfiles( $profileType, $default, array $expectedProfiles ) {
		$profiles = $this->getSearchEngine( [ 'CirrusSearchUseCompletionSuggester' => 'yes' ] )
			->getProfiles( $profileType );
		if ( $default === null ) {
			$this->assertNull( $profiles );
		} else {
			$this->assertIsArray( $profiles );
			$nameMap = [];
			foreach ( $profiles as $p ) {
				$this->assertIsArray( $p );
				$this->assertArrayHasKey( 'name', $p );
				$nameMap[$p['name']] = $p;
			}
			foreach ( $expectedProfiles as $expectedProfile ) {
				$this->assertArrayHasKey( $expectedProfile, $nameMap );
				$this->assertArrayHasKey( 'desc-message', $nameMap[$expectedProfile] );
			}
			$this->assertArrayHasKey( 'default', $nameMap[$default] );
		}
	}

	public function provideExtractProfileFromFeatureData() {
		return [
			'engine defaults (completion)' => [
				\SearchEngine::COMPLETION_PROFILE_TYPE,
				CirrusSearch::AUTOSELECT_PROFILE,
				null,
			],
			'engine defaults (fulltext qi)' => [
				\SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE,
				CirrusSearch::AUTOSELECT_PROFILE,
				null,
			],
			'profile set (completion)' => [
				\SearchEngine::COMPLETION_PROFILE_TYPE,
				'foobar',
				'foobar',
			],
			'profile set (fulltext qi)' => [
				\SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE,
				'foobar',
				'foobar',
			]
		];
	}

	/**
	 * @dataProvider provideExtractProfileFromFeatureData
	 * @covers \CirrusSearch\CirrusSearch::extractProfileFromFeatureData
	 * @throws \ConfigException
	 */
	public function testExtractProfileFromFeatureData( $type, $setValue, $expected ) {
		$engine = $this->getSearchEngine( [ 'CirrusSearchUseCompletionSuggester' => 'yes' ] );
		$engine->setFeatureData( $type, $setValue );
		$this->assertEquals( $expected, $engine->extractProfileFromFeatureData( $type ) );
	}

	public function provideCompletionSuggesterEnabled() {
		return [
			'enabled' => [
				'yes', true
			],
			'enabled with bool' => [
				true, true
			],
			'disabled' => [
				'no', false
			],
			'disabled with bool' => [
				false, false
			],
			'disabled with random' => [
				'foo', false
			],
		];
	}

	/**
	 * @covers \CirrusSearch\CirrusSearch::doSearchText
	 */
	public function testFailureOnQueryLength() {
		$engine = $this->getSearchEngine( [ 'CirrusSearchMaxFullTextQueryLength' => 10 ] );
		$engine->setHookContainer( $this->createMock( HookContainer::class ) );
		$status = $engine->searchText( str_repeat( "a", 11 ) );
		$this->assertEquals( $status,
			\Status::newFatal( 'cirrussearch-query-too-long', 11, 10 ) );
	}

	/**
	 * @param array|null $config
	 * @return CirrusSearch
	 * @throws \ConfigException
	 */
	private function getSearchEngine( array $config = null ) {
		// use cirrus base profiles
		// only set needed config for Connection
		$config = $this->newHashSearchConfig( ( $config ?: [] ) + $this->getMinimalConfig() );
		return new CirrusSearch( $config, CirrusDebugOptions::defaultOptions(),
			$this->namespacePrefixParser(), $this->getInterWikiResolver( $config ), $this->newTitleHelper() );
	}

	/**
	 * @return array
	 */
	private function getMinimalConfig() {
		return [
			'CirrusSearchClusters' => [
				'default' => [ 'localhost' ],
			],
			'CirrusSearchDefaultCluster' => 'default',
			'CirrusSearchReplicaGroup' => 'default',
		];
	}

	/**
	 * @covers \CirrusSearch\CirrusSearch::supports
	 */
	public function testSupports() {
		$engine = $this->getSearchEngine();
		$this->assertFalse( $engine->supports( 'search-update' ) );
		$this->assertFalse( $engine->supports( 'list-redirects' ) );
		$this->assertTrue( $engine->supports( \SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE ) );
		$this->assertTrue( $engine->supports( CirrusSearch::EXTRA_FIELDS_TO_EXTRACT ) );
	}

	/**
	 * @covers \CirrusSearch\CirrusSearch::updateWeightedTags
	 * @dataProvider provideUpdateWeightedTags
	 * @param string $tagPrefix
	 * @param string|string[]|null $tagNames
	 * @param int|int[]|null $tagWeights
	 * @param bool $isValid
	 */
	public function testUpdateWeightedTags( $tagPrefix, $tagNames, $tagWeights, $isValid ) {
		$pageIdentity = new PageIdentityValue( 1, 0, 'Test', PageIdentityValue::LOCAL );
		$mockUpdater = $this->createPartialMock( Updater::class, [ 'updateWeightedTags' ] );
		$mockUpdater->expects( $isValid ? $this->exactly( 2 ) : $this->never() )
			->method( 'updateWeightedTags' )
			->with( $pageIdentity, $this->logicalOr( $this->equalTo( WeightedTagsHooks::FIELD_NAME ),
				$this->equalTo( 'ores_articletopics' ) ), $tagPrefix, $tagNames, $tagWeights );
		$cirrusSearch = $this->createPartialMock( CirrusSearch::class, [ 'getUpdater' ] );
		$cirrusSearch->method( 'getUpdater' )->willReturn( $mockUpdater );

		try {
			$cirrusSearch->updateWeightedTags( $pageIdentity, $tagPrefix, $tagNames, $tagWeights );
			$this->assertTrue( $isValid, 'Expected exception not thrown' );
		} catch ( AssertionException $e ) {
			$this->assertFalse( $isValid, 'Unexpected exception thrown: ' . get_class( $e )
				. ': ' . $e->getMessage() );
		}
	}

	public function provideUpdateWeightedTags() {
		return [
			// good
			'prefix only' => [ 'foo', null, null, true ],
			'one tag' => [ 'foo', 'bar', null, true ],
			'multiple tags' => [ 'foo', [ 'bar', 'baz' ], null, true ],
			'weight with no tag' => [ 'foo', null, 500, true ],
			'weight with one tag' => [ 'foo', 'bar', [ 'bar' => 500 ], true ],
			'weight with multiple tags' => [ 'foo', [ 'bar', 'baz' ], [ 'bar' => 500, 'baz' => 600 ], true ],
			// bad
			'/ in prefix' => [ 'foo/bar', null, null, false ],
			'| in tag' => [ 'foo', 'bar|baz', null, false ],
			'weight too large' => [ 'foo', null, 1500, false ],
			'weight too large #2' => [ 'foo', [ 'bar', 'baz' ], [ 'bar' => 500, 'baz' => 1600 ], false ],
			'weight too small' => [ 'foo', null, 0, false ],
			'string weight' => [ 'foo', null, '500', false ],
			'float weight' => [ 'foo', null, 0.5, false ],
			'weight for non-existent tag' => [ 'foo', [ 'bar', 'baz' ],
				[ 'bar' => 500, 'baz' => 600, 'boom' => 700 ], false ],
		];
	}
}
