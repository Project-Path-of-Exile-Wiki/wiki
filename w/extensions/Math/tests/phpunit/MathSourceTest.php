<?php

use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\MathSource;

/**
 * Test the TeX source output format.
 *
 * @covers \MediaWiki\Extension\Math\MathSource
 *
 * @license GPL-2.0-or-later
 */
class MathSourceTest extends MediaWikiTestCase {

	/**
	 * Checks the basic functionality
	 * i.e. if the span element is generated right.
	 */
	public function testBasics() {
		$real = MathRenderer::renderMath( "a+b", [], 'source' );
		$this->assertEquals(
			'<span class="mwe-math-fallback-source-inline tex" dir="ltr">$ a+b $</span>',
			$real,
			"Rendering of a+b in plain Text mode"
		);
	}

	/**
	 * Checks if newlines are converted to spaces correctly.
	 */
	public function testNewLines() {
		$real = MathRenderer::renderMath( "a\n b", [], 'source' );
		$this->assertSame(
			'<span class="mwe-math-fallback-source-inline tex" dir="ltr">$ a  b $</span>',
			$real,
			"converting newlines to spaces"
		);
	}

	public function testConstructor() {
		$renderer = new MathSource( 'a' );

		$this->assertEquals( 'source', $renderer->getMode() );
	}

	public function testRender() {
		$renderer = new MathSource( 'a+b' );

		$this->assertTrue( $renderer->render() );
		$this->assertFalse( $renderer->isChanged() );
	}
}
