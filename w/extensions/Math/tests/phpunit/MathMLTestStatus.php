<?php

/**
 * Helper class for testing
 * @author physikerwelt
 * @see Status
 */
class MathMLTestStatus {

	public static function isGood() {
		return MathMathMLTest::$good;
	}

	public static function hasMessage( $s ) {
		if ( $s == 'http-timed-out' ) {
			return MathMathMLTest::$timeout;
		} else {
			return false;
		}
	}

	public static function getHtml() {
		return MathMathMLTest::$html;
	}

	public static function getWikiText() {
		return MathMathMLTest::$html;
	}

}
