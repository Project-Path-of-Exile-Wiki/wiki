<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService;

final class ArchiveOrg extends AbstractEmbedService {

	/**
	 * @inheritDoc
	 */
	public function getBaseUrl(): string {
		return '//archive.org/embed/%1$s';
	}

	/**
	 * @inheritDoc
	 */
	public function getAspectRatio(): ?float {
		return 640 / 493;
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
		return 493;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUrlRegex(): array {
		return [
			'#archive\.org/(?:details|embed)/([\d\w\-_][^/\?\#]+)#is'
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getIdRegex(): array {
		return [
			'#^([\d\w\-_][^/\?\#]+)$#is'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getCSPUrls(): array {
		return [
			'https://archive.org'
		];
	}
}
