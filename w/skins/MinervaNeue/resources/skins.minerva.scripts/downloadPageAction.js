( function ( M, track, msg ) {
	var MAX_PRINT_TIMEOUT = 3000,
		mobile = M.require( 'mobile.startup' ),
		Icon = mobile.Icon,
		icons = mobile.icons,
		lazyImageLoader = mobile.lazyImages.lazyImageLoader,
		browser = mobile.Browser.getSingleton(),
		GLYPH = 'download';

	/**
	 * Helper function to retrieve the Android version
	 *
	 * @ignore
	 * @param {string} userAgent User Agent
	 * @return {number|false} An integer.
	 */
	function getAndroidVersion( userAgent ) {
		var match = userAgent.toLowerCase().match( /android\s(\d\.]*)/ );
		return match ? parseInt( match[ 1 ] ) : false;
	}

	/**
	 * Helper function to retrieve the Chrome/Chromium version
	 *
	 * @ignore
	 * @param {string} userAgent User Agent
	 * @return {number|false} An integer.
	 */
	function getChromeVersion( userAgent ) {
		var match = userAgent.toLowerCase().match( /chrom(e|ium)\/(\d+)\./ );
		return match ? parseInt( match[ 2 ] ) : false;
	}

	/**
	 * Checks whether DownloadIcon is available for given user agent
	 *
	 * @memberof DownloadIcon
	 * @instance
	 * @param {Window} windowObj
	 * @param {Page} page to download
	 * @param {string} userAgent User agent
	 * @param {number[]} supportedNamespaces where printing is possible
	 * @return {boolean}
	 */
	function isAvailable( windowObj, page, userAgent, supportedNamespaces ) {
		var androidVersion = getAndroidVersion( userAgent ),
			chromeVersion = getChromeVersion( userAgent );

		// Download button is restricted to certain namespaces T181152.
		// Not shown on missing pages
		// Defaults to 0, in case cached JS has been served.
		if ( supportedNamespaces.indexOf( page.getNamespaceId() ) === -1 ||
			page.isMainPage() || page.isMissing ) {
			// namespace is not supported or it's a main page
			return false;
		}

		if ( browser.isIos() || chromeVersion === false ||
			windowObj.chrome === undefined
		) {
			// we support only chrome/chromium on desktop/android
			return false;
		}
		if ( ( androidVersion && androidVersion < 5 ) || chromeVersion < 41 ) {
			return false;
		}
		return true;
	}
	/**
	 * onClick handler for button that invokes print function
	 *
	 * @param {Icon} icon
	 * @param {Icon} spinner
	 */
	function onClick( icon, spinner ) {
		function doPrint() {
			icon.timeout = clearTimeout( icon.timeout );
			track( 'minerva.downloadAsPDF', {
				action: 'callPrint'
			} );
			window.print();
			icon.$el.show();
			spinner.$el.hide();
		}

		function doPrintBeforeTimeout() {
			if ( icon.timeout ) {
				doPrint();
			}
		}
		// The click handler may be invoked multiple times so if a pending print is occurring
		// do nothing.
		if ( !icon.timeout ) {
			track( 'minerva.downloadAsPDF', {
				action: 'fetchImages'
			} );
			icon.$el.hide();
			spinner.$el.show();
			// If all image downloads are taking longer to load then the MAX_PRINT_TIMEOUT
			// abort the spinner and print regardless.
			icon.timeout = setTimeout( doPrint, MAX_PRINT_TIMEOUT );
			lazyImageLoader.loadImages( lazyImageLoader.queryPlaceholders( document.getElementById( 'content' ) ) )
				.then( doPrintBeforeTimeout, doPrintBeforeTimeout );
		}
	}

	/**
	 * Gets a click handler for the download icon
	 * Expects to be run in the context of an icon using `Function.bind`
	 *
	 * @param {Icon} spinner
	 * @return {Function}
	 */
	function getOnClickHandler( spinner ) {
		return function () {
			onClick( this, spinner );
		};
	}

	/**
	 * Generate a download icon for triggering print functionality if
	 * printing is available
	 *
	 * @param {Page} page
	 * @param {number[]} supportedNamespaces
	 * @param {Window} [windowObj] window object
	 * @param {boolean} [hasText] Use icon + button style.
	 * @return {jQuery.Object|null}
	 */
	function downloadPageAction( page, supportedNamespaces, windowObj, hasText ) {
		var
			modifier = hasText ? 'toggle-list-item__anchor toggle-list-item__label' : 'mw-ui-icon-element mw-ui-icon-with-label-desktop',
			icon,
			spinner = icons.spinner( {
				hasText: hasText,
				modifier: modifier
			} );

		if (
			isAvailable(
				windowObj, page, navigator.userAgent,
				supportedNamespaces
			)
		) {
			icon = new Icon( {
				glyphPrefix: 'minerva',
				title: msg( 'minerva-download' ),
				name: GLYPH,
				tagName: 'button',
				events: {
					// will be bound to `this`
					click: getOnClickHandler( spinner )
				},
				hasText: hasText,
				label: mw.msg( 'minerva-download' ),
				modifier: modifier
			} );

			return $( '<li>' ).addClass( hasText ? 'toggle-list-item' : 'page-actions-menu__list-item' ).append( icon.$el ).append( spinner.$el.hide() );
		} else {
			return null;
		}
	}

	module.exports = {
		downloadPageAction: downloadPageAction,
		test: {
			isAvailable: isAvailable,
			getOnClickHandler: getOnClickHandler
		}
	};

// eslint-disable-next-line no-restricted-properties
}( mw.mobileFrontend, mw.track, mw.msg ) );
