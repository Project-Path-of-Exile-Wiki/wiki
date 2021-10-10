<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService\YouTube;

use MediaWiki\Extension\EmbedVideo\EmbedService\AbstractEmbedService;

class YouTube extends AbstractEmbedService {
	/**
	 * @inheritDoc
	 */
	protected $additionalIframeAttributes = [
		'modestbranding' => 1,
	];

	/**
	 * @inheritDoc
	 */
	public function getAspectRatio(): ?float {
		return 16 / 9;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultWidth(): int {
		return 640;
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultHeight(): int {
		return 360;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#v=([\d\w-]+)(?:&\S+?)?#is',
			'#youtu\.be/([\d\w-]+)#is'
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
	public function getBaseUrl(): string {
		return '//www.youtube-nocookie.com/embed/%1$s';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): string {
		if ( $this->getUrlArgs() !== false ) {

			return sprintf( '%s?%s', sprintf( $this->getBaseUrl(), $this->getId() ), $this->getUrlArgs() );
		}

		return parent::getUrl();
	}

	/**
	 * @inheritDoc
	 */
	public function getCSPUrls(): array {
		return [
			'https://www.youtube-nocookie.com',
			'https://i.ytimg.com'
		];
	}
}
