<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService;

/**
 * Classes implementing this interface retrieve their html by calling the OEmbed endpoint
 */
interface OEmbedServiceInterface {
	/**
	 * This must return the complete url to the OEmbed endpoint, including the video id, etc.
	 *
	 * @return string
	 */
	public function getUrl(): string;
}
