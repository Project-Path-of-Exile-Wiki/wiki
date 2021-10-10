<?php

/**
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoOpenLayersFormat extends CargoMapsFormat {

	public function __construct( $output ) {
		parent::__construct( $output );
		self::$mappingService = "OpenLayers";
	}

	public static function getScripts() {
		return [ "//openlayers.org/api/OpenLayers.js" ];
	}

	public static function getStyles() {
		return [];
	}

}
