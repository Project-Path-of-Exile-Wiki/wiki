<?php
/**
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoCalendarFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'height' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-heightparam' )->parse() ],
			'width' => [ 'type' => 'int', 'label' => wfMessage( 'cargo-viewdata-widthparam' )->parse() ],
			'start date' => [ 'type' => 'date' ],
			'color' => [ 'type' => 'string' ],
			'text color' => [ 'type' => 'string' ]
		];
	}

	/**
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array|null $querySpecificParams
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$this->mOutput->addModules( 'ext.cargo.calendar' );
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'fullcalendar';
		$queryParams['color'] = [];
		$queryParams['text color'] = [];
		foreach ( $sqlQueries as $i => $sqlQuery ) {
			if ( $querySpecificParams != null ) {
				if ( array_key_exists( 'color', $querySpecificParams[$i] ) ) {
					$queryParams['color'][] = $querySpecificParams[$i]['color'];
				} else {
					// Stick an empty value in there, to
					// preserve the order for the queries
					// that do contain a color.
					$queryParams['color'][] = null;
				}
				if ( array_key_exists( 'text color', $querySpecificParams[$i] ) ) {
					$queryParams['text color'][] = $querySpecificParams[$i]['text color'];
				} else {
					// Stick an empty value in there, to
					// preserve the order for the queries
					// that do contain a color.
					$queryParams['text color'][] = null;
				}
			}
		}

		if ( array_key_exists( 'width', $displayParams ) && $displayParams['width'] != '' ) {
			$width = $displayParams['width'];
			// Add on "px", if no unit is defined.
			if ( is_numeric( $width ) ) {
				$width .= "px";
			}
		} else {
			$width = "100%";
		}

		if ( array_key_exists( 'height', $displayParams ) && $displayParams['height'] != '' ) {
			$height = $displayParams['height'];
			// The height should be either a number or "auto".
			if ( !is_numeric( $height ) ) {
				if ( $height != "auto" ) {
					$height = null;
				}
			}
		} else {
			$height = null;
		}

		$attrs = [
			'class' => 'cargoCalendar',
			'dataurl' => $ce->getFullURL( $queryParams ),
			'style' => "width: $width",
			'height' => $height,
		];
		if ( array_key_exists( 'view', $displayParams ) && $displayParams['view'] != '' ) {
			$view = $displayParams['view'];
			// Enable simpler view names.
			if ( $view == 'day' ) {
				$view = 'agendaDay';
			} elseif ( $view == 'week' ) {
				$view = 'agendaWeek';
			}
			$attrs['startview'] = $view;
		} else {
			$attrs['startview'] = 'month';
		}
		if ( array_key_exists( 'start date', $displayParams ) && $displayParams['start date'] != '' ) {
			$attrs['startdate'] = $displayParams['start date'];
		}
		$text = Html::rawElement( 'div', $attrs, '' );

		return $text;
	}

}
