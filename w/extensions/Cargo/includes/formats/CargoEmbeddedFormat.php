<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoEmbeddedFormat extends CargoDisplayFormat {

	public static function allowedParameters() {
		return [];
	}

	private function displayRow( $row ) {
		$pageName = reset( $row );
		$wikiText = <<<END
<p style="font-size: small; text-align: right;">[[$pageName]]</p>
{{:$pageName}}


--------------

END;
		return $wikiText;
	}

	/**
	 * @param array $valuesTable
	 * @param array $formattedValuesTable Unused
	 * @param array $fieldDescriptions Unused
	 * @param array $displayParams Unused
	 * @return string
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$text = '';
		foreach ( $valuesTable as $row ) {
			$text .= $this->displayRow( $row );
		}
		return CargoUtils::smartParse( $text, $this->mParser );
	}

}
