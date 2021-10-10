<?php

class CargoUtilsUnitTest extends MediaWikiUnitTestCase {
	/**
	 * @covers CargoUtils::formatError
	 */
	public function testFormatError() {
		$expected = '<div class="error">cargo error string here</div>';
		$actual = CargoUtils::formatError( 'cargo error string here' );

		$this->assertSame( $expected, $actual );
	}
}
