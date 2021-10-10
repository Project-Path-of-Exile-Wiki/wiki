<?php

namespace Tests\MediaWiki\Minerva\Menu\Entries;

use MediaWiki\Minerva\Menu\Entries\HomeMenuEntry;

/**
 * @group MinervaNeue
 * @coversDefaultClass \MediaWiki\Minerva\Menu\Entries\HomeMenuEntry
 */
class HomeMenuEntryTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::getName
	 * @covers ::getCSSClasses
	 * @covers ::getComponents
	 */
	public function testConstruct() {
		$name = 'foo';
		$text = 'bar';
		$url = 'http://baz';
		$entry = new HomeMenuEntry( $name, $text, $url );
		$this->assertSame( $name, $entry->getName() );
		$this->assertSame( [], $entry->getCSSClasses() );
		$this->assertSame( [ [
			'text' => $text,
			'href' => $url,
			'class' => 'mw-ui-icon mw-ui-icon-before mw-ui-icon-minerva-foo',
			'data-event-name' => 'menu.foo'
		] ], $entry->getComponents() );
	}

	/**
	 * @covers ::overrideCssClass
	 * @covers ::overrideText
	 * @covers ::getComponents
	 */
	public function testOverride() {
		$entry = new HomeMenuEntry( 'foo', 'bar', 'http://baz' );
		$component = current( $entry->getComponents() );
		$this->assertSame( 'bar', $component['text'] );
		$this->assertSame(
			'mw-ui-icon mw-ui-icon-before mw-ui-icon-minerva-foo',
			$component['class']
		);
		$entry->overrideText( 'blah' )
			->overrideCssClass( 'classy' );
		$component = current( $entry->getComponents() );
			$this->assertSame( 'blah', $component['text'] );
		$this->assertSame(
			'classy',
			$component['class']
		);
	}

}
