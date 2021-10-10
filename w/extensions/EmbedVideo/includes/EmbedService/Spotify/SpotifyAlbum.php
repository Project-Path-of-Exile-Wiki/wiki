<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\Spotify;

use MediaWiki\Extension\EmbedVideo\EmbedService\AbstractEmbedService;

class SpotifyAlbum extends AbstractEmbedService {
	protected $additionalIframeAttributes = [
		'allow' => 'encrypted-media',
	];

	/**
	 * @inheritDoc
	 */
	public function getBaseUrl(): string {
		return 'https://open.spotify.com/embed/album/%1$s';
	}

	/**
	 * @inheritDoc
	 */
	public function getAspectRatio(): ?float {
		return 0.7895;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultWidth(): int {
		return 300;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultHeight(): int {
		return 380;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#open\.spotify\.com/album/([a-zA-Z0-9]+)#is',
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getIdRegex(): array {
		return [
			'#^([a-zA-Z0-9]+)$#is'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getCSPUrls(): array {
		return [
			'https://open.spotify.com'
		];
	}
}
