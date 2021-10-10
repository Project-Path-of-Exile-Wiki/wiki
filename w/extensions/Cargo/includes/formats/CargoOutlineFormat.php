<?php

/**
 * A class to print query results in an outline format, along with some
 * helper classes to handle the aggregation
 *
 * Code is based heavily on the code for the 'outline' format in the
 * Semantic Result Formats extension.
 *
 * @author Yaron Koren
 */

class CargoOutlineFormat extends CargoListFormat {

	/** @var string[] */
	protected $mOutlineFields = [];
	/** @var array|null */
	public $mFieldDescriptions;

	public static function allowedParameters() {
		return [ 'outline fields' => [ 'type' => 'string' ] ];
	}

	private function printTree( $outlineTree, $level = 0 ) {
		$text = "";
		if ( $outlineTree->mUnsortedRows !== null ) {
			$text .= "<ul>\n";
			foreach ( $outlineTree->mUnsortedRows as $row ) {
				$text .= Html::rawElement( 'li', null,
					$this->displayRow( $row->mDisplayFields, $this->mFieldDescriptions ) ) . "\n";
			}
			$text .= "</ul>\n";
		}
		if ( $level > 0 ) {
			$text .= "<ul>\n";
		}
		$numLevels = count( $this->mOutlineFields );
		// Set font size and weight depending on level we're at.
		$fontLevel = $level;
		if ( $numLevels < 4 ) {
			$fontLevel += ( 4 - $numLevels );
		}
		if ( $fontLevel == 0 ) {
			$fontSize = 'x-large';
		} elseif ( $fontLevel == 1 ) {
			$fontSize = 'large';
		} elseif ( $fontLevel == 2 ) {
			$fontSize = 'medium';
		} else {
			$fontSize = 'small';
		}
		if ( $fontLevel == 3 ) {
			$fontWeight = 'bold';
		} else {
			$fontWeight = 'regular';
		}
		foreach ( $outlineTree->mTree as $node ) {
			$text .= Html::rawElement( 'p',
				[ 'style' =>
				"font-size: $fontSize; font-weight: $fontWeight;" ], $node->mFormattedValue ) . "\n";
			$text .= $this->printTree( $node, $level + 1 );
		}
		if ( $level > 0 ) {
			$text .= "</ul>\n";
		}
		return $text;
	}

	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		if ( !array_key_exists( 'outline fields', $displayParams ) ) {
			throw new MWException( wfMessage( "cargo-query-missingparam", "outline fields", "outline" )->parse() );
		}
		$outlineFields = explode( ',', $displayParams['outline fields'] );
		$this->mOutlineFields = [];
		foreach ( $outlineFields as $outlineField ) {
			$modifiedOutlineField = trim( $outlineField );
			if ( $modifiedOutlineField[0] != '_' ) {
				$modifiedOutlineField = str_replace( '_', ' ', $modifiedOutlineField );
			}
			$this->mOutlineFields[] = $modifiedOutlineField;
		}
		$this->mFieldDescriptions = $fieldDescriptions;

		// For each result row, create an array of the row itself
		// and all its sorted-on fields, and add it to the initial
		// 'tree'.
		$outlineTree = new CargoOutlineTree();
		foreach ( $valuesTable as $rowNum => $queryResultsRow ) {
			$coRow = new CargoOutlineRow();
			foreach ( $queryResultsRow as $fieldName => $value ) {
				$formattedValue = $formattedValuesTable[$rowNum][$fieldName];
				if ( in_array( $fieldName, $this->mOutlineFields ) ) {
					if ( property_exists( $fieldDescriptions[$fieldName], 'isList' ) ) {
						$delimiter = $fieldDescriptions[$fieldName]['delimiter'];
						$coRow->addOutlineFieldValues( $fieldName, array_map( 'trim', explode( $delimiter, $value ) ),
							array_map( 'trim', explode( $delimiter, $formattedValue ) ) );
					} else {
						$coRow->addOutlineFieldValue( $fieldName, $value, $formattedValue );
					}
				} else {
					$coRow->addDisplayFieldValue( $fieldName, $formattedValue );
				}
			}
			$outlineTree->addRow( $coRow );
		}

		// Now, cycle through the outline fields, creating the tree.
		foreach ( $this->mOutlineFields as $outlineField ) {
			$outlineTree->addField( $outlineField );
		}
		$result = $this->printTree( $outlineTree );

		return $result;
	}

}
