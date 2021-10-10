<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDisplayFormat {

	public function __construct( $output, $parser = null ) {
		$this->mOutput = $output;
		$this->mParser = $parser;
	}

	public static function allowedParameters() {
		return [];
	}

	public static function isDeferred() {
		return false;
	}

}
