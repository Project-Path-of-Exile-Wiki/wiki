<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusSearch;
use CirrusSearch\SearchConfig;
use Elastica\Document;
use Elastica\Param;
use SearchEngine;
use SearchIndexField;
use SearchIndexFieldDefinition;

/**
 * Basic ElasticSearch index field
 * @since 1.28
 */
abstract class CirrusIndexField extends SearchIndexFieldDefinition {
	/**
	 * Name of the param on \Elastica\Document that contains
	 * hints about the noop_script handlers.
	 */
	public const DOC_HINT_PARAM = '_cirrus_hints';

	/**
	 * name of the noop handler for multilist
	 */
	public const MULTILIST_HANDLER = 'multilist';

	/**
	 * magic word to instruct the noop plugin to cleanup a particular multilist group
	 */
	public const MULTILIST_DELETE_GROUPING = '__DELETE_GROUPING__';

	/**
	 * Name of the hint as returned by SearchIndexField::getEngineHints()
	 */
	public const NOOP_HINT = 'noop';

	/**
	 * Name of the type in Elastic
	 * @var string
	 */
	protected $typeName = 'unknown';

	/**
	 * @var SearchConfig
	 */
	protected $config;

	/**
	 * Specific mapping flags
	 * @var int
	 */
	protected $mappingFlags;

	/**
	 * @param string $name
	 * @param string $type
	 * @param SearchConfig $config
	 */
	public function __construct( $name, $type, SearchConfig $config ) {
		parent::__construct( $name, $type );
		$this->config = $config;
	}

	/**
	 * Set flags for specific mapping
	 * @param int $flags
	 * @return self
	 */
	public function setMappingFlags( $flags ) {
		$this->mappingFlags = $flags;
		return $this;
	}

	/**
	 * Get mapping for specific search engine
	 * @param SearchEngine $engine
	 * @return array
	 */
	public function getMapping( SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			throw new \LogicException( "Cannot map CirrusSearch fields for another engine." );
		}

		$config = [
			'type' => $this->typeName,
		];
		if ( $this->checkFlag( SearchIndexField::FLAG_NO_INDEX ) ) {
			$config['index'] = false;
		}
		return $config;
	}

	/**
	 * Inspect SearchIndexField::getEngineHints() for indexing hints
	 * and forward them to special metadata in the document.
	 *
	 * @param Document $doc
	 * @param string $fieldName
	 * @param array $hints
	 */
	public static function addIndexingHints( Document $doc, $fieldName, array $hints ) {
		if ( $hints && isset( $hints[self::NOOP_HINT] ) ) {
			self::addNoopHandler( $doc, $fieldName, $hints[self::NOOP_HINT] );
		}
	}

	/**
	 * Add a special metadata to $doc to control the noop_script
	 * @param \Elastica\Param $doc
	 * @param string $field
	 * @param string|array $handler the handler as understood by the super_noop_script
	 */
	public static function addNoopHandler( \Elastica\Param $doc, $field, $handler ) {
		if ( !$doc->hasParam( self::DOC_HINT_PARAM ) ) {
			$doc->setParam( self::DOC_HINT_PARAM,
				[ self::NOOP_HINT => [ $field => $handler ] ] );
		} else {
			$params = $doc->getParam( self::DOC_HINT_PARAM );
			$params[self::NOOP_HINT][$field] = $handler;
			$doc->setParam( self::DOC_HINT_PARAM, $params );
		}
	}

	/**
	 * Get the hint named $hint
	 *
	 * @param \Elastica\Param $doc
	 * @param string $hint name of the hint
	 * @return mixed|null the hint value or null if inexistent
	 */
	public static function getHint( \Elastica\Param $doc, $hint ) {
		if ( $doc->hasParam( self::DOC_HINT_PARAM ) ) {
			$params = $doc->getParam( self::DOC_HINT_PARAM );
			if ( isset( $params[$hint] ) ) {
				return $params[$hint];
			}
		}
		return null;
	}

	/**
	 * Set the hint named $hint
	 *
	 * @param \Elastica\Param $doc
	 * @param string $hint name of the hint
	 * @param mixed $value the hint value
	 */
	public static function setHint( \Elastica\Param $doc, $hint, $value ) {
		$params = [];
		if ( $doc->hasParam( self::DOC_HINT_PARAM ) ) {
			$params = $doc->getParam( self::DOC_HINT_PARAM );
		}
		$params[$hint] = $value;
		$doc->setParam( self::DOC_HINT_PARAM, $params );
	}

	/**
	 * Clear all hints
	 *
	 * @param Param $doc
	 */
	public static function resetHints( Param $doc ) {
		if ( $doc->hasParam( self::DOC_HINT_PARAM ) ) {
			if ( $doc instanceof Document ) {
				self::resetMultiList( $doc );
			}
			$doc->setParam( self::DOC_HINT_PARAM, null );
		}
	}

	/**
	 * Remove multilist __DELETE_GROUPING__ records
	 * @param Document $doc
	 */
	private static function resetMultiList( Document $doc ) {
		$noopHandlers = self::getHint( $doc, self::NOOP_HINT ) ?: [];
		foreach ( $noopHandlers as $field => $handler ) {
			if ( $handler === self::MULTILIST_HANDLER && $doc->has( $field ) ) {
				$data = $doc->get( $field );
				$data = is_array( $data ) ? $data : [ $data ];
				$doc->set(
					$field,
					array_values( array_filter( $data, function ( string $x ) {
							return !str_ends_with( $x, self::MULTILIST_DELETE_GROUPING );
					} ) )
				);
			}
		}
	}
}
