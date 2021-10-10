<?php

namespace CirrusSearch;

use MediaWiki\MediaWikiServices;

/**
 * @covers \CirrusSearch\SearchConfig
 */
class SearchConfigIntegrationTest extends CirrusIntegrationTestCase {
	public function testMWServiceIntegration() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
		$this->assertInstanceOf( SearchConfig::class, $config );
	}

}
