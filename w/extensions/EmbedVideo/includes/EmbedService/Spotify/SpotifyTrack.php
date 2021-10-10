<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\Spotify;

final class SpotifyTrack extends SpotifyAlbum {
	/**
	 * @inheritDoc
	 */
	public function getBaseUrl(): string {
		return 'https://open.spotify.com/embed/track/%1$s';
	}

	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#open\.spotify\.com/track/([a-zA-Z0-9]+)#is',
		];
	}
}
