<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo;

use Config;
use ConfigException;
use InvalidArgumentException;
use MediaWiki\Extension\EmbedVideo\EmbedService\AbstractEmbedService;
use MediaWiki\Extension\EmbedVideo\EmbedService\EmbedHtmlFormatter;
use MediaWiki\Extension\EmbedVideo\EmbedService\EmbedServiceFactory;
use MediaWiki\Extension\EmbedVideo\EmbedService\OEmbedServiceInterface;
use MediaWiki\MediaWikiServices;
use Message;
use Parser;
use PPFrame;

class EmbedVideo {
	/**
	 * @var Parser|null
	 */
	private $parser;

	/**
	 * @var array
	 */
	private $args;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * Temporary storage for the current service object.
	 *
	 * @var AbstractEmbedService
	 */
	private $service;

	/**
	 * Description Parameter
	 *
	 * @var string
	 */
	private $description = false;

	/**
	 * Alignment Parameter
	 *
	 * @var string
	 */
	private $alignment = false;

	/**
	 * Alignment Parameter
	 *
	 * @var string
	 */
	private $vAlignment = false;

	/**
	 * Container Parameter
	 *
	 * @var string
	 */
	private $container = false;

	public function __construct( ?Parser $parser, array $args, bool $fromTag = false ) {
		$this->parser = $parser;
		$this->args = $this->parseArgs( $args, $fromTag );
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'EmbedVideo' );
	}

	/**
	 * Parse the values input from the {{#ev}} parser function
	 *
	 * @param Parser $parser The active Parser instance
	 * @param PPFrame $frame Frame
	 * @param array $args Arguments
	 *
	 * @return array Parser options and the HTML comments of cached attributes
	 */
	public static function parseEV( $parser, PPFrame $frame, array $args, bool $fromTag = false ): array {
		$expandedArgs = [];

		foreach ( $args as $key => $arg ) {
			$value = trim( $frame->expand( $arg ) );
			if ( $fromTag === true ) {
				$expandedArgs[$key] = $value;
			} else {
				$expandedArgs[] = $value;
			}
		}

		$embedVideo = new EmbedVideo( $parser, $expandedArgs, $fromTag );

		return $embedVideo->output();
	}

	/**
	 * Parse a service tag like <youtube>
	 *
	 * @param string $input The content of the tag i.e. the video id
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return array
	 */
	public static function parseEVTag( $input, array $args, Parser $parser, PPFrame $frame ): array {
		if ( !isset( $args['id'] ) ) {
			$args['id'] = $input;
		}

		return self::parseEV( $parser, $frame, $args, true );
	}

	/**
	 * Wrapper for service specific tag calls
	 *
	 * @param string $name Method name
	 * @param array $arguments Method arguments
	 */
	public static function __callStatic( $name, $arguments ) {
		if ( strpos( $name, 'parseTag' ) !== 0 ) {
			return;
		}

		[
			0 => $input,
			1 => $args,
			2 => $parser,
			3 => $frame,
		] = $arguments;

		$args['service'] = strtolower( substr( $name, 8 ) );

		return self::parseEVTag( $input, $args, $parser, $frame );
	}

	/**
	 * Outputs the iframe or error message
	 *
	 * @return array
	 */
	public function output(): array {
		[
			'service' => $service,
			'autoResize' => $autoResize,
		] = $this->args;

		try {
			$enabledServices = $this->config->get( 'EmbedVideoEnabledServices' ) ?? [];
			if ( !empty( $enabledServices ) && !in_array( $service, $enabledServices, true ) ) {
				return $this->error( 'service', sprintf( '%s (as it is disabled)', $service ) );
			}
		} catch ( ConfigException $e ) {
			// Pass through
		}

		try {
			$this->init();
		} catch ( InvalidArgumentException $e ) {
			return [
				$e->getMessage(),
				'noparse' => true,
				'isHTML' => true
			];
		}

		$html = EmbedHtmlFormatter::toHtml( $this->service );
		if ( !$html ) {
			return $this->error( 'unknown', $service );
		}

		$html = $this->generateWrapperHTML( $html, $autoResize ? 'autoResize' : null, $service );

		$this->addModules();

		return [
			$html,
			'noparse' => true,
			'isHTML' => true
		];
	}

	/**
	 * Parses the arguments given to the parser function
	 *
	 * @param array $args
	 * @param bool $fromTag
	 * @return array
	 */
	private function parseArgs( array $args, bool $fromTag ): array {
		$results = [
			'id' => '',
			'dimensions' => '',
			'alignment' => '',
			'description' => '',
			'autoResize' => true,
			'vAlignment' => '',
			'urlArgs' => '',
			'width' => null,
			'height' => null,
		];

		if ( $fromTag === true ) {
			return array_merge( $results, $args );
		}

		$keys = array_keys( $results );

		if ( $fromTag ) {
			$serviceName = $args['service'] ?? null;
		} else {
			$serviceName = array_shift( $args );
		}

		$counter = 0;

		foreach ( $args as $arg ) {
			$pair = [ $arg ];
			// Only split arg if it is not an url
			if ( preg_match( '/https?:/', $arg ) !== 1 ) {
				$pair = explode( '=', $arg, 2 );
			}
			$pair = array_map( 'trim', $pair );

			if ( count( $pair ) === 2 ) {
				[ $name, $value ] = $pair;
				$results[$name] = $value;
			} elseif ( count( $pair ) === 1 && !empty( $pair[0] ) ) {
				$pair = $pair[0];

				if ( $keys[$counter] === 'autoresize' && strtolower( $pair ) === 'false' ) {
					$pair = false;
				}

				$results[$keys[$counter]] = $pair;
			}

			++$counter;
		}

		$results['service'] = $serviceName;

		return $results;
	}

	/**
	 * Error Handler
	 *
	 * @private
	 * @param string    [Optional] Error Type
	 * @param mixed    [...] Multiple arguments to be retrieved with func_get_args().
	 * @return array Printable Error Message
	 */
	private function error( $type = 'unknown' ): array {
		$arguments = func_get_args();
		array_shift( $arguments );

		$message = wfMessage( 'embedvideo-error-' . $type, $arguments )->escaped();

		return [
			"<div class='errorbox'>{$message}</div>",
			'noparse' => true,
			'isHTML' => true
		];
	}

	/**
	 * Initializes the service and checks for errors
	 * @throws InvalidArgumentException
	 */
	private function init(): void {
		[
			'service' => $service,
			'dimensions' => $dimensions,
			'id' => $id,
			'width' => $width,
			'height' => $height,
			'alignment' => $alignment,
			'description' => $description,
			'urlArgs' => $urlArgs,
			'vAlignment' => $vAlignment,
		] = $this->args;

		// I am not using $parser->parseWidthParam() since it can not handle height only.  Example: x100
		if ( stripos( $dimensions, 'x' ) !== false ) {
			$dimensions = strtolower( $dimensions );
			[ $width, $height ] = explode( 'x', $dimensions );
		} elseif ( is_numeric( $dimensions ) ) {
			$width = $dimensions;
		}

		if ( !$service || !$id ) {
			throw new InvalidArgumentException( $this->error( 'missingparams', $service, $id )[0] );
		}

		$this->service = EmbedServiceFactory::newFromName( $service, $id );

		// Let the service automatically handle bad dimensional values.
		$this->service->setWidth( $width );

		$this->service->setHeight( $height );

		if ( !$this->service->setUrlArgs( $urlArgs ) ) {
			throw new InvalidArgumentException( $this->error( 'urlargs', $service, $urlArgs )[0] );
		}

		if ( $this->parser !== null ) {
			$this->setDescription( $description, $this->parser );
		} else {
			$this->setDescriptionNoParse( $description );
		}

		if ( !$this->setContainer( $this->container ) ) {
			throw new InvalidArgumentException( $this->error( 'container', $this->container )[0] );
		}

		if ( !$this->setAlignment( $alignment ) ) {
			throw new InvalidArgumentException( $this->error( 'alignment', $alignment )[0] );
		}

		if ( !$this->setVerticalAlignment( $vAlignment ) ) {
			throw new InvalidArgumentException( $this->error( 'valignment', $vAlignment )[0] );
		}
	}

	/**
	 * Set the description.
	 *
	 * @private
	 * @param string $description Description
	 * @param Parser $parser Mediawiki Parser object
	 * @return void
	 */
	private function setDescription( string $description, Parser $parser ): void {
		$this->description = ( !$description ? false : $parser->recursiveTagParse( $description ) );
	}

	/**
	 * Set the description without using the parser
	 *
	 * @param string    Description
	 */
	private function setDescriptionNoParse( $description ): void {
		$this->description = ( !$description ? false : $description );
	}

	/**
	 * Set the container type.
	 *
	 * @private
	 * @param string    Container
	 * @return bool Success
	 */
	private function setContainer( $container ): bool {
		if ( !empty( $container ) && ( $container === 'frame' ) ) {
			$this->container = $container;
		} elseif ( !empty( $container ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Set the align parameter.
	 *
	 * @private
	 * @param string    Alignment Parameter
	 * @return bool Valid
	 */
	private function setAlignment( $alignment ): bool {
		if ( !empty( $alignment ) && ( $alignment === 'left' || $alignment === 'right' || $alignment === 'center' || $alignment === 'inline' ) ) {
			$this->alignment = $alignment;
		} elseif ( !empty( $alignment ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Set the align parameter.
	 *
	 * @private
	 * @param string    Alignment Parameter
	 * @return bool Valid
	 */
	private function setVerticalAlignment( $vAlignment ): bool {
		if ( !empty( $vAlignment ) && ( $vAlignment === 'top' || $vAlignment === 'middle' || $vAlignment === 'bottom' || $vAlignment === 'baseline' ) ) {
			if ( $vAlignment !== 'baseline' ) {
				$this->alignment = 'inline';
			}
			$this->vAlignment = $vAlignment;
		} elseif ( !empty( $vAlignment ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate the HTML necessary to embed the video with the given alignment
	 * and text description
	 *
	 * @private
	 * @param string    [Optional] Horizontal Alignment
	 * @param string    [Optional] Description
	 * @param string  [Optional] Additional Classes to add to the wrapper
	 * @return string
	 */
	private function generateWrapperHTML( $html, $addClass = null, string $service = '' ): string {
		$classString = 'embedvideo';
		$styleString = '';
		$innerClassString = implode( ' ', array_filter( [
			'embedvideowrap',
			$service,
			// This should probably be added as a RL variable
			$this->config->get( 'EmbedVideoFetchExternalThumbnails' ) ? '' : 'no-fetch'
		] ) );
		$outerClassString = 'embedvideo ';

		if ( $this->container === 'frame' ) {
			$classString .= ' thumbinner';
		}

		if ( $this->alignment !== false ) {
			$outerClassString .= sprintf( ' ev_%s ', $this->alignment );
			$styleString .= sprintf( ' width: %dpx;', ( $this->service->getWidth() + 6 ) );
		}

		if ( $this->vAlignment !== false ) {
			$outerClassString .= sprintf( ' ev_%s ', $this->vAlignment );
		}

		if ( $addClass !== null ) {
			$classString .= ' ' . $addClass;
			$outerClassString .= $addClass;
		}

		$consentClickContainer = '';
		if ( !( $this->service instanceof OEmbedServiceInterface ) && $this->config->get( 'EmbedVideoRequireConsent' ) ) {
			$consentClickContainer = sprintf(
				'<div class="embedvideo-consent"><div class="embedvideo-consent__overlay"><div class="embedvideo-consent__message">%s</div></div></div>',
				( new Message( 'embedvideo-consent-text' ) )->text()
			);
		}

		$width = $this->service->getWidth();
		$widthPad = $width + 8;
		$caption = $this->description !== false ? sprintf( '<div class="thumbcaption">%s</div>', $this->description ) : '';

		return <<<HTML
<div class="thumb {$outerClassString}" style="width: {$widthPad}px;">
	<div class="$classString" style="$styleString">
		<div class="$innerClassString" style="width: $width">{$consentClickContainer}{$html}</div>
		$caption
	</div>
</div>
HTML;
	}

	/**
	 * Adds all relevant modules if the parser is present
	 */
	private function addModules(): void {
		if ( $this->parser === null || $this->service instanceof OEmbedServiceInterface ) {
			return;
		}

		$out = $this->parser->getOutput();

		// Add CSP if needed
		$defaultSrcArr = $this->service->getCSPUrls();
		if ( $defaultSrcArr ) {
			foreach ( $defaultSrcArr as $defaultSrc ) {
				$out->addExtraCSPDefaultSrc( $defaultSrc );
			}
		}

		$out->addModules( 'ext.embedVideo' );
		$out->addModuleStyles( 'ext.embedVideo.styles' );

		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'EmbedVideoRequireConsent' ) === true ) {
			$this->parser->getOutput()->addModules( 'ext.embedVideo.consent' );
		}
	}
}
