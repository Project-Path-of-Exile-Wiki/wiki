<?php

/**
 * CargoFieldDescription - holds the attributes of a single field as defined
 * in the #cargo_declare parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoFieldDescription {
	public $mType;
	public $mSize;
	public $mDependentOn = [];
	public $mIsList = false;
	private $mDelimiter;
	public $mAllowedValues = null;
	public $mIsMandatory = false;
	public $mIsUnique = false;
	public $mRegex = null;
	public $mIsHidden = false;
	public $mIsHierarchy = false;
	public $mHierarchyStructure = null;
	public $mOtherParams = [];

	/**
	 * Initializes from a string within the #cargo_declare function.
	 *
	 * @param string $fieldDescriptionStr
	 * @return \CargoFieldDescription|null
	 */
	public static function newFromString( $fieldDescriptionStr ) {
		$fieldDescription = new CargoFieldDescription();

		if ( strpos( strtolower( $fieldDescriptionStr ), 'list' ) === 0 ) {
			$matches = [];
			$foundMatch = preg_match( '/[Ll][Ii][Ss][Tt] \((.*)\) [Oo][Ff] (.*)/is', $fieldDescriptionStr, $matches );
			if ( !$foundMatch ) {
				// Return a true error message here?
				return null;
			}
			$fieldDescription->mIsList = true;
			$fieldDescription->mDelimiter = $matches[1];
			$fieldDescriptionStr = $matches[2];
		}

		// There may be additional parameters, in/ parentheses.
		$matches = [];
		$foundMatch2 = preg_match( '/([^(]*)\s*\((.*)\)/s', $fieldDescriptionStr, $matches );
		$allowedValuesParam = "";
		if ( $foundMatch2 ) {
			$fieldDescriptionStr = trim( $matches[1] );
			$extraParamsString = $matches[2];
			$extraParams = explode( ';', $extraParamsString );
			foreach ( $extraParams as $extraParam ) {
				$extraParamParts = explode( '=', $extraParam, 2 );
				if ( count( $extraParamParts ) == 1 ) {
					$paramKey = strtolower( trim( $extraParamParts[0] ) );
					if ( $paramKey == 'hierarchy' ) {
						$fieldDescription->mIsHierarchy = true;
					}
					$fieldDescription->mOtherParams[$paramKey] = true;
				} else {
					$paramKey = strtolower( trim( $extraParamParts[0] ) );
					$paramValue = trim( $extraParamParts[1] );
					if ( $paramKey == 'allowed values' ) {
						// we do not assign allowed values to fieldDescription here,
						// because we don't know yet if it's a hierarchy or an enumeration
						$allowedValuesParam = $paramValue;
					} elseif ( $paramKey == 'size' ) {
						$fieldDescription->mSize = $paramValue;
					} elseif ( $paramKey == 'dependent on' ) {
						$fieldDescription->mDependentOn = array_map( 'trim', explode( ',', $paramValue ) );
					} else {
						$fieldDescription->mOtherParams[$paramKey] = $paramValue;
					}
				}
			}
			if ( $allowedValuesParam !== "" ) {
				$allowedValuesArray = [];
				if ( $fieldDescription->mIsHierarchy == true ) {
					// $paramValue contains "*" hierarchy structure
					CargoUtils::validateHierarchyStructure( trim( $allowedValuesParam ) );
					$fieldDescription->mHierarchyStructure = trim( $allowedValuesParam );
					// now make the allowed values param similar to the syntax
					// used by other fields
					$hierarchyNodesArray = explode( "\n", $allowedValuesParam );
					foreach ( $hierarchyNodesArray as $node ) {
						// Remove prefix of multiple "*"
						$allowedValuesArray[] = trim( preg_replace( '/^[*]*/', '', $node ) );
					}
				} else {
					// Replace the comma/delimiter
					// substitution with a character
					// that has no chance of being
					// included in the values list -
					// namely, the ASCII beep.

					// The delimiter can't be a
					// semicolon, because that's
					// already used to separate
					// "extra parameters", so just
					// hardcode it to a semicolon.
					$delimiter = ',';
					$allowedValuesStr = str_replace( "\\$delimiter", "\a", $allowedValuesParam );
					$allowedValuesTempArray = explode( $delimiter, $allowedValuesStr );
					foreach ( $allowedValuesTempArray as $i => $value ) {
						if ( $value == '' ) {
							continue;
						}
						// Replace beep back with delimiter, trim.
						$value = str_replace( "\a", $delimiter, trim( $value ) );
						$allowedValuesArray[] = $value;
					}
				}
				$fieldDescription->mAllowedValues = $allowedValuesArray;
			}

		}

		// What's left will be the type, hopefully.
		// Allow any capitalization of the type.
		$type = ucfirst( strtolower( $fieldDescriptionStr ) );
		// The 'URL' type has special capitalization.
		if ( $type == 'Url' ) {
			$type = 'URL';
		}
		$fieldDescription->mType = $type;

		// Validation.
		if ( $fieldDescription->mType == 'Text' && array_key_exists( 'unique', $fieldDescription->mOtherParams ) ) {
			throw new MWException( "'unique' is not allowed for fields of type 'Text'." );
		}
		if ( $fieldDescription->mType == 'Boolean' && $fieldDescription->mIsList == true ) {
			throw new MWException( "Error: 'list' is not allowed for fields of type 'Boolean'." );
		}

		return $fieldDescription;
	}

	/**
	 * @param array $descriptionData
	 * @return \CargoFieldDescription
	 */
	public static function newFromDBArray( $descriptionData ) {
		$fieldDescription = new CargoFieldDescription();
		foreach ( $descriptionData as $param => $value ) {
			if ( $param == 'type' ) {
				$fieldDescription->mType = $value;
			} elseif ( $param == 'size' ) {
				$fieldDescription->mSize = $value;
			} elseif ( $param == 'dependent on' ) {
				$fieldDescription->mDependentOn = $value;
			} elseif ( $param == 'isList' ) {
				$fieldDescription->mIsList = true;
			} elseif ( $param == 'delimiter' ) {
				$fieldDescription->mDelimiter = $value;
			} elseif ( $param == 'allowedValues' ) {
				$fieldDescription->mAllowedValues = $value;
			} elseif ( $param == 'mandatory' ) {
				$fieldDescription->mIsMandatory = true;
			} elseif ( $param == 'unique' ) {
				$fieldDescription->mIsUnique = true;
			} elseif ( $param == 'regex' ) {
				$fieldDescription->mRegex = $value;
			} elseif ( $param == 'hidden' ) {
				$fieldDescription->mIsHidden = true;
			} elseif ( $param == 'hierarchy' ) {
				$fieldDescription->mIsHierarchy = true;
			} elseif ( $param == 'hierarchyStructure' ) {
				$fieldDescription->mHierarchyStructure = $value;
			} else {
				$fieldDescription->mOtherParams[$param] = $value;
			}
		}
		return $fieldDescription;
	}

	public function getDelimiter() {
		// Make "\n" represent a newline.
		return str_replace( '\n', "\n", $this->mDelimiter );
	}

	public function setDelimiter( $delimiter ) {
		$this->mDelimiter = $delimiter;
	}

	public function isDateOrDatetime() {
		return in_array( $this->mType, [ 'Date', 'Start date', 'End date', 'Datetime', 'Start datetime', 'End datetime' ] );
	}

	public function getFieldSize() {
		if ( $this->isDateOrDatetime() ) {
			return null;
		} elseif ( in_array( $this->mType, [ 'Integer', 'Float', 'Rating', 'Boolean', 'Text', 'Wikitext', 'Searchtext' ] ) ) {
			return null;
		// This leaves String, Page, etc. - see CargoUtils::fieldTypeToSQLType().
		} elseif ( $this->mSize != null ) {
			return $this->mSize;
		} else {
			global $wgCargoDefaultStringBytes;
			return $wgCargoDefaultStringBytes;
		}
	}

	/**
	 * @return array
	 */
	public function toDBArray() {
		$descriptionData = [];
		$descriptionData['type'] = $this->mType;
		if ( $this->mSize != null ) {
			$descriptionData['size'] = $this->mSize;
		}
		if ( $this->mDependentOn != null ) {
			$descriptionData['dependent on'] = $this->mDependentOn;
		}
		if ( $this->mIsList ) {
			$descriptionData['isList'] = true;
		}
		if ( $this->mDelimiter != null ) {
			$descriptionData['delimiter'] = $this->mDelimiter;
		}
		if ( $this->mAllowedValues != null ) {
			$descriptionData['allowedValues'] = $this->mAllowedValues;
		}
		if ( $this->mIsMandatory ) {
			$descriptionData['mandatory'] = true;
		}
		if ( $this->mIsUnique ) {
			$descriptionData['unique'] = true;
		}
		if ( $this->mRegex != null ) {
			$descriptionData['regex'] = $this->mRegex;
		}
		if ( $this->mIsHidden ) {
			$descriptionData['hidden'] = true;
		}
		if ( $this->mIsHierarchy ) {
			$descriptionData['hierarchy'] = true;
			$descriptionData['hierarchyStructure'] = $this->mHierarchyStructure;
		}
		foreach ( $this->mOtherParams as $otherParam => $value ) {
			$descriptionData[$otherParam] = $value;
		}

		return $descriptionData;
	}

	public function prepareAndValidateValue( $fieldValue ) {
		// @TODO - also set, and return, an error message and/or code
		// if the returned value is different from the incoming value.
		// @TODO - it might make sense to create a new class around
		// this function, like "CargoFieldValue" -
		// CargoStore::getDateValueAndPrecision() could move there too.
		$fieldValue = trim( $fieldValue );
		if ( $fieldValue == '' ) {
			return [ 'value' => $fieldValue ];
		}

		$newValue = $precision = null;

		$fieldType = $this->mType;
		if ( $this->mAllowedValues != null ) {
			$allowedValues = $this->mAllowedValues;
			if ( $this->mIsList ) {
				$delimiter = $this->getDelimiter();
				$individualValues = explode( $delimiter, $fieldValue );
				$valuesToBeKept = [];
				foreach ( $individualValues as $individualValue ) {
					$realIndividualVal = trim( $individualValue );
					if ( in_array( $realIndividualVal, $allowedValues ) ) {
						$valuesToBeKept[] = $realIndividualVal;
					}
				}
				$newValue = implode( $delimiter, $valuesToBeKept );
			} else {
				if ( in_array( $fieldValue, $allowedValues ) ) {
					$newValue = $fieldValue;
				}
			}
		}

		if ( $this->isDateOrDatetime() ) {
			if ( $this->mIsList ) {
				$delimiter = $this->getDelimiter();
				$individualValues = explode( $delimiter, $fieldValue );
				// There's unfortunately only one precision
				// value per field, even if it holds more than
				// one date - store the most "precise" of the
				// precision values.
				$maxPrecision = CargoStore::YEAR_ONLY;
				$dateValues = [];
				foreach ( $individualValues as $individualValue ) {
					$realIndividualVal = trim( $individualValue );
					if ( $realIndividualVal == '' ) {
						continue;
					}
					list( $dateValue, $curPrecision ) = CargoStore::getDateValueAndPrecision( $realIndividualVal, $fieldType );
					$dateValues[] = $dateValue;
					if ( $curPrecision < $maxPrecision ) {
						$maxPrecision = $curPrecision;
					}
				}
				$newValue = implode( $delimiter, $dateValues );
				$precision = $maxPrecision;
			} else {
				list( $newValue, $precision ) = CargoStore::getDateValueAndPrecision( $fieldValue, $fieldType );
			}
		} elseif ( $fieldType == 'Integer' ) {
			// Remove digit-grouping character.
			global $wgCargoDigitGroupingCharacter;
			if ( $this->mIsList ) {
				$delimiter = $this->getDelimiter();
				if ( $delimiter != $wgCargoDigitGroupingCharacter ) {
					$fieldValue = str_replace( $wgCargoDigitGroupingCharacter, '', $fieldValue );
				}
				$individualValues = explode( $delimiter, $fieldValue );
				foreach ( $individualValues as &$individualValue ) {
					if ( !is_int( $individualValue ) ) {
						$individualValue = round( $individualValue );
					}
				}
				$newValue = implode( $delimiter, $individualValues );
			} else {
				$newValue = str_replace( $wgCargoDigitGroupingCharacter, '', $fieldValue );
				if ( !is_int( $newValue ) ) {
					$newValue = round( $newValue );
				}
			}
		} elseif ( $fieldType == 'Float' || $fieldType == 'Rating' ) {
			// Remove digit-grouping character, and change
			// decimal mark to '.' if it's anything else.
			global $wgCargoDigitGroupingCharacter;
			global $wgCargoDecimalMark;
			$newValue = str_replace( $wgCargoDigitGroupingCharacter, '', $fieldValue );
			$newValue = str_replace( $wgCargoDecimalMark, '.', $newValue );
		} elseif ( $fieldType == 'Boolean' ) {
			// True = 1, "yes"
			// False = 0, "no"
			$msgForNo = wfMessage( 'htmlform-no' )->text();
			if ( $fieldValue === 0
				|| $fieldValue === '0'
				|| strtolower( $fieldValue ) === 'no'
				|| strtolower( $fieldValue ) == strtolower( $msgForNo ) ) {
				$newValue = '0';
			} else {
				$newValue = '1';
			}
		} else {
			$newValue = $fieldValue;
		}

		$valueArray = [ 'value' => $newValue ];
		if ( $precision !== null ) {
			$valueArray['precision'] = $precision;
		}

		return $valueArray;
	}

}
