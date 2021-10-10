<?php

namespace CirrusSearch\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use CirrusSearch\WarningCollector;
use Elastica\Query\AbstractQuery;
use Elastica\Query\Ids;
use Message;

/**
 * Filter by a set of page IDs. This is useful for re-validating cached query results.
 * Format: pageid:1|2|3
 */
class PageIdFeature extends SimpleKeywordFeature implements FilterQueryFeature {

	/** Maximum number of IDs allowed. */
	public const MAX_VALUES = 1000;

	/** @inheritDoc */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return $this->doGetFilterQuery( $node->getParsedValue(), $context->getSearchConfig() );
	}

	/** @inheritDoc */
	public function parseValue(
		$key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector
	) {
		$values = explode( '|', $value );
		$validValues = array_filter( $values, function ( $singleValue ) {
			return ctype_digit( $singleValue );
		} );
		$validValues = array_map( function ( $singleValue ) {
			return (int)$singleValue;
		}, array_values( $validValues ) );
		if ( count( $validValues ) < count( $values ) ) {
			$invalidValues = array_values( array_diff( $values, $validValues ) );
			$warningCollector->addWarning( 'cirrussearch-feature-pageid-invalid-id',
				Message::listParam( $invalidValues, 'comma' ), count( $invalidValues ) );
		}

		if ( count( $validValues ) > self::MAX_VALUES ) {
			$warningCollector->addWarning(
				'cirrussearch-feature-too-many-conditions',
				$key,
				self::MAX_VALUES
			);
			$validValues = array_slice( $validValues, 0, self::MAX_VALUES );
		}

		return [ 'pageids' => $validValues ];
	}

	/**
	 * @param array $parsedValue
	 * @param SearchConfig $searchConfig
	 * @return AbstractQuery|null
	 */
	protected function doGetFilterQuery( array $parsedValue, SearchConfig $searchConfig ) {
		if ( !$parsedValue['pageids'] ) {
			return null;
		}
		$documentIds = array_map( [ $searchConfig, 'makeId' ], $parsedValue['pageids'] );
		return new Ids( $documentIds );
	}

	/** @inheritDoc */
	protected function getKeywords() {
		return [ 'pageid' ];
	}

	/** @inheritDoc */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$filter = $this->doGetFilterQuery(
			$this->parseValue( $key, $value, $quotedValue, '', '', $context ),
			$context->getConfig()
		);
		if ( !$filter ) {
			$context->setResultsPossible( false );
		}
		return [ $filter, false ];
	}

}
