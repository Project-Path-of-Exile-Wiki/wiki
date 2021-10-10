<?php

use MediaWiki\Extension\Math\InputCheck\RestbaseChecker;

/**
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathInputCheckRestbaseTest extends MediaWikiTestCase {
	/** @var bool */
	protected static $hasRestbase;
	/** @var RestbaseChecker */
	protected $BadObject;
	/** @var RestbaseChecker */
	protected $GoodObject;

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
		$this->BadObject = new RestbaseChecker( '\newcommand{\text{do evil things}}' );
		$this->GoodObject = new RestbaseChecker( '\sin\left(\frac12x\right)' );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\RestbaseChecker::getError
	 */
	public function testGetError() {
		$this->assertNull( $this->GoodObject->getError() );
		$this->assertNull( $this->BadObject->getError() );
		$this->BadObject->isValid();
		$this->GoodObject->isValid();
		$this->assertNull( $this->GoodObject->getError() );
		$expectedMessage = wfMessage(
				'math_unknown_function', '\newcommand'
		)->inContentLanguage()->escaped();
		$this->assertStringContainsString( $expectedMessage, $this->BadObject->getError() );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\RestbaseChecker::getError
	 */
	public function testErrorSyntax() {
		$o = new RestbaseChecker( '\left(' );
		$this->assertFalse( $o->isValid() );
		$expectedMessage = wfMessage( 'math_syntax_error' )->inContentLanguage()->escaped();
		$this->assertStringContainsString( $expectedMessage, $o->getError() );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\RestbaseChecker::getError
	 */
	public function testErrorLexing() {
		$o = new RestbaseChecker( "\x61\xCC\x81" );
		$this->assertFalse( $o->isValid() );
		// Lexical errors are no longer supported. The new error message
		// Expected "-", "[", "\\\\",
		// "\\\\begin", "\\\\begin{", "]", "^", "_", "{", [ \\t\\n\\r], [%$], [().], [,:;?!\\\'],
		// [-+*=], [0-9], [><~], [\\/|] or [a-zA-Z] but "\\u0301" found.
		// is more expressive anyhow.
		$expectedMessage = wfMessage( 'math_syntax_error' )->inContentLanguage()->escaped();
		$this->assertStringContainsString( $expectedMessage, $o->getError() );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\RestbaseChecker::isValid
	 */
	public function testIsValid() {
		$this->assertFalse( $this->BadObject->isValid() );
		$this->assertTrue( $this->GoodObject->isValid() );
	}

	/**
	 * @covers \MediaWiki\Extension\Math\InputCheck\RestbaseChecker::getValidTex
	 */
	public function testGetValidTex() {
		$this->assertNull( $this->GoodObject->getValidTex() );
		$this->assertNull( $this->BadObject->getValidTex() );
		$this->BadObject->isValid();
		$this->GoodObject->isValid();
		$this->assertNull( $this->BadObject->getValidTex() );

		// Note that texvcjs has slightly diverged from texvc and enforces brackets for function
		// arguments. Also the double space between frac and the arg has ben reduce to a single space.
		$this->assertEquals( $this->GoodObject->getValidTex(), '\\sin \\left({\\frac {1}{2}}x\\right)' );
	}

}
