<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\Twitch;

final class TwitchVod extends Twitch {

	/**
	 * @inheritDoc
	 */
	public function getBaseUrl(): string {
		return 'https://player.twitch.tv/?autoplay=false&video=%1$s';
	}

	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#twitch\.tv/videos/([\d\w-]+)(?:/\S+?)?#is'
		];
	}
}
