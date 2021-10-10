<?php
/**
 * Class for the #recurring_event function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoRecurringEvent {

	/**
	 * Gets the "Julian calendar days" of the specified date.
	 *
	 * @param string[] $date
	 * @return int
	 */
	public static function getJD( $date ) {
		return gregorianToJD( $date['month'], $date['day'], $date['year'] );
	}

	/**
	 * Handles the #recurring_event parser function - prints out a
	 * string that is a delimited list of recurring events.
	 *
	 * @global int $wgCargoRecurringEventMaxInstances
	 * @param Parser &$parser Unused
	 * @return string
	 */
	public static function run( &$parser ) {
		global $wgCargoRecurringEventMaxInstances;

		// Code based in large part on the code for Semantic
		// MediaWiki's #set_recurring_event function.
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$allDateStrings = [];
		$startDate = $endDate = $unit = $period = $weekNum = null;
		$timeString = null;
		$delimiter = '; '; // default
		$includedDates = [];
		$excludedDatesJD = [];

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );

			if ( $key == 'start' ) {
				$startDate = date_parse( $value );
				// We can assume that the time of day for
				// all automatically-generated dates will
				// be the same as that of the start date (if
				// it was set at all).
				$curHour = $startDate['hour'];
				$curMinute = $startDate['minute'];
				if ( $curHour !== false && $curMinute !== false ) {
					$timeString = ' ' . str_pad( $curHour, 2, '0', STR_PAD_LEFT ) . ':'
						. str_pad( $curMinute, 2, '0', STR_PAD_LEFT );
				}
			} elseif ( $key == 'end' ) {
				$endDate = date_parse( $value );
			} elseif ( $key == 'unit' ) {
				$unit = $value;
			} elseif ( $key == 'period' ) {
				$period = $value;
			} elseif ( $key == 'week number' ) {
				$weekNum = $value;
			} elseif ( $key == 'include' ) {
				$includedDates = explode( ';', $value );
			} elseif ( $key == 'exclude' ) {
				$excludedDates = explode( ';', $value );
				foreach ( $excludedDates as $dateStr ) {
					$excludedDatesJD[] = self::getJD( date_parse( $dateStr ) );
				}
			} elseif ( $key == 'delimiter' ) {
				$delimiter = $value;
			}
		}

		if ( $startDate === null ) {
			return CargoUtils::formatError( 'Start date must be specified.' );
		}
		if ( $unit === null ) {
			return CargoUtils::formatError( 'Unit must be specified.' );
		}

		// If the period is null, or outside of normal bounds,
		// set it to 1.
		if ( $period === null ) {
			$period = 1;
		}

		// Handle 'week number', but only if it's of unit 'month'.
		if ( $unit == 'month' && $weekNum !== null ) {
			$unit = 'dayofweekinmonth';
			if ( $weekNum < -4 || $weekNum > 5 || $weekNum == 0 ) {
				$weekNum = null;
			}
		}

		if ( $unit == 'dayofweekinmonth' && $weekNum === null ) {
			$weekNum = ceil( $startDate['day'] / 7 );
		}

		// Get the Julian day value for both the start and
		// end date.
		$endDateJD = self::getJD( $endDate );
		$curDate = $startDate;
		$curDateJD = self::getJD( $curDate );

		$instanceNum = 0;
		do {
			$instanceNum++;
			$excludeDate = ( in_array( $curDateJD, $excludedDatesJD ) );
			if ( !$excludeDate ) {
				$dateStr = $curDate['year'] . '-' . $curDate['month'] . '-' . $curDate['day'] . $timeString;
				$allDateStrings[] = $dateStr;
			}

			// Now get the next date.
			// Handling is different depending on whether it's
			// month/year or week/day since the latter is a set
			// number of days while the former isn't.
			if ( $unit === 'year' || $unit == 'month' ) {
				$curYear = $curDate['year'];
				$curMonth = $curDate['month'];
				$curDay = $startDate['day'];

				if ( $unit == 'year' ) {
					$curYear += $period;
					$displayMonth = $curMonth;
				} else { // $unit === 'month'
					$curMonth += $period;
					$curYear += (int)( ( $curMonth - 1 ) / 12 );
					$curMonth %= 12;
					$displayMonth = ( $curMonth == 0 ) ? 12 : $curMonth;
				}

				// If the date is February 29, and this isn't
				// a leap year, change it to February 28.
				if ( $curMonth == 2 && $curDay == 29 ) {
					if ( !date( 'L', strtotime( "$curYear-1-1" ) ) ) {
						$curDay = 28;
					}
				}

				$dateStr = "$curYear-$displayMonth-$curDay" . $timeString;
				$curDate = date_parse( $dateStr );
				$allDateStrings = array_merge( $allDateStrings, $includedDates );
				$curDateJD = self::getJD( $curDate );
			} elseif ( $unit == 'dayofweekinmonth' ) {
				// e.g., "3rd Monday of every month"
				$prevMonth = $curDate['month'];
				$prevYear = $curDate['year'];

				$newMonth = ( $prevMonth + $period ) % 12;
				if ( $newMonth == 0 ) {
					$newMonth = 12;
				}

				$newYear = $prevYear + floor( ( $prevMonth + $period - 1 ) / 12 );
				$curDateJD += ( 28 * $period ) - 7;

				// We're sometime before the actual date now -
				// keep incrementing by a week, until we get there.
				do {
					$curDateJD += 7;
					$curDate = date_parse( JDToGregorian( $curDateJD ) );
					$rightMonth = ( $curDate['month'] == $newMonth );

					if ( $weekNum < 0 ) {
						$nextWeekJD = $curDateJD;

						do {
							$nextWeekJD += 7;
							$nextWeekDate = self::getJulianDayTimeValue( $nextWeekJD );
							$rightWeek = ( $nextWeekDate['month'] != $newMonth ) ||
								( $nextWeekDate['year'] != $newYear );
						} while ( !$rightWeek );

						$curDateJD = $nextWeekJD + ( 7 * $weekNum );
						$curDate = self::getJulianDayTimeValue( $curDateJD );
					} else {
						$curWeekNum = ceil( $curDate['day'] / 7 );
						$rightWeek = ( $curWeekNum == $weekNum );

						if ( $weekNum == 5 && ( $curDate['month'] % 12 == ( $newMonth + 1 ) % 12 ) ) {
							$curDateJD -= 7;
							$curDate = self::getJulianDayTimeValue( $curDateJD );
							$rightMonth = $rightWeek = true;
						}
					}
				} while ( !$rightMonth || !$rightWeek );
			} else { // $unit == 'day' or 'week'
				// Assume 'day' if it's none of the above.
				$curDateJD += ( $unit === 'week' ) ? 7 * $period : $period;
				$curDate = date_parse( JDToGregorian( $curDateJD ) );
			}

			// Should we stop?
			$reachedEndDate = ( $instanceNum > $wgCargoRecurringEventMaxInstances ||
				( $endDate !== null && ( $curDateJD > $endDateJD ) ) );
		} while ( !$reachedEndDate );

		// Add in the 'include' dates as well.
		$allDateStrings = array_filter( array_merge( $allDateStrings, $includedDates ) );

		return implode( $delimiter, $allDateStrings );
	}

}
