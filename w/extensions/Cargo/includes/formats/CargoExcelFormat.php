<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoExcelFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'filename' => [ 'type' => 'string' ],
			'link text' => [ 'type' => 'string' ],
			'parse values' => [ 'type' => 'boolean' ]
		];
	}

	/**
	 * @param array $sqlQueries
	 * @param array $displayParams Unused
	 * @param array|null $querySpecificParams Unused
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'excel';
		if ( array_key_exists( 'filename', $displayParams ) && $displayParams['filename'] != '' ) {
			$queryParams['filename'] = $displayParams['filename'];
		}
		if ( array_key_exists( 'parse values', $displayParams ) && $displayParams['parse values'] != '' ) {
			$queryParams['parse values'] = $displayParams['parse values'];
		}
		if ( array_key_exists( 'link text', $displayParams ) && $displayParams['link text'] != '' ) {
			$linkText = $displayParams['link text'];
		} else {
			$linkText = wfMessage( 'cargo-viewxls' )->text();
		}
		$linkAttrs = [
			'href' => $ce->getFullURL( $queryParams ),
		];
		$text = Html::rawElement( 'a', $linkAttrs, $linkText );

		return $text;
	}

}
