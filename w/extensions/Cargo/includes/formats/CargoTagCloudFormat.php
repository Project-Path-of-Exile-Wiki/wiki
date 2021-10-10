<?php
/**
 * @author Cindy Cicalese
 * @ingroup Cargo
 */

class CargoTagCloudFormat extends CargoDisplayFormat {

	public static function allowedParameters() {
		return [
			'template' => [ 'type' => 'string' ],
			'min size' => [ 'type' => 'int' ],
			'max size' => [ 'type' => 'int' ]
		];
	}

	/**
	 * @param array $valuesTagCloud Unused
	 * @param array $formattedValuesTagCloud
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string HTML
	 */
	public function display( $valuesTagCloud, $formattedValuesTagCloud, $fieldDescriptions, $displayParams ) {
		$this->mOutput->addModuleStyles( 'ext.cargo.main' );

		if ( count( $fieldDescriptions ) < 2 ) {
			return '';
		}

		$fieldNames = array_keys( $fieldDescriptions );
		$tagFieldName = $fieldNames[0];
		$countFieldName = $fieldNames[1];

		if ( $fieldDescriptions[$countFieldName]->mType != 'Integer' ) {
			return '';
		}

		$tags = [];

		foreach ( $formattedValuesTagCloud as $row ) {

			$tag = $row[$tagFieldName];
			$count = $row[$countFieldName];

			if ( strlen( $tag ) > 0 && is_numeric( $count ) && $count > 0 ) {

				$tags[$tag] = $count;

			}

		}

		if ( $tags == [] ) {
			return '';
		}

		if ( isset( $displayParams['max size'] ) ) {
			$maxSize = $displayParams['max size'];
		} else {
			$maxSize = 200;
		}

		if ( isset( $displayParams['min size'] ) ) {
			$minSize = $displayParams['min size'];
		} else {
			$minSize = 80;
		}

		$maxSizeIncrease = $maxSize - $minSize;

		$minCount = min( $tags );
		$maxCount = max( $tags );

		if ( $maxCount == $minCount ) {
			$size = $minSize + $maxSizeIncrease / 2;
		} else {
			$denominator = log( $maxCount ) - log( $minCount );
			$sizes = [];
		}

		$attrs = [
			'class' => 'cargoTagCloud',
			'align' => 'justify'
		];

		$text = Html::openElement( 'div', $attrs );

		foreach ( $tags as $tag => $count ) {
			if ( isset( $displayParams['template'] ) ) {
				$tagstring = '{{' . $displayParams['template'] .
					'|' . $tag . '|' . $count . '}}';
				$tagstring = CargoUtils::smartParse( $tagstring, null );
			} else {
				$tagstring = $tag;
			}
			if ( $maxCount != $minCount ) {
				$countstr = strval( $count );
				if ( !isset( $sizes[$countstr] ) ) {
					$sizes[$countstr] =
						$minSize + $maxSizeIncrease *
						( log( $count ) - log( $minCount ) ) /
						$denominator;
				}
				$size = $sizes[$countstr];
			}
			$text .= Html::rawElement( 'span',
				[
					'style' => 'font-size:' . $size . '%;white-space:nowrap;'
				],
				$tagstring );
			$text .= ' ';
		}

		$text .= Html::closeElement( 'div' );

		return $text;
	}

}
