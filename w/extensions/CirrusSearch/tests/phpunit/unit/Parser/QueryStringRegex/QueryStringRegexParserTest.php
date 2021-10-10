<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\AST\BooleanClause;
use CirrusSearch\Parser\AST\EmptyQueryNode;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\ParsedBooleanNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Parser\AST\WordsQueryNode;
use CirrusSearch\Parser\FTQueryClassifiersRepository;
use CirrusSearch\Parser\KeywordRegistry;
use CirrusSearch\Parser\ParsedQueryClassifiersRepository;
use CirrusSearch\Parser\QueryParser;
use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\Query\FileNumericFeature;
use CirrusSearch\Query\HasRecommendationFeature;
use CirrusSearch\Query\InCategoryFeature;
use CirrusSearch\Query\PageIdFeature;
use CirrusSearch\Search\Escaper;
use CirrusSearch\SearchConfig;

/**
 * @covers \CirrusSearch\Parser\QueryStringRegex\QueryStringRegexParser
 * @covers \CirrusSearch\Parser\QueryStringRegex\SearchQueryParseException
 */
class QueryStringRegexParserTest extends CirrusTestCase {

	public function testEmpty() {
		$config = new HashSearchConfig( [], [ HashSearchConfig::FLAG_INHERIT ] );

		$parser = $this->buildParser( $config );
		$this->assertEquals( new EmptyQueryNode( 0, 0 ), $parser->parse( '' )->getRoot() );
	}

	public function testLastUnbalanced() {
		$config = new HashSearchConfig( [] );

		$parser = $this->buildParser( $config );
		/** @var ParsedBooleanNode $parsedNode */
		$parsedNode = $parser->parse( 'test "' )->getRoot();
		$this->assertInstanceOf( ParsedBooleanNode::class, $parsedNode );
		$this->assertCount( 2, $parsedNode->getClauses() );
		/** @var PhraseQueryNode $phraseNode */
		$phraseNode = $parsedNode->getClauses()[1]->getNode();
		$this->assertInstanceOf( PhraseQueryNode::class, $phraseNode );
		$this->assertSame( '', $phraseNode->getPhrase() );
	}

	/**
	 * @dataProvider provideMaxLength
	 */
	public function testMaxLength( int $maxQueryLen, string $query, bool $expectedToPass ) {
		$config = new HashSearchConfig( [] );

		$registry = new class( $config ) implements KeywordRegistry {
			private $config;

			public function __construct( SearchConfig $config ) {
				$this->config = $config;
			}

			public function getKeywords() {
				return [
					new InCategoryFeature( $this->config ),
					new ArticleTopicFeature(),
					new PageIdFeature(),
					new HasRecommendationFeature(),
				];
			}
		};
		$parser = new QueryStringRegexParser( $registry, new Escaper( 'en', false ), 'all',
			new FTQueryClassifiersRepository( $config, $this->createCirrusSearchHookRunner() ),
			$this->namespacePrefixParser(), $maxQueryLen );

		try {
			$parser->parse( $query );
			if ( !$expectedToPass ) {
				$this->fail( 'Expected to fail' );
			}
			$this->assertTrue( true );
		} catch ( SearchQueryParseException $e ) {
			if ( $expectedToPass ) {
				$this->fail( 'Expected to pass, failed with' . $e->asStatus()->__toString() );
			}
			$hasMessage = $e->asStatus()->hasMessage( 'cirrussearch-query-too-long' )
				|| $e->asStatus()->hasMessage( 'cirrussearch-query-too-long-with-exemptions' );
			$this->assertTrue( $hasMessage, 'Unexpected error' );
		}
	}

	public function provideMaxLength() {
		// return value: [ length limit, query, expected to pass? ]
		yield [ 10, str_repeat( 'a', 10 ), true ];
		yield [ 10, str_repeat( 'a', 11 ), false ];

		foreach ( [ 'incategory', 'articletopic', 'pageid' ] as $exemptedKeyword ) {
			yield [ 10, "$exemptedKeyword:test " . str_repeat( 'a', 9 ), true ];
			yield [ 10, "-$exemptedKeyword:test " . str_repeat( 'a', 9 ), true ];
			yield [ 10, "$exemptedKeyword:test " . str_repeat( 'a', 10 ), false ];
			yield [ 10, "-$exemptedKeyword:test " . str_repeat( 'a', 10 ), false ];
		}

		yield [ 10, 'hasrecommendation:' . str_repeat( 'a', 9 ), false ];
	}

	public function testHardLimitOnQueryLength() {
		// Test that even if we allow more than the hard limit, the hard limit is always applied because evaluated prior any parsing steps
		$config = new HashSearchConfig( [ 'CirrusSearchMaxFullTextQueryLength' => QueryStringRegexParser::QUERY_LEN_HARD_LIMIT * 2 ] );

		$parser = $this->buildParser( $config );
		/** @var ParsedBooleanNode $parsedNode */
		try {
			$parser->parse( str_repeat( "a", QueryStringRegexParser::QUERY_LEN_HARD_LIMIT + 1 ) );
			$this->fail( "Expected exception" );
		} catch ( SearchQueryParseException $e ) {
			$this->assertEquals( $e->asStatus(),
				\Status::newFatal( 'cirrussearch-query-too-long',
					QueryStringRegexParser::QUERY_LEN_HARD_LIMIT + 1,
					QueryStringRegexParser::QUERY_LEN_HARD_LIMIT ) );
		}
	}

	/**
	 * @dataProvider provideEscapedQueries
	 */
	public function testT266163( string $query, array $expected ) {
		$parser = new QueryStringRegexParser(
			new class () implements KeywordRegistry {
				public function getKeywords() {
					return [ new FileNumericFeature() ];
				}
			},
			$this->createMock( Escaper::class ),
			'all',
			$this->createMock( ParsedQueryClassifiersRepository::class ),
			$this->namespacePrefixParser(),
			null
		);

		$parsedQuery = $parser->parse( $query )->getRoot();
		$this->assertInstanceOf( ParsedBooleanNode::class, $parsedQuery );
		$this->assertSame( $expected, array_map( function ( BooleanClause $clause ) {
			return get_class( $clause->getNode() );
		}, $parsedQuery->getClauses() ) );
	}

	public function provideEscapedQueries() {
		return [
			'escaped space does not disable keyword' => [
				'foo\ filew:100 fileh:200',
				[ WordsQueryNode::class, KeywordFeatureNode::class, KeywordFeatureNode::class ]
			],
			'this space is not escaped' => [
				'foo\\ filew:100 fileh:200',
				[ WordsQueryNode::class, KeywordFeatureNode::class, KeywordFeatureNode::class ]
			],
			'not a keyword because of the missing space' => [
				'foo\filew:100 fileh:200',
				[ WordsQueryNode::class, KeywordFeatureNode::class ]
			],
		];
	}

	/**
	 * @param SearchConfig $config
	 * @return QueryParser
	 */
	public function buildParser( $config ) {
		$parser = $this->createNewFullTextQueryParser( $config );
		$this->assertInstanceOf( QueryStringRegexParser::class, $parser );
		return $parser;
	}

}
