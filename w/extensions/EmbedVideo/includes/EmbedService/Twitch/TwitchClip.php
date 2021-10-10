<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\Twitch;

final class TwitchClip extends Twitch {

	/**
	 * @inheritDoc
	 */
	public function getBaseUrl(): string {
		return 'https://clips.twitch.tv/embed?autoplay=false&clip=%1$s';
	}

	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#twitch\.tv/(?:[\d\w-]+)/(?:clip/)([\d\w-]+)?#is'
		];
	}
}
