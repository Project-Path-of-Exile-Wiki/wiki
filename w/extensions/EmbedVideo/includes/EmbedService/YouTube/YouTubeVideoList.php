<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\YouTube;

final class YouTubeVideoList extends YouTube {
	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#playlist=([\d\w-]+)(?:&\S+?)?#is'
		];
	}
}
