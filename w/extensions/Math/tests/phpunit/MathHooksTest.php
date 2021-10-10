<?php

use MediaWiki\Extension\Math\Hooks;

/**
 * @covers \MediaWiki\Extension\Math\Hooks
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathHooksTest extends MediaWikiTestCase {

	public function testMathModeToString() {
		$default = 'png-testing'; // use a different string for testing only
		$testCases = [
			'MW_MATH_SIMPLE'      => $default,
			'MW_MATH_HTML'        => $default,
			'MW_MATH_MODERN'      => $default,
			'MW_MATH_MATHJAX'     => $default,
			'MW_MATH_LATEXML_JAX' => $default,
			'MW_MATH_PNG'         => 'png',
			'MW_MATH_SOURCE'      => 'source',
			'MW_MATH_MATHML'      => 'mathml',
			'MW_MATH_LATEXML'     => 'latexml',
			1                     => $default,
			2                     => $default,
			4                     => $default,
			6                     => $default,
			8                     => $default,
			0                     => 'png',
			3                     => 'source',
			5                     => 'mathml',
			7                     => 'latexml',
			'png'                 => 'png',
			'source'              => 'source',
			'mathml'              => 'mathml',
			'latexml'             => 'latexml',
		];
		foreach ( $testCases as $input => $expected ) {
			$real = Hooks::mathModeToString( $input, $default );
			$this->assertEquals( $expected, $real, "Conversion math mode $input -> $expected" );
		}
	}

	public function testMathStyleToString() {
		$default = 'inlineDisplaystyle-test';
		$testCases = [
			'MW_MATHSTYLE_INLINE_DISPLAYSTYLE'  => 'inlineDisplaystyle',
			'MW_MATHSTYLE_DISPLAY'              => 'display',
			'MW_MATHSTYLE_INLINE'               => 'inline',
			0                                   => 'inlineDisplaystyle',
			1                                   => 'display',
			2                                   => 'inline',
			'inlineDisplaystyle'                => 'inlineDisplaystyle',
			'display'                           => 'display',
			'inline'                            => 'inline',
		];
		foreach ( $testCases as $input => $expected ) {
			$real = Hooks::mathStyleToString( $input, $default );
			$this->assertEquals( $expected, $real, "Conversion in math style" );
		}
	}

	public function testMathCheckToString() {
		$default = 'always-default';
		$testCases = [
			'MW_MATH_CHECK_ALWAYS'  => 'always',
			'MW_MATH_CHECK_NEVER'   => 'never',
			'MW_MATH_CHECK_NEW'     => 'new',
			0                       => 'always',
			1                       => 'never',
			2                       => 'new',
			'always'                => 'always',
			'never'                 => 'never',
			'new'                   => 'new',
			true                    => 'never',
			false                   => 'always'
		];

		foreach ( $testCases as $input => $expected ) {
			$real = Hooks::mathCheckToString( $input, $default );
			$this->assertEquals( $expected, $real, "Conversion in math check method" );
		}
	}

	public function testMathModeToHash() {
		$default = 0;
		$testCases = [
			'png'    => 0,
			'source' => 3,
			'mathml' => 5,
			'latexml' => 7,
			'invalid' => $default
		];

		foreach ( $testCases as $input => $expected ) {
			$real = Hooks::mathModeToHashKey( $input, $default );
			$this->assertEquals( $expected, $real, "Conversion to hash key" );
		}
	}

	public function testGetMathNames() {
		$real = Hooks::getMathNames();
		$this->assertEquals( 'PNG images', $real['png'] );
	}

}
