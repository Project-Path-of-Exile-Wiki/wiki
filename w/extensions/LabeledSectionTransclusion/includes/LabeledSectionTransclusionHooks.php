<?php

class LabeledSectionTransclusionHooks implements \MediaWiki\Hook\ParserFirstCallInitHook {

	/**
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'section', [ LabeledSectionTransclusion::class, 'noop' ] );
		// Register the localized version of <section> as a noop as well
		$localName = self::getLocalName( 'section', $parser->getContentLanguage()->getCode() );
		if ( $localName !== null ) {
			$parser->setHook( $localName, [ LabeledSectionTransclusion::class, 'noop' ] );
		}
		$parser->setFunctionHook(
			'lst', [ LabeledSectionTransclusion::class, 'pfuncIncludeObj' ], Parser::SFH_OBJECT_ARGS
		);
		$parser->setFunctionHook(
			'lstx', [ LabeledSectionTransclusion::class, 'pfuncExcludeObj' ], Parser::SFH_OBJECT_ARGS
		);
		$parser->setFunctionHook( 'lsth', [ LabeledSectionTransclusion::class, 'pfuncIncludeHeading' ] );
	}

	/**
	 * MediaWiki supports localisation for the three kinds of magic words,
	 * such as variable {{NAME}}, behaviours __NAME__, and parser functions
	 * {{#name}}, but it does not support localisation of tag hooks, such
	 * as <name>. Work around that limitation by performing the localisation
	 * at run-time when calling Parser::setHook().
	 */
	private const HOOK_TRANSLATION = [
		'de' => [
			// Tag name
			'section' => 'Abschnitt',
			// Tag attributes
			'begin' => 'Anfang',
			'end' => 'Ende',
		],
		'he' => [
			'section' => 'קטע',
			'begin' => 'התחלה',
			'end' => 'סוף',
		],
		'pt' => [
			'section' => 'trecho',
			'begin' => 'começo',
			'end' => 'fim',
		],
	];

	/**
	 * Get local name for tag or tag attribute
	 * @param string $key
	 * @param string $lang
	 * @return string|null
	 */
	public static function getLocalName( $key, $lang ) {
		return self::HOOK_TRANSLATION[$lang][$key] ?? null;
	}

}
