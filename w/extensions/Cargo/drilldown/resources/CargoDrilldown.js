/**
 * CargoDrilldown.js
 *
 * Javascript code for use in the Cargo extension's Special:Drilldown page.
 *
 * Based heavily on the Semantic Drilldown extension's SemanticDrilldown.js
 * file.
 *
 * @author Sanyam Goyal
 * @author Yaron Koren
 */
( function () {
	jQuery.ui.autocomplete.prototype._renderItem = function ( ul, item ) {
		var re = new RegExp( '(?![^&;]+;)(?!<[^<>]*)(' + this.term.replace( /([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi, '\\$1' ) + ')(?![^<>]*>)(?![^&;]+;)', 'gi' ),
			loc = item.label.search( re );
		if ( loc >= 0 ) {
			var t = item.label.substr( 0, loc ) + '<strong>' + item.label.substr( loc, this.term.length ) + '</strong>' + item.label.substr( loc + this.term.length );
		} else {
			var t = item.label;
		}
		return jQuery( '<li></li>' )
			.data( 'item.autocomplete', item )
			.append( ' <a>' + t + '</a>' )
			.appendTo( ul );
	};

	jQuery.widget( 'ui.CDComboBox', {
		_create: function () {
			var self = this,
				select = this.element.hide(),
				inp_id = select[ 0 ].options[ 0 ].value,
				curval = select[ 0 ].name,
				input = jQuery( '<input id = "' + inp_id + '" type="text" name="' + inp_id + '" value="' + curval + '">' )
					.insertAfter( select )
					.autocomplete( {
						source: function ( request, response ) {
							var matcher = new RegExp( '\\b' + request.term, 'i' );
							response( select.children( 'option' ).map( function () {
								var text = jQuery( this ).text();
								if ( this.value && ( !request.term || matcher.test( text ) ) ) {
									return {
										id: this.value,
										label: text,
										value: text
									};
								}
							} ) );
						},
						delay: 0,
						change: function ( event, ui ) {
							if ( !ui.item ) {
							// if it didn't match anything,
							// just leave it as it is
								return false;
							}
							select.val( ui.item.id );
							self._trigger( 'selected', event, {
								item: select.find( "[value='" + ui.item.id + "']" )
							} );

						},
						minLength: 0
					} )
					.addClass( 'ui-widget ui-widget-content ui-corner-left' );
			jQuery( '<button type="button">&nbsp;</button>' )
				.attr( 'tabIndex', -1 )
				.attr( 'title', 'Show All Items' )
				.insertAfter( input )
				.button( {
					icons: {
						primary: 'ui-icon-triangle-1-s'
					},
					text: false
				} ).removeClass( 'ui-corner-all' )
				.addClass( 'ui-corner-right ui-button-icon' )
			// Need to do some hardcoded CSS here, to override
			// pesky jQuery UI settings!
			// Unfortunately, calling .css() won't work, because
			// it ignores "!important".
				.attr( 'style', 'width: 2.4em; margin: 0 !important; border-radius: 0' )
				.click( function () {
				// close if already visible
					if ( input.autocomplete( 'widget' ).is( ':visible' ) ) {
						input.autocomplete( 'close' );
						return;
					}
					// pass empty string as value to search for, displaying all results
					input.autocomplete( 'search', '' );
					input.focus();
				} );
		}
	} );

	jQuery.widget( 'ui.CDRemoteAutocomplete', {
		_create: function () {
			var input = this.element;
			input.autocomplete( {
				source: function ( request, response ) {
					var urlAPI = mw.config.get( 'wgScriptPath' ) + '/api.php?action=cargoautocomplete' +
						'&table=' + input.attr( 'data-cargo-table' ) +
						'&field=' + input.attr( 'data-cargo-field' ),
						fieldIsArray = input.attr( 'data-cargo-field-is-list' );
					if ( fieldIsArray != undefined ) {
						urlAPI += '&field_is_array=' + fieldIsArray;
					}
					urlAPI += '&where=' + input.attr( 'data-cargo-where' ) +
						'&format=json';
					$.ajax( {
						url: urlAPI,
						dataType: 'json',
						// jsonp: false,
						data: {
							substr: request.term
						},
						success: function ( data ) {
							response( data.cargoautocomplete );
						},
						error: function ( jqxhr, status, error ) {
							// Add debugging stuff here.
						}
					} );
				}
			} )
				.addClass( 'ui-widget ui-widget-content' );
		}
	} );

}( jQuery ) );

jQuery.fn.toggleCDValuesDisplay = function () {
	$valuesDiv = jQuery( this ).closest( '.drilldown-filter' )
		.find( '.drilldown-filter-values' );
	if ( $valuesDiv.css( 'display' ) === 'none' ) {
		$valuesDiv.css( 'display', 'block' );
		var downArrowImage = mw.config.get( 'cgDownArrowImage' );
		this.find( 'img' ).attr( 'src', downArrowImage );
	} else {
		$valuesDiv.css( 'display', 'none' );
		var rightArrowImage = mw.config.get( 'cgRightArrowImage' );
		this.find( 'img' ).attr( 'src', rightArrowImage );
	}
};

jQuery.fn.showDifferentColors = function () {
	$( this ).each( function () {
		if ( $( this ).attr( 'id' ) % 2 === 0 ) {
			$( this ).attr( 'style', 'background-color:#faf1f1;' );
		} else if ( $( this ).attr( 'id' ) % 2 === 1 ) {
			$( this ).attr( 'style', 'background-color:#f0f9f1;' );
		}
	} );
};

jQuery( document ).ready( function () {
	var viewport = '<meta name="viewport" content="width=device-width,initial-scale=1">';
	$( 'head' ).append( viewport );

	jQuery( '.cargoDrilldownComboBox' ).CDComboBox();
	jQuery( '.cargoDrilldownRemoteAutocomplete' ).CDRemoteAutocomplete();
	jQuery( '.drilldown-values-toggle' ).on( 'click', function () { jQuery( this ).toggleCDValuesDisplay(); } );
	jQuery( '.drilldown-parent-tables-value, .drilldown-parent-filters-wrapper' ).showDifferentColors();

	var maxWidth = window.matchMedia( '(max-width: 549px)' );

	function mobileView( maxWidth ) {
		if ( maxWidth.matches ) {
			var menu_icon = "<a class='menu_header' id='menu'><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\">\n" +
				'            <path d="M2 6h20v3H2zm0 5h20v3H2zm0 5h20v3H2z"/>\n' +
				'          </svg>' +
				'       </a>',
				div = "<div id='header'> </div>";
			$( '#bodyContent' ).before( div );
			$( '#header' ).append( menu_icon );
			$( '#header' ).append( $( '#firstHeading' ) );

			var menu = $( '#menu' ),
				main = $( '#content, #mw-navigation' ),
				drawer = $( '#drilldown-tables-tabs-wrapper' ),
				formatTabsWrapper = $( '#drilldown-format-tabs-wrapper' );
			if ( drawer.length != 0 ) {
				$( 'body' ).prepend( drawer );
			} else {
				$( 'body' ).prepend( "<div id='drilldown-tables-tabs-wrapper'></div>" );
				drawer = $( '#drilldown-tables-tabs-wrapper' );
			}
			if ( formatTabsWrapper ) {
				var formatLabel = '<p id="formatTabsHeader">Format:</p>';
				drawer.append( formatTabsWrapper );
				formatTabsWrapper.prepend( formatLabel );
			}

			menu.click( function ( e ) {
				drawer.toggleClass( 'open' );
				e.stopPropagation();
			} );
			main.click( function () {
				drawer.removeClass( 'open' );
			} );

			var mapCanvas = $( '.mapCanvas' );
			if ( mapCanvas.length ) {
				var mapWidth = mapCanvas.width(),
					zoom = 1 - ( mapWidth / 700 );
				mapCanvas.css( 'zoom', zoom );
			}

			var tableTabsHeader = $( '#tableTabsHeader' ),
				formatTabsHeader = $( '#formatTabsHeader' ),

				rightArrowImage = mw.config.get( 'cgRightArrowImage' ),
				downArrowImage = mw.config.get( 'cgDownArrowImage' ),
				arrow = "<img src=''>\t";
			tableTabsHeader.prepend( arrow );
			formatTabsHeader.prepend( arrow );

			var tableTabs = $( '#drilldown-tables-tabs' );
			if ( formatTabsWrapper.length != 0 ) {
				formatTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
				tableTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
				tableTabs.toggleClass( 'hide' );
			} else {
				tableTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
				formatTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
			}

			tableTabsHeader.click( function ( e ) {
				if ( tableTabs.hasClass( 'hide' ) ) {
					tableTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
					tableTabs.removeClass( 'hide' );
				} else {
					tableTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
					tableTabs.toggleClass( 'hide' );
				}
				e.stopPropagation();
			} );

			var formatTabs = $( '#drilldown-format-tabs' );
			formatTabsHeader.click( function ( e ) {
				if ( formatTabs.hasClass( 'hide' ) ) {
					formatTabsHeader.find( 'img' ).attr( 'src', downArrowImage );
					formatTabs.removeClass( 'hide' );
				} else {
					formatTabsHeader.find( 'img' ).attr( 'src', rightArrowImage );
					formatTabs.toggleClass( 'hide' );
				}
				e.stopPropagation();
			} );

		}
	}

	mobileView( maxWidth ); // Call listener function at run time
	maxWidth.addListener( mobileView );

} );
