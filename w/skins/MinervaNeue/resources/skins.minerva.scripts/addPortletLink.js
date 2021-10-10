/**
 * @param {jQuery.Object} $item The added list item, or null if no element was added.
 * @return {Object} of arrays with mandatory class names for list item elements.
 */
function getClassesForItem( $item ) {
	// eslint-disable-next-line no-jquery/no-class-state
	var isToggleList = $item.parent().hasClass( 'toggle-list__list' );
	if ( isToggleList ) {
		return {
			li: [ 'toggle-list-item' ],
			span: [ 'toggle-list-item__label' ],
			a: [ 'toggle-list-item__anchor' ]
		};
	} else {
		return {
			li: [],
			span: [],
			a: []
		};
	}
}

/**
 * @param {HTMLElement|null} link The added list item, or null if no element was added.
 * @param {Object} data
 */
module.exports = function ( link, data ) {
	var label, $item, $a, classes,
		id = data.id || 'unknowngadget';

	if ( link ) {
		$item = $( link );
		classes = getClassesForItem( $item );
		$item.addClass( classes.li );
		$a = $item.find( 'a' );
		$a.addClass( [
			'menu-list-item__button',
			'menu__item--' + id,
			'mw-ui-icon',
			'mw-ui-icon-before',
			'mw-ui-icon-portletlink-' + id
		].concat( classes.a ) );
		label = document.createElement( 'span' );
		label.setAttribute( 'class', classes.span.join( ' ' ) );
		label.textContent = $item.text();
		$a.empty().append( label );
	}
};
