<?php

/**
 * @file
 * @license GPL-2.0-or-later
 */

use MediaWiki\Revision\SlotRecord;
use Wikimedia\CSS\Grammar\CheckedMatcher;
use Wikimedia\CSS\Grammar\GrammarMatch;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Objects\ComponentValue;
use Wikimedia\CSS\Objects\ComponentValueList;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Parser\Parser as CSSParser;
use Wikimedia\CSS\Sanitizer\FontFeatureValuesAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\KeyframesAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\MediaAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\NamespaceAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\PageAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\Sanitizer;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;
use Wikimedia\CSS\Sanitizer\StyleRuleSanitizer;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;
use Wikimedia\CSS\Sanitizer\SupportsAtRuleSanitizer;

/**
 * TemplateStyles extension hooks
 */
class TemplateStylesHooks {

	/** @var MatcherFactory|null */
	private static $matcherFactory = null;

	/** @var Sanitizer[] */
	private static $sanitizers = [];

	/** @var (false|Token[])[] */
	private static $wrappers = [];

	/**
	 * @return Config
	 * @codeCoverageIgnore
	 */
	public static function getConfig() {
		return \MediaWiki\MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'templatestyles' );
	}

	/**
	 * @return MatcherFactory
	 * @codeCoverageIgnore
	 */
	private static function getMatcherFactory() {
		if ( !self::$matcherFactory ) {
			self::$matcherFactory = new TemplateStylesMatcherFactory(
				self::getConfig()->get( 'TemplateStylesAllowedUrls' )
			);
		}
		return self::$matcherFactory;
	}

	/**
	 * Validate an extra wrapper-selector
	 * @param string $wrapper
	 * @return ComponentValue[]|false Representation of the selector, or false on failure
	 */
	private static function validateExtraWrapper( $wrapper ) {
		if ( !isset( self::$wrappers[$wrapper] ) ) {
			$cssParser = CSSParser::newFromString( $wrapper );
			$components = $cssParser->parseComponentValueList();
			if ( $cssParser->getParseErrors() ) {
				$match = false;
			} else {
				$match = self::getMatcherFactory()->cssSimpleSelectorSeq()
					->matchAgainst( $components, [ 'mark-significance' => true ] );
			}
			self::$wrappers[$wrapper] = $match ? $components->toComponentValueArray() : false;
		}
		return self::$wrappers[$wrapper];
	}

	/**
	 * @param string $class Class to limit selectors to
	 * @param string|null $extraWrapper Extra selector to limit selectors to
	 * @return Sanitizer
	 */
	public static function getSanitizer( $class, $extraWrapper = null ) {
		$key = $extraWrapper !== null ? "$class $extraWrapper" : $class;
		if ( !isset( self::$sanitizers[$key] ) ) {
			$config = self::getConfig();
			$matcherFactory = self::getMatcherFactory();

			$disallowedProperties = $config->get( 'TemplateStylesDisallowedProperties' );
			if ( $disallowedProperties === [] ) {
				// Fallback to deprecated $wgTemplateStylesPropertyBlacklist
				$disallowedProperties = $config->get( 'TemplateStylesPropertyBlacklist' );
				if ( $disallowedProperties !== [] ) {
					wfDeprecated(
						'$wgTemplateStylesPropertyBlacklist is deprecated and has a value set. ' .
						'Please use $wgTemplateStylesDisallowedProperties instead.'
					);
				}
			}

			$propertySanitizer = new StylePropertySanitizer( $matcherFactory );
			$propertySanitizer->setKnownProperties( array_diff_key(
				$propertySanitizer->getKnownProperties(),
				array_flip( $disallowedProperties )
			) );
			Hooks::run( 'TemplateStylesPropertySanitizer', [ &$propertySanitizer, $matcherFactory ] );

			$htmlOrBodySimpleSelectorSeqMatcher = new CheckedMatcher(
				$matcherFactory->cssSimpleSelectorSeq(),
				function ( ComponentValueList $values, GrammarMatch $match, array $options ) {
					foreach ( $match->getCapturedMatches() as $m ) {
						if ( $m->getName() !== 'element' ) {
							continue;
						}
						$str = (string)$m;
						return $str === 'html' || $str === 'body';
					}
					return false;
				}
			);

			$prependSelectors = [
				new Token( Token::T_DELIM, '.' ),
				new Token( Token::T_IDENT, $class ),
			];
			if ( $extraWrapper !== null ) {
				$extraComponentValues = self::validateExtraWrapper( $extraWrapper );
				if ( !$extraComponentValues ) {
					throw new InvalidArgumentException( "Invalid value for \$extraWrapper: $extraWrapper" );
				}
				$prependSelectors = array_merge(
					$prependSelectors,
					[ new Token( Token::T_WHITESPACE, [ 'significant' => true ] ) ],
					$extraComponentValues
				);
			}

			$disallowedAtRules = $config->get( 'TemplateStylesDisallowedAtRules' );
			if ( $disallowedAtRules === [] ) {
				// Fallback to deprecated $wgTemplateStylesAtRuleBlacklist
				$disallowedAtRules = $config->get( 'TemplateStylesAtRuleBlacklist' );
				if ( $disallowedAtRules !== [] ) {
					wfDeprecated(
						'$wgTemplateStylesAtRuleBlacklist is deprecated and has a value set. ' .
						'Please use $wgTemplateStylesDisallowedAtRules instead.'
					);
				}
			}

			$ruleSanitizers = [
				'styles' => new StyleRuleSanitizer(
					$matcherFactory->cssSelectorList(),
					$propertySanitizer,
					[
						'prependSelectors' => $prependSelectors,
						'hoistableComponentMatcher' => $htmlOrBodySimpleSelectorSeqMatcher,
					]
				),
				'@font-face' => new TemplateStylesFontFaceAtRuleSanitizer( $matcherFactory ),
				'@font-feature-values' => new FontFeatureValuesAtRuleSanitizer( $matcherFactory ),
				'@keyframes' => new KeyframesAtRuleSanitizer( $matcherFactory, $propertySanitizer ),
				'@page' => new PageAtRuleSanitizer( $matcherFactory, $propertySanitizer ),
				'@media' => new MediaAtRuleSanitizer( $matcherFactory->cssMediaQueryList() ),
				'@supports' => new SupportsAtRuleSanitizer( $matcherFactory, [
					'declarationSanitizer' => $propertySanitizer,
				] ),
			];
			$ruleSanitizers = array_diff_key( $ruleSanitizers, array_flip( $disallowedAtRules ) );
			if ( isset( $ruleSanitizers['@media'] ) ) {
				// In case @media was disallowed
				$ruleSanitizers['@media']->setRuleSanitizers( $ruleSanitizers );
			}
			if ( isset( $ruleSanitizers['@supports'] ) ) {
				// In case @supports was disallowed
				$ruleSanitizers['@supports']->setRuleSanitizers( $ruleSanitizers );
			}

			$allRuleSanitizers = $ruleSanitizers + [
				// Omit @import, it's not secure. Maybe someday we'll make an "@-mw-import" or something.
				'@namespace' => new NamespaceAtRuleSanitizer( $matcherFactory ),
			];
			$allRuleSanitizers = array_diff_key( $allRuleSanitizers, $disallowedAtRules );
			$sanitizer = new StylesheetSanitizer( $allRuleSanitizers );
			Hooks::run( 'TemplateStylesStylesheetSanitizer',
				[ &$sanitizer, $propertySanitizer, $matcherFactory ]
			);
			self::$sanitizers[$key] = $sanitizer;
		}
		return self::$sanitizers[$key];
	}

	/**
	 * Update $wgTextModelsToParse
	 */
	public static function onRegistration() {
		// This gets called before ConfigFactory is set up, so I guess we need
		// to use globals.
		global $wgTextModelsToParse, $wgTemplateStylesAutoParseContent;

		if ( in_array( CONTENT_MODEL_CSS, $wgTextModelsToParse, true ) &&
			$wgTemplateStylesAutoParseContent
		) {
			$wgTextModelsToParse[] = 'sanitized-css';
		}
	}

	/**
	 * Add `<templatestyles>` to the parser.
	 * @param Parser $parser Parser object being cleared
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'templatestyles', [ __CLASS__, 'handleTag' ] );
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		$parser->extTemplateStylesCache = new MapCacheLRU( 100 ); // 100 is arbitrary
	}

	/**
	 * Set the default content model to 'sanitized-css' when appropriate.
	 * @param Title $title the Title in question
	 * @param string &$model The model name
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( $title, &$model ) {
		// Allow overwriting attributes with config settings.
		// Attributes can not use namespaces as keys, as processing them does not preserve
		// integer keys.
		$enabledNamespaces = self::getConfig()->get( 'TemplateStylesNamespaces' ) +
			array_fill_keys(
				ExtensionRegistry::getInstance()->getAttribute( 'TemplateStylesNamespaces' ),
				true
			);

		if ( !empty( $enabledNamespaces[$title->getNamespace()] ) &&
			$title->isSubpage() && substr( $title->getText(), -4 ) === '.css'
		) {
			$model = 'sanitized-css';
			return false;
		}
		return true;
	}

	/**
	 * Edit our CSS content model like core's CSS
	 * @param Title $title Title being edited
	 * @param string &$lang CodeEditor language to use
	 * @param string $model Content model
	 * @param string $format Content format
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( $title, &$lang, $model, $format ) {
		if ( $model === 'sanitized-css' && self::getConfig()->get( 'TemplateStylesUseCodeEditor' ) ) {
			$lang = 'css';
			return false;
		}
		return true;
	}

	/**
	 * Clear our cache when the parser is reset
	 * @param Parser $parser
	 */
	public static function onParserClearState( Parser $parser ) {
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		$parser->extTemplateStylesCache->clear();
	}

	/**
	 * Parser hook for `<templatestyles>`
	 * @param string $text Contents of the tag (ignored).
	 * @param string[] $params Tag attributes
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML
	 * @suppress SecurityCheck-XSS
	 */
	public static function handleTag( $text, $params, $parser, $frame ) {
		if ( self::getConfig()->get( 'TemplateStylesDisable' ) ) {
			return '';
		}

		if ( !isset( $params['src'] ) || trim( $params['src'] ) === '' ) {
			return self::formatTagError( $parser, [ 'templatestyles-missing-src' ] );
		}

		$extraWrapper = null;
		if ( isset( $params['wrapper'] ) && trim( $params['wrapper'] ) !== '' ) {
			$extraWrapper = trim( $params['wrapper'] );
			if ( !self::validateExtraWrapper( $extraWrapper ) ) {
				return self::formatTagError( $parser, [ 'templatestyles-invalid-wrapper' ] );
			}
		}

		// Default to the Template namespace because that's the most likely
		// situation. We can't allow for subpage syntax like src="/styles.css"
		// or the like, though, because stuff like substing and Parsoid would
		// wind up wanting to make that relative to the wrong page.
		$title = Title::newFromText( $params['src'], NS_TEMPLATE );
		if ( !$title ) {
			return self::formatTagError( $parser, [ 'templatestyles-invalid-src' ] );
		}

		$revRecord = $parser->fetchCurrentRevisionRecordOfTitle( $title );

		// It's not really a "template", but it has the same implications
		// for needing reparse when the stylesheet is edited.
		$parser->getOutput()->addTemplate(
			$title,
			$title->getArticleId(),
			$revRecord ? $revRecord->getId() : null
		);

		$content = $revRecord ? $revRecord->getContent( SlotRecord::MAIN ) : null;
		if ( !$content ) {
			$titleText = $title->getPrefixedText();
			return self::formatTagError( $parser, [
				'templatestyles-bad-src-missing',
				$titleText,
				wfEscapeWikiText( $titleText )
			] );
		}
		if ( !$content instanceof TemplateStylesContent ) {
			$titleText = $title->getPrefixedText();
			return self::formatTagError( $parser, [
				'templatestyles-bad-src',
				$titleText,
				wfEscapeWikiText( $titleText ),
				ContentHandler::getLocalizedName( $content->getModel() )
			] );
		}

		// If the revision actually has an ID, cache based on that.
		// Otherwise, cache by hash.
		if ( $revRecord->getId() ) {
			$cacheKey = 'r' . $revRecord->getId();
		} else {
			$cacheKey = sha1( $content->getNativeData() );
		}

		// Include any non-default wrapper class in the cache key too
		$wrapClass = $parser->getOptions()->getWrapOutputClass();
		if ( $wrapClass === false ) { // deprecated
			$wrapClass = 'mw-parser-output';
		}
		if ( $wrapClass !== 'mw-parser-output' || $extraWrapper !== null ) {
			$cacheKey .= '/' . $wrapClass;
			if ( $extraWrapper !== null ) {
				$cacheKey .= '/' . $extraWrapper;
			}
		}

		// Already cached?
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		if ( $parser->extTemplateStylesCache->has( $cacheKey ) ) {
			/** @phan-suppress-next-line PhanUndeclaredProperty */
			return $parser->extTemplateStylesCache->get( $cacheKey );
		}

		$targetDir = $parser->getTargetLanguage()->getDir();
		$contentDir = $parser->getContentLanguage()->getDir();
		$status = $content->sanitize( [
			'flip' => $targetDir !== $contentDir,
			'minify' => !ResourceLoader::inDebugMode(),
			'class' => $wrapClass,
			'extraWrapper' => $extraWrapper,
		] );
		$style = $status->isOk() ? $status->getValue() : '/* Fatal error, no CSS will be output */';

		// Prepend errors. This should normally never happen, but might if an
		// update or configuration change causes something that was formerly
		// valid to become invalid or something like that.
		if ( !$status->isGood() ) {
			$comment = wfMessage(
				'templatestyles-errorcomment',
				$title->getPrefixedText(),
				$revRecord->getId(),
				$status->getWikiText( false, 'rawmessage' )
			)->text();
			$comment = trim( strtr( $comment, [
				// Use some lookalike unicode characters to avoid things that might
				// otherwise confuse browsers.
				'*' => '•', '-' => '‐', '<' => '⧼', '>' => '⧽',
			] ) );
			$style = "/*\n$comment\n*/\n$style";
		}

		// Hide the CSS from Parser::doBlockLevels
		$marker = Parser::MARKER_PREFIX . '-templatestyles-' .
			sprintf( '%08X', $parser->mMarkerIndex++ ) . Parser::MARKER_SUFFIX;
		$parser->getStripState()->addNoWiki( $marker, $style );

		// Return the inline <style>, which the Parser will wrap in a 'general'
		// strip marker.
		$ret = Html::inlineStyle( $marker, 'all', [
			'data-mw-deduplicate' => "TemplateStyles:$cacheKey",
		] );
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		$parser->extTemplateStylesCache->set( $cacheKey, $ret );
		return $ret;
	}

	/**
	 * Format an error in the `<templatestyles>` tag
	 * @param Parser $parser
	 * @param array $msg Arguments to wfMessage()
	 * @return string HTML
	 */
	private static function formatTagError( Parser $parser, array $msg ) {
		$parser->addTrackingCategory( 'templatestyles-page-error-category' );
		return '<strong class="error">' .
			call_user_func_array( 'wfMessage', $msg )->inContentLanguage()->parse() .
			'</strong>';
	}

}
