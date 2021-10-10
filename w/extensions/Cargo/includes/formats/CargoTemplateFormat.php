<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTemplateFormat extends CargoDisplayFormat {

	public static function allowedParameters() {
		return [
			'template' => [ 'type' => 'string' ],
			'named args' => [ 'type' => 'boolean' ],
			'delimiter' => [ 'type' => 'string', 'label' => wfMessage( 'cargo-viewdata-delimiterparam' )->parse() ]
		];
	}

	private function displayRow( $templateName, $row, $fieldDescriptions, $namedArgs ) {
		$wikiText = '{{' . $templateName;
		// If we're not using named arguments, we add the field number
		// in to the template call, to not mess up values that contain '='.
		$fieldNum = 1;
		foreach ( $fieldDescriptions as $fieldName => $fieldDescription ) {
			if ( array_key_exists( $fieldName, $row ) ) {
				$paramName = $namedArgs ? $fieldName : $fieldNum;
				// HTML-decode the Wikitext values, which were
				// encoded in CargoSQLQuery::run().
				// We do this only for the "template" format
				// because it's the only one that uses the
				// unformatted values - the formatted values
				// do this HTML-encoding on their own.
				if ( $fieldDescription->mType == 'Wikitext' || $fieldDescription->mType == 'Wikitext string' ) {
					$value = htmlspecialchars_decode( $row[$fieldName] );
				} else {
					$value = $row[$fieldName];
				}
				// Escape pipes within the values so that they
				// aren't interpreted as template pipes.
				$value = str_replace( '|', '{{!}}', $value );
				$wikiText .= '|' . $paramName . '=' . $value;
				$fieldNum++;
			}
		}
		$wikiText .= "\n}}";
		return $wikiText;
	}

	/**
	 * @param array $valuesTable
	 * @param array $formattedValuesTable Unused
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string
	 * @throws MWException
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		if ( !array_key_exists( 'template', $displayParams ) ) {
			throw new MWException( wfMessage( "cargo-query-missingparam", "template", "template" )->parse() );
		}

		$templateName = $displayParams['template'];
		$namedArgs = false;
		if ( array_key_exists( 'named args', $displayParams ) ) {
			$namedArgs = strtolower( $displayParams['named args'] ) == 'yes';
		}
		$delimiter = ( array_key_exists( 'delimiter', $displayParams ) ) ?
			$displayParams['delimiter'] : '';
		$delimiter = str_replace( '\n', "\n", $delimiter );
		$text = '';
		foreach ( $valuesTable as $i => $row ) {
			if ( $i > 0 ) {
				$text .= $delimiter;
			}
			$text .= $this->displayRow( $templateName, $row, $fieldDescriptions, $namedArgs );
		}
		global $wgTitle;
		if ( $wgTitle != null && $wgTitle->isSpecialPage() ) {
			return CargoUtils::smartParse( $text, $this->mParser );
		} else {
			return $text;
		}
	}

}
