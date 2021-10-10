'use strict';
const Page = require( 'wdio-mediawiki/Page' );

class RandomPage extends Page {
	open() {
		super.openTitle( 'Special:RandomPage', { useskinversion: 1 } );
	}
}
module.exports = new RandomPage();
