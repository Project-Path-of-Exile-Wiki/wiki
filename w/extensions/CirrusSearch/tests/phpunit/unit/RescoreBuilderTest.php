<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Profile\SearchProfileException;
use CirrusSearch\Search\Rescore\BoostTemplatesFunctionScoreBuilder;
use CirrusSearch\Search\Rescore\CustomFieldFunctionScoreBuilder;
use CirrusSearch\Search\Rescore\FunctionScoreDecorator;
use CirrusSearch\Search\Rescore\IncomingLinksFunctionScoreBuilder;
use CirrusSearch\Search\Rescore\InvalidRescoreProfileException;
use CirrusSearch\Search\Rescore\LangWeightFunctionScoreBuilder;
use CirrusSearch\Search\Rescore\NamespacesFunctionScoreBuilder;
use CirrusSearch\Search\Rescore\PreferRecentFunctionScoreBuilder;
use CirrusSearch\Search\Rescore\RescoreBuilder;
use CirrusSearch\Search\Rescore\ScriptScoreFunctionScoreBuilder;
use NamespaceInfo;

/**
 * @group CirrusSearch
 */
class RescoreBuilderTest extends CirrusTestCase {
	/**
	 * @covers \CirrusSearch\Search\Rescore\FunctionScoreDecorator
	 */
	public function testFunctionScoreDecorator() {
		$func = new FunctionScoreDecorator();
		$this->assertTrue( $func->isEmptyFunction() );

		$func->addWeightFunction( 2.0, new \Elastica\Query\MatchAll() );
		$this->assertFalse( $func->isEmptyFunction() );

		$array = $func->toArray();
		$this->assertTrue( isset( $array['function_score'] ) );
		$this->assertCount( 1, $array['function_score']['functions'] );

		$func = new FunctionScoreDecorator();
		$this->assertTrue( $func->isEmptyFunction() );
		$func->addFunction( 'foo_function', [] );
		$func->addFunction( 'foo_function', [] );
		$this->assertFalse( $func->isEmptyFunction() );
		$array = $func->toArray();
		$this->assertCount( 2, $array['function_score']['functions'] );

		$func = new FunctionScoreDecorator();
		$this->assertTrue( $func->isEmptyFunction() );
		$func->addScriptScoreFunction( new \Elastica\Script\Script( "foo+2" ) );
		$this->assertFalse( $func->isEmptyFunction() );
		$array = $func->toArray();
		$this->assertCount( 1, $array['function_score']['functions'] );
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\PreferRecentFunctionScoreBuilder
	 */
	public function testPreferRecent() {
		$config = new HashSearchConfig( [] );
		$builder = new PreferRecentFunctionScoreBuilder( $config, 1, -1, -1 );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertTrue( $fScore->isEmptyFunction() );

		$builder = new PreferRecentFunctionScoreBuilder( $config, 1, 1, 0.6 );

		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\LangWeightFunctionScoreBuilder
	 */
	public function testLangWeight() {
		// Test that we generate 2 filters
		$config = new HashSearchConfig( [
			'CirrusSearchLanguageWeight' => [
				'user' => 2,
				'wiki' => 3,
			],
			'LanguageCode' => 'de'
		] );
		$builder = new LangWeightFunctionScoreBuilder( $config, 1, 'en' );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertCount( 2, $array['function_score']['functions'] );

		// Set cont lang as en to we generate only 1 filter
		$config = new HashSearchConfig( [
			'CirrusSearchLanguageWeight' => [
				'user' => 2,
				'wiki' => 3,
			],
			'LanguageCode' => 'en'
		] );

		$builder = new LangWeightFunctionScoreBuilder( $config, 1, 'en' );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertCount( 1, $array['function_score']['functions'] );

		// Test that we do not generate any filter is weight are not set.
		$config = new HashSearchConfig( [
			'CirrusSearchLanguageWeight' => [],
			'LanguageCode' => 'de'
		] );
		$builder = new LangWeightFunctionScoreBuilder( $config, 1, 'en' );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertTrue( $fScore->isEmptyFunction() );
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\BoostTemplatesFunctionScoreBuilder
	 * @covers \CirrusSearch\Search\Rescore\BoostedQueriesFunction
	 */
	public function testBoostTemplates() {
		$config = new HashSearchConfig( [ 'CirrusSearchIgnoreOnWikiBoostTemplates' => true ] );
		$builder = new BoostTemplatesFunctionScoreBuilder( $config, [], false, true, 1 );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertTrue( $fScore->isEmptyFunction() );

		$config = new HashSearchConfig( [
			'CirrusSearchIgnoreOnWikiBoostTemplates' => true,
			'CirrusSearchBoostTemplates' => [ 'test' => 3.2 ]
		] );
		$builder = new BoostTemplatesFunctionScoreBuilder( $config, [], false, true, 1 );
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );

		$fScore = new FunctionScoreDecorator();
		$builder = new BoostTemplatesFunctionScoreBuilder( $config, [], false, false, 1 );
		$builder->append( $fScore );
		$this->assertTrue( $fScore->isEmptyFunction() );

		$config = new HashSearchConfig( [
			'CirrusSearchIgnoreOnWikiBoostTemplates' => true,
			'CirrusSearchExtraIndexes' => [ NS_MAIN => [ 'extramain' ] ],
			'CirrusSearchExtraIndexBoostTemplates' => [
				'extramain' => [
					'wiki' => 'phpunitwiki',
					'boosts' => [ 'foo' => 0.44 ]
				],
			],
		] );

		$builder = new BoostTemplatesFunctionScoreBuilder( $config, [], true, true, 1 );
		$builder->append( $fScore );
		$this->assertTrue( $fScore->isEmptyFunction() );

		$builder = new BoostTemplatesFunctionScoreBuilder( $config, [], false, true, 1 );
		$builder->append( $fScore );
		$this->assertTrue( $fScore->isEmptyFunction() );

		$builder = new BoostTemplatesFunctionScoreBuilder( $config, [ NS_MAIN ], false, true, 1 );
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\CustomFieldFunctionScoreBuilder
	 */
	public function testCustomField() {
		$config = new HashSearchConfig( [] );
		$profile = [
			'field' => 'test',
			'factor' => 5,
			'modifier' => 'sqrt',
			'missing' => 1,
		];
		$builder = new CustomFieldFunctionScoreBuilder( $config, 1, $profile );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertTrue( isset( $array['function_score']['functions'][0]['field_value_factor'] ) );
		$this->assertEquals( $profile, $array['function_score']['functions'][0]['field_value_factor'] );
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\ScriptScoreFunctionScoreBuilder
	 */
	public function testScriptScore() {
		$config = new HashSearchConfig( [] );
		$script = "sqrt( doc['incoming_links'].value )";
		$builder = new ScriptScoreFunctionScoreBuilder( $config, 2, $script );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertTrue( isset( $array['function_score']['functions'][0]['script_score'] ) );
		$this->assertEquals( $script, $array['function_score']['functions'][0]['script_score']['script']['source'] );
		$this->assertEquals( 'expression', $array['function_score']['functions'][0]['script_score']['script']['lang'] );
		$this->assertEquals( 2, $array['function_score']['functions'][0]['weight'] );
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\IncomingLinksFunctionScoreBuilder
	 */
	public function testBoostLinks() {
		$builder = new IncomingLinksFunctionScoreBuilder();
		$fScore = new FunctionScoreDecorator();

		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertTrue( isset( $array['function_score']['functions'][0] ) );
		$array = $array['function_score']['functions'][0];
		$this->assertTrue( isset( $array['field_value_factor'] ) );
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\NamespacesFunctionScoreBuilder
	 */
	public function testNamespacesBoost() {
		$settings = [
			'CirrusSearchNamespaceWeights' => [
				NS_MAIN => 2.5,
				NS_PROJECT => 1.3,
				NS_HELP => 3,
			],
			'CirrusSearchDefaultNamespaceWeight' => 0.2,
			'CirrusSearchTalkNamespaceWeight' => 0.25,
		];
		$config = new HashSearchConfig( $settings );

		$namespaceInfo = $this->createMock( NamespaceInfo::class );
		$namespaceInfo->expects( $this->any() )->method( 'isSubject' )->will(
			$this->returnCallback( function ( $ns ) {
				return in_array( $ns, [ NS_MAIN, NS_PROJECT, NS_HELP, NS_MEDIAWIKI ] );
			}
		) );
		$namespaceInfo->expects( $this->any() )->method( 'getSubject' )->with( NS_TALK )->willReturn( NS_MAIN );
		// 5 namespaces in the query generates 5 filters
		$builder = new NamespacesFunctionScoreBuilder( $config, [ NS_MAIN, NS_PROJECT, NS_HELP, NS_MEDIAWIKI, NS_TALK ],
			1, $namespaceInfo );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertCount( 5, $array['function_score']['functions'] );

		// With a single namespace the function score is empty
		$builder = new NamespacesFunctionScoreBuilder( $config, [ 0 ], 1, $namespaceInfo );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertTrue( $fScore->isEmptyFunction() );

		// with 2 namespaces we have 2 functions
		$builder = new NamespacesFunctionScoreBuilder( $config, [ NS_MAIN, NS_HELP ], 1, $namespaceInfo );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertCount( 2, $array['function_score']['functions'] );

		// Test that 2 similar boosts are flattened into the same filter
		$settings = [
			'CirrusSearchNamespaceWeights' => [
				NS_MAIN => 2,
				NS_PROJECT => 2,
				NS_HELP => 3,
			],
		];
		$config = new HashSearchConfig( $settings );
		$builder = new NamespacesFunctionScoreBuilder( $config, [ NS_MAIN, NS_PROJECT, NS_HELP ], 1, $namespaceInfo );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertCount( 2, $array['function_score']['functions'] );

		// Test that a weigth to 1 is ignored
		$settings = [
			'CirrusSearchNamespaceWeights' => [
				NS_MAIN => 2,
				NS_PROJECT => 2,
				NS_HELP => 1,
			],
		];
		$config = new HashSearchConfig( $settings );
		$builder = new NamespacesFunctionScoreBuilder( $config, [ NS_MAIN, NS_PROJECT, NS_HELP ], 1, $namespaceInfo );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$this->assertFalse( $fScore->isEmptyFunction() );
		$array = $fScore->toArray();
		$this->assertCount( 1, $array['function_score']['functions'] );
	}

	/**
	 * @dataProvider provideRescoreProfilesWithFallback
	 * @covers \CirrusSearch\Search\Rescore\RescoreBuilder
	 */
	public function testFallbackProfile( $settings, $namespaces, $expectedFunctionCount ) {
		$config = $this->newHashSearchConfig( $settings + [
			'CirrusSearchIgnoreOnWikiBoostTemplates' => true,
			'CirrusSearchBoostTemplates' => [ 'Good' => 1.3 ]
		] );

		$context = new SearchContext( $config, $namespaces, null, null, null, $this->createCirrusSearchHookRunner() );
		$builder = new RescoreBuilder( $context, $this->createCirrusSearchHookRunner(), $config->get( 'CirrusSearchRescoreProfile' ) );
		$rescore = $builder->build();
		$array = $rescore[0]['query']['rescore_query'];
		$array = $array->toArray();
		$this->assertCount( $expectedFunctionCount, $array['function_score']['functions'] );
	}

	public static function provideRescoreProfilesWithFallback() {
		$defaultChain = [
			'functions' => [
				[ 'type' => 'boostlinks' ]
			]
		];
		$fullChain = [
			'functions' => [
				[ 'type' => 'boostlinks' ],
				[ 'type' => 'templates' ]
			]
		];
		$profile = [
			'ContentNamespaces' => [ 1, 2 ],
			'NamespacesToBeSearchedDefault' => [ 1 => true ],
			'CirrusSearchRescoreProfiles' => [
				'full' => [
					'supported_namespaces' => [ 0, 1 ],
					'fallback_profile' => 'default',
					'rescore' => [
						[
							'window' => 123,
							'type' => 'function_score',
							'function_chain' => 'full',
						]
					]
				],
				'content' => [
					'supported_namespaces' => 'content',
					'fallback_profile' => 'default',
					'rescore' => [
						[
							'window' => 123,
							'type' => 'function_score',
							'function_chain' => 'full',
						]
					]
				],
				'default' => [
					'supported_namespaces' => 'all',
					'rescore' => [
						[
							'window' => 123,
							'type' => 'function_score',
							'function_chain' => 'default',
						]
					]
				]
			],
			'CirrusSearchRescoreFunctionScoreChains' => [
				'full' => $fullChain,
				'default' => $defaultChain
			]
		];
		return [
			'No fallback' => [
				$profile + [ 'CirrusSearchRescoreProfile' => 'full' ],
				[ 0 ],
				2
			],
			'No fallback multi namespace' => [
				$profile + [ 'CirrusSearchRescoreProfile' => 'full' ],
				[ 0, 1 ],
				2
			],
			'No fallback content ns' => [
				$profile + [ 'CirrusSearchRescoreProfile' => 'content' ],
				[ 1, 2 ],
				2
			],
			'Fallback content ns' => [
				$profile + [ 'CirrusSearchRescoreProfile' => 'content' ],
				[ 0, 2 ],
				1
			],
			'Fallback with multiple namespace' => [
				$profile + [ 'CirrusSearchRescoreProfile' => 'full' ],
				[ 0, 2 ],
				1
			],
			'Fallback null ns' => [
				$profile + [ 'CirrusSearchRescoreProfile' => 'full' ],
				null,
				1
			],
		];
	}

	/**
	 * @dataProvider provideRescoreProfilesWithWindowSize
	 * @covers \CirrusSearch\Search\Rescore\RescoreBuilder
	 */
	public function testWindowSizeOverride( $settings, $expected ) {
		$config = $this->newHashSearchConfig( $settings + [ 'CirrusSearchRescoreProfile' => 'default' ] );

		$context = new SearchContext( $config, null, null, null, null, $this->createCirrusSearchHookRunner() );
		$builder = new RescoreBuilder( $context, $this->createCirrusSearchHookRunner(), 'default' );
		$rescore = $builder->build();
		$this->assertEquals( $expected, $rescore[0]['window_size'] );
	}

	public static function provideRescoreProfilesWithWindowSize() {
		$testChain = [
			'functions' => [ [ 'type' => 'boostlinks' ] ]
		];
		return [
			'Overridden' => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 'all',
							'rescore' => [
								[
									'window' => 123,
									'window_size_override' => 'CirrusSearchOverrideWindow',
									'type' => 'function_score',
									'function_chain' => 'test',
								]
							]
						]
					],
					'CirrusSearchOverrideWindow' => 321,
					'CirrusSearchRescoreFunctionScoreChains' => [
						'test' => $testChain
					]
				],
				321
			],
			'Overridden with missing config' => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 'all',
							'rescore' => [
								[
									'window' => 123,
									'window_size_override' => 'CirrusSearchOverrideWindow',
									'type' => 'function_score',
									'function_chain' => 'test',
								]
							]
						]
					],
					'CirrusSearchRescoreFunctionScoreChains' => [
						'test' => $testChain
					]
				],
				123
			],
			'Not overridden' => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 'all',
							'rescore' => [
								[
									'window' => 123,
									'type' => 'function_score',
									'function_chain' => 'test',
								]
							]
						]
					],
					'CirrusSearchRescoreFunctionScoreChains' => [
						'test' => $testChain
					]
				],
				123
			],
		];
	}

	public function termBoostProvider() {
		return [
			"one statement" => [
				1.5,
				[ 'statement_keywords' => [ 'P31=Q123' => 2 ] ],
				[
					[
						'weight' => 3.0,
						'filter' => [ 'term' => [ 'statement_keywords' => 'P31=Q123' ] ]
					]
				]
			],
			"nothing" => [
				2,
				[ 'statement_keywords' => [] ],
				[]
			],
			"nothing 2" => [
				2,
				[],
				[]
			],
			"multiple statements" => [
				0.1,
				[ 'statement_keywords' => [ 'P31=Q1234' => -2, 'P279=Q345' => -7 ] ],
				[
					[
						'weight' => -0.2,
						'filter' => [ 'term' => [ 'statement_keywords' => 'P31=Q1234' ] ]
					],
					[
						'weight' => -0.7,
						'filter' => [ 'term' => [ 'statement_keywords' => 'P279=Q345' ] ]
					],
				]

			],
		];
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\TermBoostScoreBuilder
	 * @covers \CirrusSearch\Search\Rescore\BoostedQueriesFunction
	 * @dataProvider termBoostProvider
	 */
	public function testTermBoosts( $weight, array $settings, array $functions ) {
		$config = new HashSearchConfig( [] );
		$context = new SearchContext( $config, null, null, null, null, $this->createCirrusSearchHookRunner() );
		$builder = new Rescore\TermBoostScoreBuilder( $context, $weight, $settings );
		$fScore = new FunctionScoreDecorator();
		$builder->append( $fScore );
		$array = $fScore->toArray();
		if ( empty( $functions ) ) {
			$this->assertTrue( $fScore->isEmptyFunction() );
		} else {
			$this->assertFalse( $fScore->isEmptyFunction() );
			$this->assertEquals( $functions, $array['function_score']['functions'] );
		}
	}

	/**
	 * @dataProvider provideInvalidRescoreProfiles
	 * @covers \CirrusSearch\Search\Rescore\RescoreBuilder
	 */
	public function testBadRescoreProfile( $settings, $expectedException ) {
		$config = $this->newHashSearchConfig( $settings + [ 'CirrusSearchRescoreProfile' => 'default' ] );

		$context = new SearchContext( $config, null, null, null, null, $this->createCirrusSearchHookRunner() );
		try {
			$builder = new RescoreBuilder( $context, $this->createCirrusSearchHookRunner(), 'default' );
			$builder->build();
			$this->fail( "Expected exception of type: $expectedException" );
		} catch ( \Exception $e ) {
			$this->assertInstanceOf( $expectedException, $e, $e->getTraceAsString() );
		}
	}

	public static function provideInvalidRescoreProfiles() {
		return [
			'Unsupported rescore query type' => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 'all',
							'rescore' => [
								[
									'window' => 123,
									'type' => 'foobar',
								]
							]
						]
					],
				],
				InvalidRescoreProfileException::class
			],
			"Invalid rescore profile: supported_namespaces should be 'all' or an array of namespaces" => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 1,
						]
					],
				],
				InvalidRescoreProfileException::class
			],
			"Invalid rescore profile: fallback_profile is mandatory" => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => [ 0 ],
						]
					],
				],
				InvalidRescoreProfileException::class
			],
			"Unknown fallback profile" => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => [ 0 ],
							'fallback_profile' => 'missing',
						]
					],
				],
				SearchProfileException::class
			],
			"Fallback profile must support all namespaces" => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => [ 0 ],
							'fallback_profile' => 'fallback',
						],
						'fallback' => [
							'supported_namespaces' => [ 3 ],
						]
					],
				],
				InvalidRescoreProfileException::class
			],
			"Unknown rescore function chain" => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 'all',
							'rescore' => [
								[
									'window' => 123,
									'type' => 'function_score',
									'function_chain' => 'test_missing',
								]
							]
						],
					],
					'CirrusSearchRescoreFunctionScoreChains' => [
						'test' => []
					]
				],
				SearchProfileException::class
			],
			"Invalid function chain (none defined)" => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 'all',
							'rescore' => [
								[
									'window' => 123,
									'type' => 'function_score',
									'function_chain' => 'test',
								],
							]
						],
					],
					'CirrusSearchRescoreFunctionScoreChains' => [
						'test' => []
					]
				],
				InvalidRescoreProfileException::class
			],
			"Invalid function score type" => [
				[
					'CirrusSearchRescoreProfiles' => [
						'default' => [
							'supported_namespaces' => 'all',
							'rescore' => [
								[
									'window' => 123,
									'type' => 'function_score',
									'function_chain' => 'test',
								],
							]
						],
					],
					'CirrusSearchRescoreFunctionScoreChains' => [
						'test' => [ 'functions' => [ [ 'type' => 'foobar' ] ] ]
					]
				],
				InvalidRescoreProfileException::class
			],
		];
	}

	/**
	 * @covers \CirrusSearch\Search\Rescore\RescoreBuilder
	 */
	public function testRescoreFunctionChainOverrides() {
		$initialWeight = 4;
		$weight = 7;

		$settings = [
			'CirrusSearchRescoreFunctionScoreChains' => [
				'test' => [
					'functions' => [
						[
							'type' => 'script',
							'script' => '...',
							'weight' => $initialWeight
						],
					],
				],
			],
			'CirrusSearchRescoreProfiles' => [
				'default' => [
					'supported_namespaces' => 'all',
					'rescore' => [
						[
							'window' => 123,
							'type' => 'function_score',
							'function_chain' => 'test',
							'function_chain_overrides' => [
								'functions.0.weight' => $weight,
							]
						]
					]
				]
			]
		];

		$config = $this->newHashSearchConfig( $settings + [
			'CirrusSearchRescoreProfile' => 'default',
		] );

		$context = new SearchContext( $config, [ NS_MAIN, NS_USER ], null, null, null, $this->createCirrusSearchHookRunner() );
		$builder = new RescoreBuilder( $context, $this->createCirrusSearchHookRunner(), 'default' );
		$rescores = $builder->build();
		$this->assertCount( 1, $rescores );
		$query = $rescores[0]['query']['rescore_query']->toArray();
		// Check the weight override was applied
		$this->assertEquals( $weight, $query['function_score']['functions'][0]['weight'] );
	}

	/**
	 * @dataProvider provideRescoreProfilesForSyntaxBasedFallback
	 * @covers \CirrusSearch\Search\Rescore\RescoreBuilder
	 */
	public function testSyntaxBasedFallbackProfile( $settings, $query, $expectedFunctionCount ) {
		$config = $this->newHashSearchConfig( $settings + [
				'CirrusSearchIgnoreOnWikiBoostTemplates' => true,
				'CirrusSearchBoostTemplates' => [ 'Good' => 1.3 ]
			] );

		$searchQuery = SearchQueryBuilder::newFTSearchQueryBuilder( $config, $query,
			$this->namespacePrefixParser(), $this->createCirrusSearchHookRunner() )->build();
		$context = SearchContext::fromSearchQuery( $searchQuery, null, $this->createCirrusSearchHookRunner() );
		$builder = new RescoreBuilder( $context, $this->createCirrusSearchHookRunner(), $config->get( 'CirrusSearchRescoreProfile' ) );
		$rescore = $builder->build();
		$array = $rescore[0]['query']['rescore_query'];
		$array = $array->toArray();
		$this->assertCount( $expectedFunctionCount, $array['function_score']['functions'] );
	}

	public static function provideRescoreProfilesForSyntaxBasedFallback() {
		$defaultChain = [
			'functions' => [
				[ 'type' => 'boostlinks' ]
			]
		];
		$fullChain = [
			'functions' => [
				[ 'type' => 'boostlinks' ],
				[ 'type' => 'templates' ]
			]
		];

		$buildConfig = function ( array $supportedSyntax, array $unsupportedSyntax ) use ( $defaultChain, $fullChain ) {
			return [
				'CirrusSearchRescoreProfile' => 'full',
				'CirrusSearchRescoreProfiles' => [
					'full' => [
						'supported_namespaces' => 'all',
						'unsupported_syntax' => $unsupportedSyntax,
						'supported_syntax' => $supportedSyntax,
						'fallback_profile' => 'default',
						'rescore' => [
							[
								'window' => 123,
								'type' => 'function_score',
								'function_chain' => 'full',
							]
						]
					],
					'default' => [
						'supported_namespaces' => 'all',
						'rescore' => [
							[
								'window' => 123,
								'type' => 'function_score',
								'function_chain' => 'default',
							]
						]
					]
				],
				'CirrusSearchRescoreFunctionScoreChains' => [
					'full' => $fullChain,
					'default' => $defaultChain
				]
			];
		};
		return [
			'No fallback needed' => [
				$buildConfig( [], [] ),
				'foo',
				2
			],
			'should not fallback if only simple bag of words is supported and such query is passed' => [
				$buildConfig( [ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ], [] ),
				'foo',
				2
			],
			'should not fallback if only simple bag of words and phrases are supported and a phrase query is passed' => [
				$buildConfig( [ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS, BasicQueryClassifier::SIMPLE_PHRASE ], [] ),
				'"foo"',
				2
			],
			'should use fallback if only simple bag of words supported and complex query is passed' => [
				$buildConfig( [ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ], [] ),
				'foo AND bar',
				1
			],
			'should use fallback if only complex query is disallowed and such query is passed' => [
				$buildConfig( [], [ BasicQueryClassifier::COMPLEX_QUERY ] ),
				'foo AND bar',
				1
			],
		];
	}

}
