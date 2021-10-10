<?php
/**
 * EmbedVideo
 * VideoHandler Class
 *
 * @author  Alexia E. Smith
 * @license MIT
 * @package EmbedVideo
 * @link    https://www.mediawiki.org/wiki/Extension:EmbedVideo
 */

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\Media;

use Exception;
use File;
use MediaTransformOutput;
use MediaWiki\Extension\EmbedVideo\Media\TransformOutput\VideoTransformOutput;
use MediaWiki\MediaWikiServices;
use Title;

class VideoHandler extends AudioHandler {
	/**
	 * Validate a thumbnail parameter at parse time.
	 * Return true to accept the parameter, and false to reject it.
	 * If you return false, the parser will do something quiet and forgiving.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return bool
	 */
	public function validateParam( $name, $value ) :bool {
		if ( $name === 'width' || $name === 'height' ) {
			return $value > 0;
		}

		if ( $name === 'cover' || $name === 'gif' || $name === 'muted' ) {
			return true;
		}

		return parent::validateParam( $name, $value );
	}

	/**
	 * Changes the parameter array as necessary, ready for transformation.
	 * Should be idempotent.
	 * Returns false if the parameters are unacceptable and the transform should fail
	 *
	 * @param File File
	 * @param array Parameters
	 * @return bool Success
	 */
	public function normaliseParams( $file, &$parameters ): bool {
		parent::normaliseParams( $file, $parameters );

		// Note: MediaHandler declares getImageSize with a local path, but we don't need it here.
		[ $width, $height ] = $this->getImageSize( $file, '' );

		if ( $width === 0 && $height === 0 ) {
			// Force a reset.
			$width = 640;
			$height = 360;
		}

		if ( isset( $parameters['width'], $parameters['height'] ) && $parameters['width'] > 0 && $parameters['height'] === $parameters['width'] ) {
			// special allowance for square video embeds needed by some wikis, otherwise forced 16:9 ratios are followed.
			return true;
		}

		if ( isset( $parameters['width'] ) && $parameters['width'] > 0 && $parameters['width'] < $width ) {
			$parameters['width'] = (int)$parameters['width'];

			if ( !isset( $parameters['height'] ) ) {
				// Page embeds do not specify thumbnail height so correct it here based on aspect ratio.
				$parameters['height'] = round( $height / $width * $parameters['width'] );
			}
		} else {
			$parameters['width'] = $width;
		}

		if ( isset( $parameters['height'] ) && $parameters['height'] > 0 && $parameters['height'] < $height ) {
			$parameters['height'] = (int)$parameters['height'];
		} else {
			$parameters['height'] = $height;
		}

		if ( $width > 0 && $parameters['width'] > 0 && ( $height / $width ) !== ( $parameters['height'] / $parameters['width'] ) ) {
			$parameters['height'] = round( $height / $width * $parameters['width'] );
		}

		if ( isset( $parameters['cover'] ) ) {
			$title = Title::newFromText( $parameters['cover'], NS_FILE );

			if ( $title !== null && $title->exists() ) {
				$coverFile = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
				$transform = $coverFile->transform( [ 'width' => $parameters['width'] ] );

				try {
					$parameters['cover'] = wfExpandUrl( $transform->getUrl() );
				} catch ( Exception $e ) {
					unset( $parameters['cover'] );
				}
			} else {
				unset( $parameters['cover'] );
			}
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getImageSize( $file, $path ): array {
		[
			'stream' => $stream,
		] = $this->getMakeProbeFromPool( $file );

		if ( $stream !== false ) {
			return [
				$stream->getWidth(),
				$stream->getHeight(),
				0,
				sprintf( 'width="%s" height="%s"', $stream->getWidth(), $stream->getHeight() ),
				'bits' => $stream->getBitDepth()
			];
		}

		return [ 0, 0, 0, 'width="0" height="0"', 'bits' => 0 ];
	}

	/**
	 * Get a MediaTransformOutput object representing the transformed output. Does the
	 * transform unless $flags contains self::TRANSFORM_LATER.
	 *
	 * @param File $file The image object
	 * @param string $dstPath Filesystem destination path
	 * @param string $dstUrl Destination URL to use in output HTML
	 * @param array $params Arbitrary set of parameters validated by $this->validateParam()
	 *                          Note: These parameters have *not* gone through
	 *                          $this->normaliseParams()
	 * @param int $flags A bitfield, may contain self::TRANSFORM_LATER
	 * @return MediaTransformOutput
	 */
	public function doTransform( $file, $dstPath, $dstUrl, $params, $flags = 0 ) {
		$this->normaliseParams( $file, $params );

		return new VideoTransformOutput( $file, $params );
	}

	/**
	 * Shown in file history box on image description page.
	 *
	 * @param File $file
	 * @return string Dimensions
	 */
	public function getDimensionsString( $file ): string {
		[
			'stream' => $stream,
			'format' => $format,
		] = $this->getMakeProbeFromPool( $file );

		if ( $format === false || $stream === false ) {
			return parent::getDimensionsString( $file );
		}

		return wfMessage(
			'embedvideo-video-short-desc',
			$this->contentLanguage->formatTimePeriod( $format->getDuration() ),
			$stream->getWidth(),
			$stream->getHeight()
		)->text();
	}

	/**
	 * Short description. Shown on Special:Search results.
	 *
	 * @param File $file
	 * @return string
	 */
	public function getShortDesc( $file ): string {
		[
			'stream' => $stream,
			'format' => $format,
		] = $this->getMakeProbeFromPool( $file );

		if ( $format === false || $stream === false ) {
			return self::getGeneralShortDesc( $file );
		}

		return wfMessage(
			'embedvideo-video-short-desc',
			$this->contentLanguage->formatTimePeriod( $format->getDuration() ),
			$stream->getWidth(),
			$stream->getHeight(),
			$this->contentLanguage->formatSize( $file->getSize() )
		)->text();
	}

	/**
	 * Long description. Shown under image on image description page surounded by ().
	 *
	 * @param File $file
	 * @return string
	 */
	public function getLongDesc( $file ): string {
		[
			'stream' => $stream,
			'format' => $format,
		] = $this->getMakeProbeFromPool( $file );

		if ( $format === false || $stream === false ) {
			return self::getGeneralLongDesc( $file );
		}

		$extension = pathinfo( $file->getPath(), PATHINFO_EXTENSION );

		return wfMessage(
			'embedvideo-video-long-desc',
			strtoupper( $extension ),
			$stream->getCodecName(),
			$this->contentLanguage->formatTimePeriod( $format->getDuration() ),
			$stream->getWidth(),
			$stream->getHeight(),
			$this->contentLanguage->formatBitrate( $format->getBitRate() )
		)->text();
	}
}
