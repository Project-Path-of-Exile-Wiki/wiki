<?php
/**
 * Class holding functions for displaying widgets.
 */

class WidgetRenderer {
	// The prefix and suffix for the widget strip marker.
	private static $markerPrefix = "START_WIDGET";
	private static $markerSuffix = "END_WIDGET";

	// Stores the compiled widgets for after the parser has run.
	// Must be public for use in anonymous callback function in PHP 5.3
	public static $widgets = [];

	public static function initRandomString() {
		// Add a random string to the prefix to ensure no conflicts
		// with normal content.
		self::$markerPrefix .= wfRandomString( 16 );
	}

	public static function renderWidget( &$parser, $widgetName ) {
		global $wgWidgetsCompileDir;

		$smarty = new Smarty;
		$smarty->left_delimiter = '<!--{';
		$smarty->right_delimiter = '}-->';
		$smarty->compile_dir = $wgWidgetsCompileDir;

		// registering custom Smarty plugins
		$smarty->addPluginsDir( __DIR__ . "/smarty_plugins/" );

		$smarty->enableSecurity( 'WidgetSecurity' );

		// Register the Widgets extension functions.
		$smarty->registerResource(
			'wiki',
			[
				[ 'WidgetRenderer', 'wiki_get_template' ],
				[ 'WidgetRenderer', 'wiki_get_timestamp' ],
				[ 'WidgetRenderer', 'wiki_get_secure' ],
				[ 'WidgetRenderer', 'wiki_get_trusted' ]
			]
		);

		$params = func_get_args();
		// The first and second params are the parser and the widget
		// name - we already have both.
		array_shift( $params );
		array_shift( $params );

		$params_tree = [];

		foreach ( $params as $param ) {
			$pair = explode( '=', $param, 2 );

			if ( count( $pair ) == 2 ) {
				$key = trim( $pair[0] );
				$val = trim( $pair[1] );
			} else {
				$key = $param;
				$val = true;
			}

			if ( $val == 'false' ) {
				$val = false;
			}

			/* If the name of the parameter has object notation

				a.b.c.d

			   then we assign stuff to hash of hashes, not scalar

			*/
			$keys = explode( '.', $key );

			// $subtree will be moved from top to the bottom and
			// at the end will point to the last level.
			$subtree =& $params_tree;

			// Go through all the keys but the last one.
			$last_key = array_pop( $keys );

			foreach ( $keys as $subkey ) {
				// If next level of subtree doesn't exist yet,
				// create an empty one.
				if ( !array_key_exists( $subkey, $subtree ) ) {
					$subtree[$subkey] = [];
				}

				// move to the lower level
				$subtree =& $subtree[$subkey];
			}

			// last portion of the key points to itself
			if ( isset( $subtree[$last_key] ) ) {
				// If this is already an array, push into it;
				// otherwise, convert into an array first.
				if ( !is_array( $subtree[$last_key] ) ) {
					$subtree[$last_key] = [ $subtree[$last_key] ];
				}
				$subtree[$last_key][] = $val;
			} else {
				// doesn't exist yet, just setting a value
				$subtree[$last_key] = $val;
			}
		}

		$smarty->assign( $params_tree );

		try {
			$output = $smarty->fetch( "wiki:$widgetName" );
		} catch ( Exception $e ) {
			wfDebugLog( "Widgets", "Smarty exception while parsing '$widgetName': " . $e->getMessage() );
			return '<div class="error">' . wfMessage( 'widgets-error', htmlentities( $widgetName ) )->text() . ': ' . $e->getMessage() . '</div>';
		}

		// To prevent the widget output from being tampered with, the
		// compiled HTML is stored and a strip marker with an index to
		// retrieve it later is returned.
		$index = array_push( self::$widgets, $output ) - 1;
		return self::$markerPrefix . '-' . $index . self::$markerSuffix;
	}

	public static function outputCompiledWidget( &$out, &$text ) {
		$text = preg_replace_callback(
			'/' . self::$markerPrefix . '-(\d+)' . self::$markerSuffix . '/S',
			function ( $matches ) {
				// Can't use self:: in an anonymous function pre PHP 5.4
				return WidgetRenderer::$widgets[$matches[1]];
			},
			$text
		);

		return true;
	}

	// The following four functions are all registered with Smarty.

	public static function wiki_get_template( $widgetName, &$widgetCode, $smarty_obj ) {
		global $wgWidgetsUseFlaggedRevs;

		$widgetTitle = Title::makeTitleSafe( NS_WIDGET, $widgetName );

		if ( $widgetTitle && $widgetTitle->exists() ) {
			if ( $wgWidgetsUseFlaggedRevs ) {
				$flaggedWidgetArticle = FlaggedArticle::getTitleInstance( $widgetTitle );
				$flaggedWidgetArticleRevision = $flaggedWidgetArticle->getStableRev();

				if ( $flaggedWidgetArticleRevision ) {
					$widgetCode = $flaggedWidgetArticleRevision->getRevText();
				} else {
					$widgetCode = '';
				}
			} else {
				$widgetWikiPage = new WikiPage( $widgetTitle );
				$widgetContent = $widgetWikiPage->getContent();
				$widgetCode = ContentHandler::getContentText( $widgetContent );
			}

			// Remove <noinclude> sections and <includeonly> tags from form definition
			$widgetCode = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $widgetCode );
			$widgetCode = strtr( $widgetCode, [ '<includeonly>' => '', '</includeonly>' => '' ] );

			return true;
		} else {
			return false;
		}
	}

	public static function wiki_get_timestamp( $widgetName, &$widgetTimestamp, $smarty_obj ) {
		$widgetTitle = Title::makeTitleSafe( NS_WIDGET, $widgetName );

		if ( $widgetTitle && $widgetTitle->exists() ) {
			$widgetArticle = new Article( $widgetTitle, 0 );
			$widgetTimestamp = $widgetArticle->getPage()->getTouched();
			return true;
		} else {
			return false;
		}
	}

	public static function wiki_get_secure( $tpl_name, &$smarty_obj ) {
		// assume all templates are secure
		return true;
	}

	public static function wiki_get_trusted( $tpl_name, &$smarty_obj ) {
		// not used for templates
	}

}
