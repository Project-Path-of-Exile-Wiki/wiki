<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\YouTube;

final class YouTubePlaylist extends YouTube {

	protected function getUrlRegex(): array {
		return [
			'#list=([\d\w-]+)(?:&\S+?)?#is'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getBaseUrl(): string {
		return '//www.youtube-nocookie.com/embed/videoseries?list=%1$s';
	}
}
