( function () {

	var WATCHED_CLASS = [ 'watched', 'mw-ui-icon-wikimedia-unStar-progressive' ],
		TEMP_WATCHED_CLASS = [ 'temp-watched', 'mw-ui-icon-wikimedia-halfStar-progressive' ],
		UNWATCHED_CLASS = 'mw-ui-icon-wikimedia-star-base20';

	/**
	 * Tweaks the global watchstar handler in core to use the correct classes for Minerva.
	 *
	 * @param {jQuery.Object} $icon
	 */
	function init( $icon ) {
		$icon.on( 'watchpage.mw', function ( _ev, action, expiry ) {
			toggleClasses( $( this ).find( 'a' ), action, expiry );
		} );
	}

	/**
	 *
	 * @param {jQuery.Object} $elem
	 * @param {string} action
	 * @param {string} expiry
	 */
	function toggleClasses( $elem, action, expiry ) {
		$elem.removeClass(
			[].concat( WATCHED_CLASS, TEMP_WATCHED_CLASS, UNWATCHED_CLASS )
		).addClass( function () {
			var classes = UNWATCHED_CLASS;
			if ( action === 'watch' ) {
				if ( expiry !== null && expiry !== undefined ) {
					classes = TEMP_WATCHED_CLASS;
				} else {
					classes = WATCHED_CLASS;
				}
			}
			return classes;
		} );
	}

	module.exports = {
		init: init,
		test: {
			toggleClasses: toggleClasses
		}
	};

}() );
