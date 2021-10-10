<?php
/**
 * EmbedVideo
 * VideoTransformOutput Class
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

class VideoTransformOutput extends AudioTransformOutput {

	/**
	 * Main Constructor
	 *
	 * @access public
	 * @param File $file
	 * @param array $parameters Parameters for constructing HTML.
	 * @return void
	 */
	public function __construct( $file, $parameters ) {
		parent::__construct( $file, $parameters );

		if ( isset( $parameters['gif'] ) ) {
			$this->parameters['autoplay'] = true;
			$this->parameters['loop'] = true;
			$this->parameters['nocontrols'] = true;
			$this->parameters['muted'] = true;
		}
	}

	/**
	 * Fetch HTML for this transform output
	 *
	 * @access public
	 * @param array $options Associative array of options. Boolean options
	 *                        should be indicated with a value of true for true, and false or
	 *                        absent for false.
	 *                        alt                Alternate text or caption
	 *                        desc-link          Boolean, show a description link
	 *                        file-link          Boolean, show a file download link
	 *                        custom-url-link    Custom URL to link to
	 *                        custom-title-link  Custom Title object to link to
	 *                        valign             vertical-align property, if the output is an inline element
	 *                        img-class          Class applied to the "<img>" tag, if there is such a tag
	 *
	 * @return string HTML
	 */
	public function toHtml( $options = [] ): string {
		return Html::rawElement( 'video', [
			'src' => $this->getSrc(),
			'width' => $this->getWidth(),
			'height' => $this->getHeight(),
			'class' => $options['img-class'] ?? false,
			'style' => $this->getStyle( $options ),
			'poster' => $this->parametersparameters['cover'] ?? false,
			'controls' => !isset( $this->parameters['nocontrols'] ),
			'autoplay' => isset( $this->parameters['autoplay'] ),
			'loop' => isset( $this->parameters['loop'] ),
			'muted' => isset( $this->parameters['muted'] ),
		], $this->getDescription() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getStyle( array $options ): string {
		$style = [];
		$style[] = 'max-width: 100%;';
		$style[] = 'max-height: 100%;';

		if ( empty( $options['no-dimensions'] ) ) {
			$style[] = "width: {$this->getWidth()}px;";
			$style[] = "height: {$this->getHeight()}px;";
		}

		if ( !empty( $options['valign'] ) ) {
			$style[] = "vertical-align: {$options['valign']};";
		}

		return implode( ' ', $style );
	}
}
