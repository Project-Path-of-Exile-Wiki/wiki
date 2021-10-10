<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoJSONFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [ 'parse values' => [ 'type' => 'boolean' ] ];
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
		$queryParams['format'] = 'json';
		if ( array_key_exists( 'parse values', $displayParams ) && $displayParams['parse values'] != '' ) {
			$queryParams['parse values'] = $displayParams['parse values'];
		}

		$linkAttrs = [
			'href' => $ce->getFullURL( $queryParams ),
		];
		$text = Html::rawElement( 'a', $linkAttrs, wfMessage( 'cargo-viewjson' )->text() );

		return $text;
	}

}
