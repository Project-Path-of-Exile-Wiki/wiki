<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\EmbedService;

use Config;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;

abstract class AbstractEmbedService {
	/**
	 * Array of attributes that are added to the iframe
	 *
	 * @var array
	 */
	protected $iframeAttributes = [
		'loading' => 'lazy',
		'frameborder' => 0,
		'allow' => 'accelerometer; clipboard-write; encrypted-media; fullscreen; gyroscope; picture-in-picture',
	];

	/**
	 * Additional attributes that are set on the iframe
	 * This has a precedence over the default attributes
	 *
	 * @var array
	 */
	protected $additionalIframeAttributes = [];

	/**
	 * The id of the targeted embed
	 * E.g. the id of a YouTube video
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Width of the iframe
	 *
	 * @var int
	 */
	protected $width;

	/**
	 * Height of the iframe
	 *
	 * @var int
	 */
	protected $height;

	/**
	 *
	 *
	 * @var array
	 */
	protected $extraIds = [];

	/**
	 * @var
	 */
	protected $urlArgs = [];

	/**
	 * Config object
	 *
	 * @var Config
	 */
	protected static $config;

	/**
	 * AbstractVideoService constructor.
	 * @param string $id
	 * @throws InvalidArgumentException
	 */
	public function __construct( string $id ) {
		if ( self::$config === null ) {
			self::$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'EmbedVideo' );
		}

		$this->id = $this->parseVideoID( $id );
	}

	/**
	 * Get the width of the iframe
	 *
	 * @return float|string
	 */
	public function getWidth() {
		return $this->width ?? $this->getDefaultWidth();
	}

	/**
	 * Get the height of the iframe
	 *
	 * @return float|string
	 */
	public function getHeight() {
		return $this->height ?? $this->getDefaultHeight();
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return (string)$this->id;
	}

	/**
	 * Returns the base url for this service.
	 * Specify the location of the id with '%1$s'
	 * E.g.: //www.youtube-nocookie.com/embed/%1$s
	 *
	 * @return string
	 */
	abstract public function getBaseUrl(): string;

	/**
	 * The targeted aspect ratio of the embed
	 * Is used to automatically set the height based on the width
	 *
	 * @return float|null
	 */
	abstract public function getAspectRatio(): ?float;

	/**
	 * Returns the service name
	 *
	 * @return string
	 */
	final public static function getServiceName(): string {
		return strtolower( substr( static::class, strrpos( static::class, '\\' ) + 1 ) );
	}

	/**
	 * The default iframe width if no width is set specified
	 *
	 * @return int
	 */
	abstract protected function getDefaultWidth(): int;

	/**
	 * The default iframe height if no height is set specified
	 *
	 * @return int
	 */
	abstract protected function getDefaultHeight(): int;

	/**
	 * Array of regexes to validate a given service url
	 *
	 * @return array
	 */
	abstract protected function getUrlRegex(): array;

	/**
	 * Array of regexes to validate a given embed id
	 *
	 * @return array
	 */
	abstract protected function getIdRegex(): array;

	/**
	 * Returns the full url to the embed
	 *
	 * @return string
	 */
	public function getUrl(): string {
		return sprintf( $this->getBaseUrl(), $this->getId() );
	}

	/**
	 * Returns an array of Content Security Policy urls for this service.
	 *
	 * @return array
	 */
	abstract public function getCSPUrls(): array;

	/**
	 * Set the width of the player.  This also will set the height automatically.
	 * Width will be automatically constrained to the minimum and maximum widths.
	 *
	 * @param int|null Width
	 * @return void
	 */
	public function setWidth( $width = null ): void {
		$videoMinWidth = self::$config->get( 'EmbedVideoMinWidth' );
		$videoMaxWidth = self::$config->get( 'EmbedVideoMaxWidth' );
		$videoDefaultWidth = self::$config->get( 'EmbedVideoDefaultWidth' );

		if ( !is_numeric( $width ) ) {
			if ( $width === null && $this->width !== null && $videoDefaultWidth < 1 ) {
				$width = $this->getWidth();
			} else {
				$width = ( $videoDefaultWidth > 0 ? $videoDefaultWidth : 640 );
			}
		} else {
			$width = (int)$width;
		}

		if ( $videoMaxWidth > 0 && $width > $videoMaxWidth ) {
			$width = $videoMaxWidth;
		}

		if ( $videoMinWidth > 0 && $width < $videoMinWidth ) {
			$width = $videoMinWidth;
		}

		$this->width = $width;

		if ( $this->height === null ) {
			$this->setHeight();
		}
	}

	/**
	 * Set the height automatically by a ratio of the width or use the provided value.
	 *
	 * @param int|null [Optional] Height Value
	 * @return void
	 */
	public function setHeight( $height = null ): void {
		if ( $height !== null && $height > 0 ) {
			$this->height = (int)$height;
			return;
		}

		$ratio = $this->getAspectRatio() ?? ( 16 / 9 );

		$this->height = round( $this->getWidth() / $ratio );
	}

	/**
	 * Parse the video ID/URL provided.
	 *
	 * @param  string Video ID/URL
	 * @return string Parsed Video ID or false on failure.
	 * @throws InvalidArgumentException
	 */
	public function parseVideoID( $id ): string {
		$id = trim( $id );
		// URL regexes are put into the array first to prevent cases where the ID regexes might accidentally match an incorrect portion of the URL.
		$regexes = array_merge( $this->getUrlRegex(), $this->getIdRegex() );

		if ( !empty( $regexes ) ) {
			foreach ( $regexes as $regex ) {
				if ( preg_match( $regex, $id, $matches ) ) {
					// Get rid of the full text match.
					array_shift( $matches );

					$id = array_shift( $matches );

					if ( !empty( $matches ) ) {
						$this->extraIds = $matches;
					}

					return $id;
				}
			}

			// If nothing matches and matches are specified then return false for an invalid ID/URL.
			throw new InvalidArgumentException( 'Provided ID could not be validated.' );
		}

		// Service definition has not specified a sanitization/validation regex.
		return $id;
	}

	/**
	 * Return the optional URL arguments.
	 *
	 * @return false|string Http query or false for not set.
	 */
	public function getUrlArgs() {
		if ( !empty( $this->urlArgs ) ) {
			return http_build_query( $this->urlArgs );
		}

		return false;
	}

	/**
	 * Set URL Arguments to optionally add to the embed URL.
	 *
	 * @param string Raw Arguments
	 * @return bool Success
	 */
	public function setUrlArgs( string $urlArgs ): bool {
		if ( $urlArgs === null || empty( $urlArgs ) ) {
			return true;
		}

		$urlArgs = urldecode( $urlArgs );
		$_args = explode( '&', $urlArgs );
		$arguments = [];

		if ( is_array( $_args ) ) {
			foreach ( $_args as $rawPair ) {
				[ $key, $value ] = explode( "=", $rawPair, 2 );

				if ( empty( $key ) || ( $value === null || $value === '' ) ) {
					return false;
				}

				$arguments[$key] = htmlentities( $value, ENT_QUOTES );
			}
		} else {
			return false;
		}

		$this->urlArgs = $arguments;
		return true;
	}

	/**
	 * Add an attribute to the iframe
	 *
	 * @param string $key Attribute name
	 * @param mixed $value Attribute value
	 */
	public function addIframeAttribute( string $key, $value ): void {
		$this->iframeAttributes[$key] = (string)$value;
	}

	/**
	 * Get the merged list of attributes
	 *
	 * @return array
	 */
	public function getIframeAttributes(): array {
		return array_merge( $this->iframeAttributes, $this->additionalIframeAttributes );
	}
}
