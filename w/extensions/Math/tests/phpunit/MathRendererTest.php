<?php

use MediaWiki\Extension\Math\MathRenderer;

/**
 * Test the database access and core functionality of MathRenderer.
 *
 * @covers \MediaWiki\Extension\Math\MathRenderer
 *
 * @group Math
 *
 * @license GPL-2.0-or-later
 */
class MathRendererTest extends MediaWikiTestCase {
	private const SOME_TEX = "a+b";
	private const TEXVCCHECK_INPUT = '\forall \epsilon \exist \delta';
	private const TEXVCCHECK_OUTPUT = '\forall \epsilon \exists \delta ';

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

	/**
	 * Checks the tex and hash functions
	 * @covers \MediaWiki\Extension\Math\MathRenderer::getTex
	 * @covers \MediaWiki\Extension\Math\MathRenderer::__construct
	 */
	public function testBasics() {
		$renderer = $this->getMockForAbstractClass( MathRenderer::class, [ self::SOME_TEX ] );
		/** @var MathRenderer $renderer */
		// check if the TeX input was corretly passed to the class
		$this->assertEquals( self::SOME_TEX, $renderer->getTex(), "test getTex" );
		$this->assertFalse( $renderer->isChanged(), "test if changed is initially false" );
	}

	/**
	 * Test behavior of writeCache() when nothing was changed
	 * @covers \MediaWiki\Extension\Math\MathRenderer::writeCache
	 */
	public function testWriteCacheSkip() {
		$renderer =
			$this->getMockBuilder( MathRenderer::class )->setMethods( [
					'writeToDatabase',
					'render',
					'getMathTableName',
					'getHtmlOutput'
				] )->getMock();
		$renderer->expects( $this->never() )->method( 'writeToDatabase' );
		/** @var MathRenderer $renderer */
		$renderer->writeCache();
	}

	/**
	 * Test behavior of writeCache() when values were changed.
	 * @covers \MediaWiki\Extension\Math\MathRenderer::writeCache
	 */
	public function testWriteCache() {
		$renderer =
			$this->getMockBuilder( MathRenderer::class )->setMethods( [
					'writeToDatabase',
					'render',
					'getMathTableName',
					'getHtmlOutput'
				] )->getMock();
		$renderer->expects( $this->never() )->method( 'writeToDatabase' );
		/** @var MathRenderer $renderer */
		$renderer->writeCache();
	}

	public function testSetPurge() {
		$renderer =
			$this->getMockBuilder( MathRenderer::class )->setMethods( [
					'render',
					'getMathTableName',
					'getHtmlOutput'
				] )->getMock();
		/** @var MathRenderer $renderer */
		$renderer->setPurge();
		$this->assertTrue( $renderer->isPurge(), "Test purge." );
	}

	public function testDisableCheckingAlways() {
		$this->setMwGlobals( "wgMathDisableTexFilter", 'never' );
		$renderer =
			$this->getMockBuilder( MathRenderer::class )->setMethods( [
					'render',
					'getMathTableName',
					'getHtmlOutput',
					'readFromDatabase',
					'setTex'
				] )->setConstructorArgs( [ self::TEXVCCHECK_INPUT ] )->getMock();
		$renderer->expects( $this->never() )->method( 'readFromDatabase' );
		$renderer->expects( $this->once() )->method( 'setTex' )->with( self::TEXVCCHECK_OUTPUT );

		/** @var MathRenderer $renderer */
		$this->assertTrue( $renderer->checkTeX() );
		// now setTex sould not be called again
		$this->assertTrue( $renderer->checkTeX() );
	}

	public function testDisableCheckingNever() {
		$this->setMwGlobals( "wgMathDisableTexFilter", 'always' );
		$renderer =
			$this->getMockBuilder( MathRenderer::class )->setMethods( [
					'render',
					'getMathTableName',
					'getHtmlOutput',
					'readFromDatabase',
					'setTex'
				] )->setConstructorArgs( [ self::TEXVCCHECK_INPUT ] )->getMock();
		$renderer->expects( $this->never() )->method( 'readFromDatabase' );
		$renderer->expects( $this->never() )->method( 'setTex' );

		/** @var MathRenderer $renderer */
		$this->assertTrue( $renderer->checkTeX() );
	}

	public function testCheckingNewUnknown() {
		$this->setMwGlobals( "wgMathDisableTexFilter", 'new' );
		$renderer =
			$this->getMockBuilder( MathRenderer::class )->setMethods( [
					'render',
					'getMathTableName',
					'getHtmlOutput',
					'readFromDatabase',
					'setTex'
				] )->setConstructorArgs( [ self::TEXVCCHECK_INPUT ] )->getMock();
		$renderer->expects( $this->once() )->method( 'readFromDatabase' )
			->will( $this->returnValue( false ) );
		$renderer->expects( $this->once() )->method( 'setTex' )->with( self::TEXVCCHECK_OUTPUT );

		/** @var MathRenderer $renderer */
		$this->assertTrue( $renderer->checkTeX() );
		// now setTex sould not be called again
		$this->assertTrue( $renderer->checkTeX() );
	}

	public function testCheckingNewKnown() {
		$this->setMwGlobals( "wgMathDisableTexFilter", 'new' );
		$renderer =
			$this->getMockBuilder( MathRenderer::class )->setMethods( [
					'render',
					'getMathTableName',
					'getHtmlOutput',
					'readFromDatabase',
					'setTex'
				] )->setConstructorArgs( [ self::TEXVCCHECK_INPUT ] )->getMock();
		$renderer->expects( $this->exactly( 1 ) )->method( 'readFromDatabase' )
			->will( $this->returnValue( true ) );
		$renderer->expects( $this->never() )->method( 'setTex' );

		/** @var MathRenderer $renderer */
		$this->assertTrue( $renderer->checkTeX() );
		// we don't mark a object as checked even though we rely on the database cache
		// so readFromDatabase will be called again
		$this->assertTrue( $renderer->checkTeX() );
	}
}
