<?php

/**
 * Represents a single row in the outline.
 */
class CargoOutlineRow {
	public $mOutlineFields;
	public $mDisplayFields;

	public function __construct() {
		$this->mOutlineFields = [];
		$this->mDisplayFields = [];
	}

	public function addOutlineFieldValues( $fieldName, $values, $formattedValues ) {
		$this->mOutlineFields[$fieldName] = [
			'unformatted' => $values,
			'formatted' => $formattedValues
		];
	}

	public function addOutlineFieldValue( $fieldName, $value, $formattedValue ) {
		$this->mOutlineFields[$fieldName] = [
			'unformatted' => [ $value ],
			'formatted' => [ $formattedValue ]
		];
	}

	public function addDisplayFieldValue( $fieldName, $value ) {
		$this->mDisplayFields[$fieldName] = $value;
	}

	public function getOutlineFieldValues( $fieldName ) {
		if ( !array_key_exists( $fieldName, $this->mOutlineFields ) ) {
			throw new MWException( wfMessage( "cargo-query-specifiedfieldmissing", $fieldName, "outline fields" )->parse() );
		}
		return $this->mOutlineFields[$fieldName]['unformatted'];
	}

	public function getFormattedOutlineFieldValues( $fieldName ) {
		return $this->mOutlineFields[$fieldName]['formatted'];
	}
}
