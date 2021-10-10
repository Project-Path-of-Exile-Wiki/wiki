<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\YouTube;

use MediaWiki\Extension\EmbedVideo\EmbedService\OEmbedServiceInterface;

/**
 * This should merely be an example
 */
final class YouTubeOEmbed extends YouTube implements OEmbedServiceInterface {
	/**
	 * @inheritDoc
	 */
	public function getUrl(): string {
		return sprintf(
			'https://www.youtube.com/oembed?url=%s&width=%d&maxwidth=%d',
			htmlentities( sprintf( 'https://www.youtube.com/watch?v=%s', $this->getId() ), ENT_QUOTES ),
			$this->getWidth(),
			$this->getHeight(),
		);
	}
}
