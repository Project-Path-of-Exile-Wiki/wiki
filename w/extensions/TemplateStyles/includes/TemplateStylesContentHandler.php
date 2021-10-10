<?php

/**
 * @file
 * @license GPL-2.0-or-later
 */

/**
 * Content handler for sanitized CSS
 */
class TemplateStylesContentHandler extends CodeContentHandler {

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = 'sanitized-css' ) {
		parent::__construct( $modelId, [ CONTENT_FORMAT_CSS ] );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return TemplateStylesContent::class;
	}
}
