<?php

namespace CirrusSearch\Search;

use Title;

class ArrayCirrusSearchResult extends CirrusSearchResult {
	public const DOC_ID = 'doc_id';
	public const SCORE = 'score';
	public const EXPLANATION = 'explanation';
	public const TEXT_SNIPPET = 'text_snippet';
	public const TITLE_SNIPPET = 'title_snippet';
	public const REDIRECT_SNIPPET = 'redirect_snippet';
	public const REDIRECT_TITLE = 'redirect_title';
	public const SECTION_SNIPPET = 'section_snippet';
	public const SECTION_TITLE = 'section_title';
	public const CATEGORY_SNIPPET = 'category_snippet';
	public const TIMESTAMP = 'timestamp';
	public const WORD_COUNT = 'word_count';
	public const BYTE_SIZE = 'byte_size';
	public const INTERWIKI_NAMESPACE_TEXT = 'interwiki_namespace_text';
	public const IS_FILE_MATCH = 'is_file_match';
	public const EXTRA_FIELDS = 'extra_fields';

	/**
	 * @var array
	 */
	private $data;

	public function __construct( Title $title, array $data ) {
		parent::__construct( $title );
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	public function getDocId() {
		return $this->data[self::DOC_ID];
	}

	/**
	 * @return float
	 */
	public function getScore() {
		return $this->data[self::SCORE] ?? 0.0;
	}

	/**
	 * @return array|null
	 */
	public function getExplanation() {
		return $this->data[self::EXPLANATION] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getTextSnippet( $terms = [] ) {
		return $this->data[self::TEXT_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitleSnippet() {
		return $this->data[self::TITLE_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getRedirectSnippet() {
		return $this->data[self::REDIRECT_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getRedirectTitle() {
		return $this->data[self::REDIRECT_TITLE] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getSectionSnippet() {
		return $this->data[self::SECTION_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getSectionTitle() {
		return $this->data[self::SECTION_TITLE] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getCategorySnippet() {
		return $this->data[self::CATEGORY_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getTimestamp() {
		$ts = $this->data[self::TIMESTAMP] ?? null;
		return $ts !== null ? $ts->getTimestamp( TS_MW ) : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getWordCount() {
		return $this->data[self::WORD_COUNT] ?? 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getByteSize() {
		return $this->data[self::BYTE_SIZE] ?? 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getInterwikiPrefix() {
		return $this->getTitle()->getInterwiki();
	}

	/**
	 * @inheritDoc
	 */
	public function getInterwikiNamespaceText() {
		return $this->data[self::INTERWIKI_NAMESPACE_TEXT] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function isFileMatch() {
		return $this->data[self::IS_FILE_MATCH] ?? false;
	}

	/**
	 * @return array[]
	 */
	public function getExtensionData() {
		$extensionData = parent::getExtensionData();
		if ( isset( $this->data[self::EXTRA_FIELDS] ) ) {
			$extensionData[self::EXTRA_FIELDS] = $this->data[self::EXTRA_FIELDS];
		}
		return $extensionData;
	}
}
