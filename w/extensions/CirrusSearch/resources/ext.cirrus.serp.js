$( function () {
	var uri, router = require( 'mediawiki.router' );
	if ( !router.isSupported() ) {
		return;
	}

	try {
		uri = new mw.Uri( location.href );
		if ( uri.query.searchToken ) {
			delete uri.query.searchToken;
			router.navigateTo( document.title, {
				path: uri.toString(),
				useReplaceState: true
			} );
		}
	} catch ( e ) {
		// Don't install the click handler when the browser location can't be parsed anyway
		return;
	}

	// No need to install the click handler when there is no token
	if ( !mw.config.get( 'wgCirrusSearchRequestSetToken' ) ) {
		return;
	}

	// Limit to content area so this doesn't interfere with clicks to UI elements
	$( mw.util.$content ).on( 'click', 'a', function () {
		try {
			var clickUri = new mw.Uri( location.href );
			clickUri.query.searchToken = mw.config.get( 'wgCirrusSearchRequestSetToken' );
			router.navigateTo( document.title, {
				path: clickUri.toString(),
				useReplaceState: true
			} );
		} catch ( e ) {
		}
	} );
} );
