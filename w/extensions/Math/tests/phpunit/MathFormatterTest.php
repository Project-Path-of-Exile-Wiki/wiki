<?php

use DataValues\NumberValue;
use DataValues\StringValue;
use MediaWiki\Extension\Math\MathFormatter;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * Test the results of MathFormatter
 *
 * @covers \MediaWiki\Extension\Math\MathFormatter
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathFormatterTest extends MediaWikiTestCase {

	private const SOME_TEX = 'a^2+b^2 < c^2';

	/** @var bool */
	protected static $hasRestbase;

	public static function setUpBeforeClass() : void {
		$rbi = new MathRestbaseInterface();
		self::$hasRestbase = $rbi->checkBackend( true );
	}

	protected function setUp() : void {
		$this->markTestSkipped( 'All HTTP requests are banned in tests. See T265628.' );
		parent::setUp();

		if ( !self::$hasRestbase ) {
			$this->markTestSkipped( 'Can not connect to Restbase Math interface.' );
		}
	}

	/**
	 * Checks the
	 * @covers \MediaWiki\Extension\Math\MathFormatter::__construct
	 */
	public function testBasics() {
		$formatter = new MathFormatter( SnakFormatter::FORMAT_PLAIN );
		// check if the format input was corretly passed to the class
		$this->assertSame( SnakFormatter::FORMAT_PLAIN, $formatter->getFormat(), 'test getFormat' );
	}

	public function testNotStringValue() {
		$formatter = new MathFormatter( SnakFormatter::FORMAT_PLAIN );
		$this->expectException( InvalidArgumentException::class );
		$formatter->format( new NumberValue( 0 ) );
	}

	public function testNullValue() {
		$formatter = new MathFormatter( SnakFormatter::FORMAT_PLAIN );
		$this->expectException( InvalidArgumentException::class );
		$formatter->format( null );
	}

	public function testUnknownFormatFallsBackToMathMl() {
		$formatter = new MathFormatter( 'unknown/unknown' );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertStringContainsString( '</math>', $resultFormat );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\MathFormatter::format
	 */
	public function testUnknownFormatFailure() {
		$formatter = new MathFormatter( 'unknown/unknown' );
		$value = new StringValue( '\noTex' );
		$resultFormat = $formatter->format( $value );
		$this->assertStringContainsString( 'unknown function', $resultFormat );
	}

	public function testFormatPlain() {
		$formatter = new MathFormatter( SnakFormatter::FORMAT_PLAIN );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertSame( self::SOME_TEX, $resultFormat );
	}

	public function testFormatHtml() {
		$formatter = new MathFormatter( SnakFormatter::FORMAT_HTML );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertStringContainsString( '</math>', $resultFormat, 'Result must contain math-tag' );
	}

	public function testFormatDiffHtml() {
		$formatter = new MathFormatter( SnakFormatter::FORMAT_HTML_DIFF );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertStringContainsString( '</math>', $resultFormat, 'Result must contain math-tag' );
		$this->assertStringContainsString( '</h4>', $resultFormat, 'Result must contain a <h4> tag' );
		$this->assertStringContainsString( '</code>', $resultFormat, 'Result must contain a <code> tag' );
		$this->assertStringContainsString(
			'wb-details',
			$resultFormat,
			'Result must contain wb-details class'
		);
		$this->assertStringContainsString(
			htmlspecialchars( self::SOME_TEX ),
			$resultFormat,
			'Result must contain the TeX source'
		);
	}

	public function testFormatXWiki() {
		$tex = self::SOME_TEX;
		$formatter = new MathFormatter( SnakFormatter::FORMAT_WIKI );
		$value = new StringValue( self::SOME_TEX );
		$resultFormat = $formatter->format( $value );
		$this->assertSame( "<math>$tex</math>", $resultFormat, 'Tex wasn\'t properly wrapped' );
	}

}
