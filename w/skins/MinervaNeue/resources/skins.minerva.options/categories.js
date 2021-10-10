module.exports = function () {
	var
		// eslint-disable-next-line no-restricted-properties
		M = mw.mobileFrontend,
		mobile = M.require( 'mobile.startup' ),
		headers = mobile.headers,
		icons = mobile.icons,
		Overlay = mobile.Overlay,
		features = mw.config.get( 'wgMinervaFeatures', {} ),
		OverlayManager = mobile.OverlayManager,
		overlayManager = OverlayManager.getSingleton(),
		eventBus = mobile.eventBusSingleton,
		isAnon = mw.user.isAnon();

	// check the categories feature has been turned on
	if ( !features.categories ) {
		return;
	}

	// categories overlay
	overlayManager.add( /^\/categories$/, function () {

		return mobile.categoryOverlay( {
			api: new mw.Api(),
			isAnon: isAnon,
			title: mobile.currentPage().title,
			eventBus: eventBus
		} );
	} );

	overlayManager.add( /^\/categories\/add$/, function () {
		// A transitional overlay that loads instantly that will be replaced with a
		// CategoryAddOverlay as soon as it is available.
		var spinnerOverlay = Overlay.make(
			{
				headers: [
					headers.header( '', [
						icons.spinner()
					], icons.back() )
				],
				heading: ''
			}, icons.spinner()
		);

		// Load the additional code and replace the temporary overlay with the new overlay.
		mw.loader.using( 'mobile.categories.overlays' ).then( function () {
			var CategoryAddOverlay = M.require( 'mobile.categories.overlays' ).CategoryAddOverlay;
			overlayManager.replaceCurrent(
				new CategoryAddOverlay( {
					api: new mw.Api(),
					isAnon: isAnon,
					title: mobile.currentPage().title
				} )
			);
		} );
		return spinnerOverlay;
	} );

	/**
	 * Enable the categories button
	 *
	 * @ignore
	 */
	function initButton() {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.category-button' ).removeClass( 'hidden' );
	}

	$( initButton );

};
