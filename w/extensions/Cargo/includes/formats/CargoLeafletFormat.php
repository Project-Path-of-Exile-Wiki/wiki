<?php

/**
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoLeafletFormat extends CargoMapsFormat {

	public function __construct( $output ) {
		parent::__construct( $output );
		self::$mappingService = "Leaflet";
	}

	public static function allowedParameters() {
		$allowedParams = parent::allowedParameters();
		$allowedParams['image'] = [ 'type' => 'string' ];
		return $allowedParams;
	}

	public static function getScripts() {
		return [ "https://unpkg.com/leaflet@1.1.0/dist/leaflet.js" ];
	}

	public static function getStyles() {
		return [ "https://unpkg.com/leaflet@1.1.0/dist/leaflet.css" ];
	}

}
