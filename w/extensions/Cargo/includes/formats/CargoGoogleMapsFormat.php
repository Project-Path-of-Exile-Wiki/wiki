<?php

/**
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoGoogleMapsFormat extends CargoMapsFormat {

	public function __construct( $output ) {
		parent::__construct( $output );
		self::$mappingService = "Google Maps";
	}

	public static function getScripts() {
		global $wgCargoGoogleMapsKey;

		return [
			"https://maps.googleapis.com/maps/api/js?v=3.exp&key=$wgCargoGoogleMapsKey"
		];
	}

	public static function getStyles() {
		return [];
	}

}
