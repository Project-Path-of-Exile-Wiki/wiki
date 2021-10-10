<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService;

use ConfigException;
use MediaWiki\Extension\EmbedVideo\OEmbed;
use MediaWiki\MediaWikiServices;
use UnexpectedValueException;

final class EmbedHtmlFormatter {
	/**
	 * Generates the iframe html
	 *
	 * @param AbstractEmbedService $service
	 * @return string
	 */
	public static function toHtml( AbstractEmbedService $service ): string {
		if ( $service instanceof OEmbedServiceInterface ) {
			try {
				$data = OEmbed::newFromRequest( $service->getUrl() );
				return $data->getHtml();
			} catch ( UnexpectedValueException $e ) {
				return $e->getMessage();
			}
		}

		$attributes = $service->getIframeAttributes();
		$attributes['width'] = $service->getWidth();
		$attributes['height'] = $service->getHeight();

		$srcType = 'src';
		try {
			$consent = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'EmbedVideo' )->get( 'EmbedVideoRequireConsent' );
			if ( $consent === true ) {
				$srcType = 'data-src';
			}
		} catch ( ConfigException $e ) {
			//
		}

		$attributes[$srcType] = $service->getUrl();

		$out = array_map( static function ( $key, $value ) {
			return sprintf( '%s="%s"', $key, $value );
		}, array_keys( $attributes ), $attributes );

		return sprintf( '<iframe %s></iframe>', implode( ' ', $out ) );
	}
}
