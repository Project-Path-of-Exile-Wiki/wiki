<?php

class MetaDescriptionTagHooks {
	/**
	 * Sets up the MetaDescriptionTag Parser hook and system messages
	 *
	 * @param Parser $parser
	 *
	 * @return bool true
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'metadesc', [ __CLASS__, 'renderMetaDescriptionTag' ] );

		return true;
	}


	/**
	 * Renders the <metadesc> tag.
	 *
	 * @param String $text The description to output
	 * @param array $params Attributes specified for the tag. Should be an empty array
	 * @param Parser $parser Reference to currently running parser
	 *
	 * @return String Always empty (because we don't output anything to the text).
	 */
	public static function renderMetaDescriptionTag( $text, $params, Parser $parser, PPFrame $frame ) {
		// Short-circuit with error message if content is not specified.
		if ( !isset( $text ) ) {
			$errorText = wfMessage( 'metadescriptiontag-missing-content' )->inContentLanguage(
			)->text();

			return Html::element( 'div', [ 'class' => 'errorbox' ], $errorText );
		}

		$parser->getOutput()->setExtensionData( 'metaDescription', trim( $text ) );

		return '';
	}


	public static function onOutputPageParserOutput( OutputPage &$out, ParserOutput $parseroutput ) {
		$metaDescription = $parseroutput->getExtensionData( 'metaDescription' );
		if ( !empty( $metaDescription ) ) {
			$out->addMeta( 'description', htmlspecialchars( $metaDescription ) );
		}

		return true;
	}
}

