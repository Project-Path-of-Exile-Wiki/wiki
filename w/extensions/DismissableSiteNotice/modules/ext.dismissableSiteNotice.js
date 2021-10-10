( function () {

	var cookieName = 'dismissSiteNotice',
		siteNoticeId = mw.config.get( 'wgSiteNoticeId' );

	// If no siteNoticeId is set, exit.
	if ( !siteNoticeId ) {
		return;
	}

	// If the user has the notice dismissal cookie set, exit.
	if ( $.cookie( cookieName ) === siteNoticeId ) {
		return;
	}

	// Otherwise, show the notice ...
	mw.util.addCSS( '.client-js .mw-dismissable-notice { display: block; }' );

	// ... and enable the dismiss button.
	$( function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.mw-dismissable-notice-close' )
			.css( 'visibility', 'visible' )
			.find( 'a' )
			.on( 'click keypress', function ( e ) {
				if (
					e.type === 'click' ||
					e.type === 'keypress' && e.which === 13
				) {
					$( this ).closest( '.mw-dismissable-notice' ).hide();
					$.cookie( cookieName, siteNoticeId, {
						expires: 30,
						path: '/'
					} );
				}
			} );
	} );

}() );
