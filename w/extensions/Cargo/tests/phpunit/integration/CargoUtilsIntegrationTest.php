<?php

use MediaWiki\MediaWikiServices;

class CargoUtilsIntegrationTest extends MediaWikiIntegrationTestCase {
	public function setUp() : void {
		$this->setMwGlobals(
			[
				'wgDisableInternalSearch' => true,
				'wgDummyLanguageCodes' => true
			]
		);
	}

	/**
	 * @covers CargoUtils::smartSplit
	 * @covers CargoUtils::findQuotedStringEnd
	 * @dataProvider provideSmartSplitData
	 */
	public function testSmartSplit( $delimiter, $string, array $expected ) {
		$actual = CargoUtils::smartSplit( $delimiter, $string );
		$this->assertSame( $expected, $actual );
	}

	/** @return array */
	public function provideSmartSplitData() {
		return [
			[ '', '', [] ],
			[ ',', 'one,two', [ 'one', 'two' ] ],
			[ '|', 'one||0|', [ 'one', '0' ] ],
		];
	}

	/**
	 * @covers CargoUtils::getSpecialPage
	 */
	public function testGetValidSpecialPage() {
		$actual = CargoUtils::getSpecialPage( 'Block' );

		$this->assertInstanceOf( SpecialPage::class, $actual );
	}

	/**
	 * @covers CargoUtils::getSpecialPage
	 */
	public function testGetInvalidSpecialPage() {
		$actual = CargoUtils::getSpecialPage( 'NotValidPage' );

		$this->assertNull( $actual );
	}

	/**
	 * @covers CargoUtils::getContentLang
	 */
	public function testGetContentLang() {
		$actual = CargoUtils::getContentLang();

		$this->assertInstanceOf( Language::class, $actual );
	}

	/**
	 * @covers CargoUtils::makeLink
	 * @dataProvider provideMakeLinkData
	 */
	public function testMakeLink(
		$linkRenderer,
		$title,
		$msg,
		$attr,
		$params,
		$expected
	) {
		$actual = CargoUtils::makeLink( $linkRenderer, $title, $msg, $attr, $params );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public function provideMakeLinkData() {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$title = Title::newFromText( 'Test' );
		return [
			[ $linkRenderer, null, null, [], [], null ],
			[ $linkRenderer, null, '', [], [], null ],
			[ $linkRenderer, $title, null, [], [],
				'<a href="/index.php?title=Test&amp;action=edit&amp;redlink=1" class="new" title="Test (page does not exist)">Test</a>'
			],
			[ $linkRenderer, $title, 'Link text', [], [],
				'<a href="/index.php?title=Test&amp;action=edit&amp;redlink=1" class="new" title="Test (page does not exist)">Link text</a>'
			],
		];
	}
}
