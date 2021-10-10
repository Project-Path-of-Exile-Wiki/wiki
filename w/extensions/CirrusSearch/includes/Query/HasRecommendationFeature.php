<?php

namespace CirrusSearch\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use CirrusSearch\Wikimedia\WeightedTagsHooks;
use Elastica\Query\AbstractQuery;
use Elastica\Query\MatchQuery;

/**
 * Filters the result set based on the existing article recommendation.
 * Currently we handle link and image recommendations.
 *
 * Examples:
 *   hasrecommendation:image
 *   hasrecommendation:link|image
 */
class HasRecommendationFeature extends SimpleKeywordFeature implements FilterQueryFeature {

	/**
	 * Limit filtering to 5 recommendation types. Arbitrarily chosen, but should be more
	 * than enough and some sort of limit has to be enforced.
	 */
	public const QUERY_LIMIT = 5;

	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$parsedValue = $this->parseValue( $key, $value, $quotedValue, '', '', $context );
		return [ $this->doGetFilterQuery( $parsedValue ), false ];
	}

	protected function getKeywords() {
		return [ 'hasrecommendation' ];
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $valueDelimiter
	 * @param string $suffix
	 * @param WarningCollector $warningCollector
	 * @return array|false|null
	 */
	public function parseValue( $key, $value, $quotedValue, $valueDelimiter, $suffix,
								WarningCollector $warningCollector ) {
		$recFlags = explode( "|", $value );
		if ( count( $recFlags ) > self::QUERY_LIMIT ) {
			$warningCollector->addWarning(
				'cirrussearch-feature-too-many-conditions',
				$key,
				self::QUERY_LIMIT
			);
			$recFlags = array_slice( $recFlags, 0, self::QUERY_LIMIT );
		}
		return [ 'recommendationflags' => $recFlags ];
	}

	/**
	 * @param array[] $parsedValue
	 * @return AbstractQuery|null
	 */
	private function doGetFilterQuery( array $parsedValue ): ?AbstractQuery {
		$queries = [];
		$fields = [ 'ores_articletopics', WeightedTagsHooks::FIELD_NAME ];
		foreach ( $parsedValue['recommendationflags'] as $recFlag ) {
			foreach ( $fields as $field ) {
				$tagValue = "recommendation." . $recFlag . '/exists';
				$queries[] = ( new MatchQuery() )->setFieldQuery( $field, $tagValue );
			}
		}
		$query = Filters::booleanOr( $queries, false );

		return $query;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ): ?AbstractQuery {
		return $this->doGetFilterQuery( $node->getParsedValue() );
	}

}
