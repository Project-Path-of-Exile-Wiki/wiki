<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\Twitch;

use MediaWiki\Extension\EmbedVideo\EmbedService\AbstractEmbedService;

class Twitch extends AbstractEmbedService {

	/**
	 * @inheritDoc
	 */
	public function getBaseUrl(): string {
		return 'https://player.twitch.tv/?channel=%1$s';
	}

	/**
	 * @inheritDoc
	 */
	public function getAspectRatio(): ?float {
		return 620 / 378;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultWidth(): int {
		return 620;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultHeight(): int {
		return 378;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#twitch\.tv/([\d\w-]+)(?:/\S+?)?#is'
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getIdRegex(): array {
		return [
			'#^([\d\w-]+)$#is'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): string {
		$serverName = self::$config->get( 'ServerName' );
		$urlArgs = $this->urlArgs;

		if ( empty( $urlArgs ) ) {
			// Set the url args to the parent domain
			$urlArgs = "parent=$serverName";
		} else {
			// Break down the url args and inject the parent
			$parsedArgs = [];
			parse_str( $this->getUrlArgs(), $parsedArgs );
			$parsedArgs['parent'] = $serverName;

			$urlArgs = http_build_query( $parsedArgs );
		}

		return sprintf( '%s&%s', sprintf( $this->getBaseUrl(), $this->getId() ), $urlArgs );
	}

	/**
	 * @inheritDoc
	 */
	public function getCSPUrls(): array {
		return [
			'https://player.twitch.tv'
		];
	}
}
