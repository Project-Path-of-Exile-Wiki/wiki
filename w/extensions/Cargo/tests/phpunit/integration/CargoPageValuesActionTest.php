<?php

class CargoPageValuesActionTest extends MediaWikiIntegrationTestCase {

	/**
	 * Mock Skin for use in ::addLink test.
	 * @param Title $title
	 * @return Skin
	 */
	private function getSkin( Title $title ) {
		$skin = $this->getMockBuilder( Skin::class )
			->onlyMethods( [ 'getTitle' ] )
			->getMockForAbstractClass();
		$skin->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( $title );

		return $skin;
	}

	/**
	 * @covers CargoPageValuesAction::addLink
	 * @dataProvider provideTitle
	 */
	public function testAddLink( $title ) {
		$sidebar = [];
		$result = CargoPageValuesAction::addLink(
			$this->getSkin( $title ),
			$sidebar
		);

		if ( $title->getNamespace() == NS_SPECIAL ) {
			$this->assertNull( $result );
			$this->assertCount( 0, $sidebar );
		} else {
			$this->assertNull( $result );
			$this->assertArrayHasKey( 'cargo-pagevalues', $sidebar['TOOLBOX'] );
		}
	}

	/** @return array */
	public function provideTitle() {
		return [
			// Test any page that is not a special page
			[ Title::newFromText( 'Test', NS_MAIN ) ],
			// Test special pages too, as this is needed
			[ Title::newFromText( 'Test', NS_SPECIAL ) ],
		];
	}

}
