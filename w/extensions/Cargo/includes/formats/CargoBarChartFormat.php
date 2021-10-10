<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoBarChartFormat extends CargoDeferredFormat {
	public static function allowedParameters() {
		return [
			'height' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ],
			'width' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ]
		];
	}

	/**
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array|null $querySpecificParams Unused
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( 'ext.cargo.nvd3' );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'nvd3chart';

		$svgAttrs = [];
		if ( array_key_exists( 'width', $displayParams ) && $displayParams['width'] != '' ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
			$svgAttrs['width'] = $width;
		} else {
			$svgAttrs['width'] = "100%";
		}
		if ( array_key_exists( 'height', $displayParams ) && $displayParams['height'] != '' ) {
			$height = $displayParams['height'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $height ) ) {
				$height .= "px";
			}
			$svgAttrs['height'] = $height;
		} else {
			// Stub value, so that we know to replace it.
			$svgAttrs['height'] = '1px';
		}

		$svgText = Html::element( 'svg', $svgAttrs, '' );

		$divAttrs = [
			'class' => 'cargoBarChart',
			'dataurl' => $ce->getFullURL( $queryParams ),
		];
		$text = Html::rawElement( 'div', $divAttrs, $svgText );

		return $text;
	}

}
