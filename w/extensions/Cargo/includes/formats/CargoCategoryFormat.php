<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCategoryFormat extends CargoListFormat {

	public static function allowedParameters() {
		return [
			'columns' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-columnsparam' )->parse() ]
		];
	}

	/**
	 * @global Language $wgContLang
	 * @param array $valuesTable
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$contLang = CargoUtils::getContentLang();

		if ( array_key_exists( 'columns', $displayParams ) && $displayParams['columns'] != '' ) {
			$numColumns = max( $displayParams['columns'], 1 );
		} else {
			$numColumns = 3;
		}
		if ( array_key_exists( 'header field', $displayParams ) ) {
			$headerField = str_replace( '_', ' ', $displayParams['header field'] );
			if ( count( $valuesTable ) > 0 && !array_key_exists( $headerField, $valuesTable[0] ) ) {
				throw new MWException( "Error: the header field \"$headerField\" must be among this query's fields." );
			}
			$this->undisplayedFields[] = $headerField;
		} else {
			$headerField = null;
		}

		$result = '';
		$num = count( $valuesTable );

		$prev_first_char = "";
		$rows_per_column = ceil( $num / $numColumns );
		// Column width is a percentage.
		$column_width = floor( 100 / $numColumns );

		// Print all result rows:
		$rowindex = 0;

		foreach ( $formattedValuesTable as $i => $row ) {
			if ( $headerField == null ) {
				$curValue = reset( $valuesTable[$i] );
			} else {
				$curValue = $valuesTable[$i][$headerField];
			}
			// Ignore the namespace when setting the index character.
			if ( array_key_exists( 'namespace', $row ) ) {
				$curValue = str_replace( $row['namespace'] . ':', '', $curValue );
			}
			$cur_first_char = $contLang->firstChar( $curValue );

			if ( $rowindex % $rows_per_column == 0 ) {
				$result .= "\n\t\t\t<div style=\"float: left; width: $column_width%;\">\n";
				if ( $cur_first_char == $prev_first_char ) {
					$result .= "\t\t\t\t<h3>$cur_first_char " .
						wfMessage( 'listingcontinuesabbrev' )->text() . "</h3>\n				<ul>\n";
				}
			}

			// If we're at a new first letter, end
			// the last list and start a new one.
			if ( $cur_first_char != $prev_first_char ) {
				if ( $rowindex % $rows_per_column > 0 ) {
					$result .= "				</ul>\n";
				}
				$result .= "\t\t\t\t<h3>$cur_first_char</h3>\n				<ul>\n";
			}
			$prev_first_char = $cur_first_char;

			$result .= '<li>' . $this->displayRow( $row, $fieldDescriptions ) . "</li>\n";

			// end list if we're at the end of the column
			// or the page
			if ( ( $rowindex + 1 ) % $rows_per_column == 0 && ( $rowindex + 1 ) < $num ) {
				$result .= "\t\t\t\t</ul>\n\t\t\t</div> <!-- end column -->";
			}

			$rowindex++;
		}

		$result .= "</ul>\n</div> <!-- end column -->";
		// clear all the CSS floats
		$result .= "\n" . '<br style="clear: both;"/>';

		// <H3> will generate TOC entries otherwise. Probably need another way
		// to accomplish this -- user might still want TOC for other page content.
		// $result .= '__NOTOC__';
		return $result;
	}

}
