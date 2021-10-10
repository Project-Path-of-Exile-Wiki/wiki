<?php

class RSSData {
	public $error;
	public $items;

	/**
	 * Constructor, takes a DOMDocument and returns an array of parsed items.
	 * @param DOMDocument $xml the pre-parsed XML Document
	 */
	public function __construct( $xml ) {
		if ( !( $xml instanceof DOMDocument ) ) {
			$this->error = "Not passed DOMDocument object.";
			return;
		}
		$xpath = new DOMXPath( $xml );

		// namespace-safe method to find all elements
		$items = $xpath->query( "//*[local-name() = 'item']" );

		if ( $items->length === 0 ) {
			$items = $xpath->query( "//*[local-name() = 'entry']" );
		}

		if ( $items->length === 0 ) {
			$this->error = 'No RSS/ATOM items found.';
			return;
		}

		foreach ( $items as $item ) {
			$bit = [];
			foreach ( $item->childNodes as $n ) {
				$name = $this->rssTokenToName( $n->nodeName );
				if ( $name != null ) {
					/**
					 * Because for DOMElements the nodeValue is just
					 * the text of the containing element, without any
					 * tags, it makes this a safe, if unattractive,
					 * value to use. If you want to allow people to
					 * mark up their RSS, some more precautions are
					 * needed.
					 */
					$bit[$name] = trim( $n->nodeValue );
				}
			}
			$this->items[] = $bit;
		}
	}

	/**
	 * Return a string that will be used to map RSS elements that
	 * contain similar data (e.g. dc:date, date, and pubDate) to the
	 * same array key.  This works on WordPress feeds as-is, but it
	 * probably needs a way to concert dc:date format dates to be the
	 * same as pubDate.
	 *
	 * @param string $name name of the element we have
	 * @return string|null Name to map it to
	 */
	protected function rssTokenToName( $name ) {
		$tokenNames = [
			'dc:date' => 'date',
			'pubDate' => 'date',
			'updated' => 'date',
			'dc:creator' => 'author',
			'summary' => 'description',
			'content:encoded' => 'encodedContent',
			'category' => null,
			'comments' => null,
			'feedburner:origLink' => null,
			'slash:comments' => null,
			'slash:department' => null,
			'slash:hit_parade' => null,
			'slash:section' => null,
			'wfw:commentRss' => null,
		];

		if ( array_key_exists( $name, $tokenNames ) ) {
			return $tokenNames[ $name ];
		}

		return $name;
	}

}
