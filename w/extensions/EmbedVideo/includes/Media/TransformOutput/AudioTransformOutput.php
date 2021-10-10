<?php
/**
 * EmbedVideo
 * AudioTransformOutput Class
 *
 * @author  Alexia E. Smith
 * @license MIT
 * @package EmbedVideo
 * @link    https://www.mediawiki.org/wiki/Extension:EmbedVideo
 */

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\Media\TransformOutput;

use File;
use Html;
use MediaTransformOutput;

class AudioTransformOutput extends MediaTransformOutput {
	/**
	 * @var array
	 */
	protected $parameters;

	/**
	 * Main Constructor
	 *
	 * @access public
	 * @param File $file
	 * @param array $parameters Parameters for constructing HTML.
	 * @return void
	 */
	public function __construct( $file, $parameters ) {
		$this->file = $file;
		$this->parameters = $parameters;
		$this->width = $parameters['width'] ?? null;
		$this->height = $parameters['height'] ?? null;
		$this->path = null;
		$this->lang = false;
		$this->page = $parameters['page'];
		$this->url = $file->getFullUrl();
	}

	/**
	 * Fetch HTML for this transform output
	 *
	 * @access public
	 * @param array $options Associative array of options. Boolean options
	 *                        should be indicated with a value of true for
	 *                        true, and false or absent for false. alt
	 *                        Alternate text or caption desc-link
	 *                        Boolean, show a description link file-link
	 *                        Boolean, show a file download link
	 *                        custom-url-link    Custom URL to link to
	 *                        custom-title-link  Custom Title object to
	 *                        link to valign       vertical-align property,
	 *                        if the output is an inline element img-class
	 *                        Class applied to the "<img>" tag, if there
	 *                        is such a tag For images, desc-link and
	 *                        file-link are implemented as a click-through.
	 *                        For sounds and videos, they may be displayed
	 *                        in other ways.
	 *
	 * @return string HTML
	 */
	public function toHtml( $options = [] ): string {
		return Html::rawElement( 'audio', [
			'src' => $this->getSrc(),
			'width' => $this->getWidth(),
			'class' => $options['img-class'] ?? false,
			'style' => $this->getStyle( $options ),
			'controls' => !isset( $this->parameters['nocontrols'] ),
			'autoplay' => isset( $this->parameters['autoplay'] ),
			'loop' => isset( $this->parameters['loop'] ),
		], $this->getDescription() );
	}

	/**
	 * Get the source of the medium, including start (in) and end (out) times if set
	 *
	 * @return string
	 */
	protected function getSrc(): string {
		$inOut = [];

		if ( ( $this->parameters['start'] ?? null ) !== ( $this->parameters['end'] ?? null ) ) {
			if ( isset( $this->parameters['start'] ) && $this->parameters['start'] !== false ) {
				$inOut[] = $this->parameters['start'];
			}

			if ( isset( $this->parameters['end'] ) && $this->parameters['end'] !== false ) {
				$inOut[] = $this->parameters['end'];
			}
		}

		return $this->url . ( !empty( $inOut ) ? '#t=' . implode( ',', $inOut ) : '' );
	}

	/**
	 * Inline style added to the html tag
	 *
	 * @param array $options
	 * @return string
	 */
	protected function getStyle( array $options ): string {
		$style = [];

		$style[] = "max-width: 100%;";

		if ( empty( $options['no-dimensions'] ) ) {
			$style[] = "width: {$this->getWidth()}px;";
		}

		if ( !empty( $options['valign'] ) ) {
			$style[] = "vertical-align: {$options['valign']};";
		}

		return implode( ' ', $style );
	}

	/**
	 * Description link added to the html tag
	 *
	 * @return string
	 */
	protected function getDescription(): string {
		if ( isset( $this->parameters['descriptionUrl'] ) ) {
			return Html::element(
				'a',
				[
					'href' => $this->parameters['descriptionUrl']
				],
				$this->parameters['descriptionUrl']
			);
		}

		return '';
	}
}
