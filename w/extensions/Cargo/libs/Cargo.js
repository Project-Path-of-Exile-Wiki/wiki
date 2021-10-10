/**
 * Cargo.js
 *
 * JavaScript utility functionality for the Cargo extension.
 *
 * @author Yaron Koren
 */

( function ( $, mw ) {

var showText = '[' + mw.msg( 'show' ) + ']';
var hideText = '[' + mw.msg( 'hide' ) + ']';

$('span.cargoMinimizedText')
	.hide()
	.parent().prepend('<a class="cargoToggle">' + showText + '</a> ');

$('a.cargoToggle').click( function() {
	if ( $(this).text() == showText ) {
		$(this).siblings('.cargoMinimizedText').show(400).css('display', 'inline');
		$(this).text(hideText);
	} else {
		$(this).siblings('.cargoMinimizedText').hide(400);
		$(this).text(showText);
	}
});

}( jQuery, mediaWiki ) );