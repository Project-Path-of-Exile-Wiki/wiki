<?php

use MediaWiki\Extension\Math\MathRestbaseInterface;

/**
 * Test the interface to access Restbase paths
 * /media/math/check/{type}
 * /media/math/render/{format}/{hash}
 *
 * @covers \MediaWiki\Extension\Math\MathRestbaseInterface
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathRestbaseInterfaceTest extends MediaWikiTestCase {
	protected static $hasRestbase;

	public static function setUpBeforeClass() : void {
		$rbi = new MathRestbaseInterface();
		self::$hasRestbase = $rbi->checkBackend( true );
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() : void {
		$this->markTestSkipped( 'All HTTP requests are banned in tests. See T265628.' );
		parent::setUp();
		if ( !self::$hasRestbase ) {
			$this->markTestSkipped( "Can not connect to Restbase Math interface." );
		}
	}

	public function testConfig() {
		$rbi = new MathRestbaseInterface();
		$this->assertTrue( $rbi->checkBackend() );
	}

	public function testSuccess() {
		$input = '\\sin x^2';
		$rbi = new MathRestbaseInterface( $input );
		$this->assertTrue( $rbi->getSuccess(), "Assuming that $input is valid input." );
		$this->assertEquals( '\\sin x^{2}', $rbi->getCheckedTex() );
		$this->assertStringContainsString( '<mi>sin</mi>', $rbi->getMathML() );
		$url = $rbi->getFullSvgUrl();
		$req = MWHttpRequest::factory( $url );
		$status = $req->execute();
		$this->assertTrue( $status->isOK() );
		$this->assertStringContainsString( '</svg>', $req->getContent() );
	}

	public function testFail() {
		$input = '\\sin\\newcommand';
		$rbi = new MathRestbaseInterface( $input );
		$this->assertFalse( $rbi->getSuccess(), "Assuming that $input is invalid input." );
		$this->assertNull( $rbi->getCheckedTex() );
		$this->assertEquals( 'Illegal TeX function', $rbi->getError()->error->message );
	}

	public function testChem() {
		$input = '\ce{H2O}';
		$rbi = new MathRestbaseInterface( $input, 'chem' );
		$this->assertTrue( $rbi->checkTeX(), "Assuming that $input is valid input." );
		$this->assertTrue( $rbi->getSuccess(), "Assuming that $input is valid input." );
		$this->assertEquals( '{\ce {H2O}}', $rbi->getCheckedTex() );
		$this->assertStringContainsString( '<msubsup>', $rbi->getMathML() );
		$this->assertStringContainsString( '<mtext>H</mtext>', $rbi->getMathML() );
	}

	public function testException() {
		$input = '\\newcommand';
		$rbi = new MathRestbaseInterface( $input );
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'TeX input is invalid.' );
		$rbi->getMathML();
	}

	public function testExceptionSvg() {
		$input = '\\newcommand';
		$rbi = new MathRestbaseInterface( $input );
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'TeX input is invalid.' );
		$rbi->getFullSvgUrl();
	}

	/**
	 * Incorporate the "details" in the error message, if the check requests passes, but the
	 * mml/svg/complete endpoints returns an error
	 */
	public function testLateError() {
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$input = '{"type":"https://mediawiki.org/wiki/HyperSwitch/errors/bad_request","title":"Bad Request","method":"POST","detail":["TeX parse error: Missing close brace"],"uri":"/complete"}';
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Cannot get mml. TeX parse error: Missing close brace' );
		MathRestbaseInterface::throwContentError( 'mml', $input );
	}

	/**
	 * Incorporate the "details" in the error message, if the check requests passes, but the
	 * mml/svg/complete endpoints returns an error
	 */
	public function testLateErrorString() {
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$input = '{"type":"https://mediawiki.org/wiki/HyperSwitch/errors/bad_request","title":"Bad Request","method":"POST","detail": "TeX parse error: Missing close brace","uri":"/complete"}';
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Cannot get mml. TeX parse error: Missing close brace' );
		MathRestbaseInterface::throwContentError( 'mml', $input );
	}

	public function testLateErrorNoDetail() {
		$input = '';
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( 'Cannot get mml. Server problem.' );
		MathRestbaseInterface::throwContentError( 'mml', $input );
	}
}
