<?php

/**
 * Helper class for testing
 * @author physikerwelt
 * @see MWHttpRequestTester
 */
class MathMLHttpRequestTester {

	public static function factory() {
		return new self();
	}

	public static function execute() {
		return new MathMLTestStatus();
	}

	public static function getContent() {
		return MathMathMLTest::$content;
	}

}
