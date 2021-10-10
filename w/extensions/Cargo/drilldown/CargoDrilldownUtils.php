<?php
/**
 * A class for static helper functions for the Cargo extension's
 * drill-down functionality.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDrilldownUtils {

	/**
	 * Appears to be unused
	 *
	 * @global string $cgScriptPath
	 * @param array &$vars
	 * @return bool
	 */
	public static function setGlobalJSVariables( &$vars ) {
		global $cgScriptPath;

		$vars['cgDownArrowImage'] = "$cgScriptPath/drilldown/resources/down-arrow.png";
		$vars['cgRightArrowImage'] = "$cgScriptPath/drilldown/resources/right-arrow.png";
		return true;
	}

	/**
	 * Return the month represented by the given number.
	 *
	 * @global Language $wgLang
	 * @param int $month
	 * @return string Month name in user language
	 * @todo This function should be replaced with direct calls to Language::getMonthName()
	 */
	public static function monthToString( $month ) {
		$monthInt = intval( $month );
		if ( $monthInt < 1 || $monthInt > 12 ) {
			return false;
		}

		global $wgLang;
		return $wgLang->getMonthName( $monthInt );
	}

	/**
	 * Return the month number (1-12) which precisely matches the string sent in the user's language
	 *
	 * @global Language $wgLang
	 * @param string $str
	 * @param Language|null $language
	 * @return int|bool
	 */
	public static function stringToMonth( $str, Language $language = null ) {
		if ( $language === null ) {
			global $wgLang;
			$language = $wgLang;
		}

		return array_search( $str, $language->getMonthNamesArray() );
	}
}
