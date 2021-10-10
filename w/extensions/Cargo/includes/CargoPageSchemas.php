<?php
/**
 * Static functions for use by the Page Schemas extension.
 *
 * @author Yaron Koren
 */

class CargoPageSchemas extends PSExtensionHandler {
	public static function registerClass() {
		global $wgPageSchemasHandlerClasses;
		$wgPageSchemasHandlerClasses[] = 'CargoPageSchemas';
		return true;
	}

	/**
	 * Returns an object containing information on a Cargo field,
	 * based on XML from the Page Schemas extension.
	 */
	public static function createPageSchemasObject( $tagName, $xml ) {
		$cargoArray = [];
		if ( $tagName == "cargo_TemplateDetails" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag == $tagName ) {
					foreach ( $child->children() as $tag => $elem ) {
						$cargoArray[$tag] = (string)$elem;
					}
					return $cargoArray;
				}
			}
		}
		if ( $tagName == "cargo_Field" ) {
			foreach ( $xml->children() as $tag => $child ) {
				if ( $tag != $tagName ) {
					continue;
				}
				$allowedValues = [];
				foreach ( $child->children() as $prop => $value ) {
					if ( $prop == "AllowedValue" ) {
						$allowedValues[] = (string)$value;
					} else {
						$cargoArray[$prop] = (string)$value;
					}
				}
				$cargoArray['AllowedValues'] = $allowedValues;
				return $cargoArray;
			}
		}

		return null;
	}

	public static function getDisplayColor() {
		return '#e9cdff';
	}

	public static function getTemplateDisplayString() {
		return wfMessage( 'specialpages-group-cargo' )->escaped();
	}

	public static function getTemplateValues( $psTemplate ) {
		// TODO - fix this.
		$values = [];
		if ( $psTemplate instanceof PSTemplate ) {
			$psTemplate = $psTemplate->getXML();
		}
		foreach ( $psTemplate->children() as $tag => $child ) {
			if ( $tag == "cargo_TemplateDetails" ) {
				foreach ( $child->children() as $prop ) {
					$values[$prop->getName()] = (string)$prop;
				}
			}
		}
		return $values;
	}

	/**
	 * Displays Cargo details for one template in the Page Schemas XML.
	 */
	public static function getTemplateDisplayValues( $templateXML ) {
		$templateValues = self::getTemplateValues( $templateXML );
		if ( count( $templateValues ) == 0 ) {
			return null;
		}

		$displayValues = [];
		foreach ( $templateValues as $key => $value ) {
			if ( $key == 'Table' ) {
				$propName = 'Table';
			}
			$displayValues[$propName] = $value;
		}
		return [ null, $displayValues ];
	}

	public static function getFieldDisplayString() {
		return wfMessage( 'cargo-pageschemas-cargofield' )->text();
	}

	public static function isTemplateDataMultipleInstanceOnly() {
		return false;
	}

	public static function getTemplateEditingHTML( $psTemplate ) {
		$hasExistingValues = false;
		$tableName = null;
		if ( $psTemplate !== null ) {
			$cargoArray = $psTemplate->getObject( 'cargo_TemplateDetails' );
			if ( $cargoArray !== null ) {
				$hasExistingValues = true;
				$tableName = PageSchemas::getValueFromObject( $cargoArray, 'Table' );
			}
		}

		$text = "\t<p>" . wfMessage( 'cargo-pageschemas-tablename' )->text() . ' ' .
			Html::input( 'cargo_template_table_name_num', $tableName, 'text', [ 'size' => 30 ] ) . "</p>\n";

		return [ $text, $hasExistingValues ];
	}

	/**
	 * Returns the HTML for setting the options for the Cargo section
	 * in Page Schemas' "edit schema" page.
	 */
	public static function getFieldEditingHTML( $psField ) {
		global $wgCargoFieldTypes;

		$cargoArray = [];
		$hasExistingValues = false;
		if ( $psField !== null ) {
			$cargoArray = $psField->getObject( 'cargo_Field' );
			if ( $cargoArray !== null ) {
				$hasExistingValues = true;
			}
		}

		$fieldType = PageSchemas::getValueFromObject( $cargoArray, 'Type' );

		$allowedValues = PageSchemas::getValueFromObject( $cargoArray, 'AllowedValues' );
		if ( $allowedValues === null ) {
			$allowedValuesString = '';
		} else {
			$allowedValuesString = implode( ', ', $allowedValues );
		}

		$typeLabel = wfMessage( 'pf_createproperty_proptype' )->escaped();
		if ( $typeLabel == '' ) {
			$typeLabel = 'Type:';
		}
		$allowedValuesLabel = wfMessage( 'pf_createproperty_allowedvalsinput' )->escaped();
		if ( $allowedValuesLabel == '' ) {
			$allowedValuesLabel = 'Allowed values:';
		}

		$html_text = "<p>$typeLabel ";

		$selectBody = '';
		foreach ( $wgCargoFieldTypes as $type ) {
			$optionAttrs = [ 'value' => $type ];
			if ( $type == $fieldType ) {
				$optionAttrs['selected'] = true;
			}
			$selectBody .= Html::element( 'option', $optionAttrs, $type ) . "\n";
		}
		$html_text .= Html::rawElement( 'select', [ 'name' => 'cargo_field_type_num' ], $selectBody ) . "\n";
		$html_text .= "<p>$allowedValuesLabel<br />\n";
		$html_text .= Html::input( 'cargo_field_allowed_values_num', $allowedValuesString, 'text', [ 'size' => 100 ] );
		$html_text .= "\t</p>\n";

		return [ $html_text, $hasExistingValues ];
	}

	/**
	 * Creates Page Schemas XML from Cargo information on templates.
	 */
	public static function createTemplateXMLFromForm() {
		global $wgRequest;

		$xmlPerTemplate = [];
		$templateNum = -1;
		foreach ( $wgRequest->getValues() as $var => $val ) {
			$val = str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $val );
			if ( substr( $var, 0, 26 ) == 'cargo_template_table_name_' ) {
				$templateNum = substr( $var, 26 );
				$xml = '<cargo_TemplateDetails>';
				if ( !empty( $val ) ) {
					$xml .= "<Table>$val</Table>";
				}
				$xml .= '</cargo_TemplateDetails>';
								$xmlPerTemplate[$templateNum] = $xml;
			}
		}
		return $xmlPerTemplate;
	}

	public static function createFieldXMLFromForm() {
		global $wgRequest;

		$fieldNum = -1;
		$xmlPerField = [];
		foreach ( $wgRequest->getValues() as $var => $val ) {
			if ( substr( $var, 0, 17 ) == 'cargo_field_type_' ) {
				$xml = '<cargo_Field>';
				$fieldNum = substr( $var, 17 );
				if ( !empty( $val ) ) {
					$xml .= "<Type>$val</Type>";
				}
			} elseif ( substr( $var, 0, 27 ) == 'cargo_field_allowed_values_' ) {
				if ( !empty( $val ) ) {
					// Replace the comma substitution character that has no chance of
					// being included in the values list - namely, the ASCII beep.
					$listSeparator = ',';
					$allowedValuesStr = str_replace( "\\$listSeparator", "\a", $val );
					$allowedValuesArray = explode( $listSeparator, $allowedValuesStr );
					foreach ( $allowedValuesArray as $value ) {
						// Replace beep back with comma, trim.
						$value = str_replace( "\a", $listSeparator, trim( $value ) );
						$xml .= '<AllowedValue>' . $value . '</AllowedValue>';
					}
				}
				$xml .= '</cargo_Field>';
				$xmlPerField[$fieldNum] = $xml;
			}
		}

		return $xmlPerField;
	}

	/**
	 * Displays the information about the Cargo field (if any)
	 * for one field in the Page Schemas XML.
	 */
	public static function getFieldDisplayValues( $field_xml ) {
		foreach ( $field_xml->children() as $tag => $child ) {
			if ( $tag == "cargo_Field" ) {
				$values = [];
				$allowedValues = [];
				foreach ( $child->children() as $prop => $value ) {
					if ( $prop == "AllowedValue" ) {
						$allowedValues[] = $value;
					} else {
						$values[$prop] = $value;
					}
				}
				$allowedValuesStr = implode( ', ', $allowedValues );
				$allowedValuesLabel = wfMessage( 'pf_createclass_allowedvalues' )->escaped();
				if ( $allowedValuesLabel == '' ) {
					$allowedValuesLabel = 'Allowed values:';
				}
				$values[$allowedValuesLabel] = $allowedValuesStr;
				return [ null, $values ];
			}
		}
		return null;
	}
}
