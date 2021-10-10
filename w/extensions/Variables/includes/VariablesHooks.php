<?php

/**
 * Extension class containing the hook functions called by core to incorporate the
 * functionality of the Variables extension.
 */
class VariablesHooks {

	/**
	 * Sets up parser functions
	 *
	 * @since 1.4
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		/*
		 * store for variables per parser object. This will solve several bugs related to
		 * 'ParserClearState' hook clearing all variables early in combination with certain
		 * other extensions. (since v2.0)
		 */
		$parser->mExtVariables = new ExtVariables();

		self::initFunction( $parser, 'var', Parser::SFH_OBJECT_ARGS );
		self::initFunction( $parser, 'varexists', Parser::SFH_OBJECT_ARGS );
		self::initFunction( $parser, 'var_final' );
		self::initFunction( $parser, 'vardefine' );
		self::initFunction( $parser, 'vardefineecho' );
	}

	/**
	 * Does the actual setup after making sure the functions aren't disabled
	 *
	 * @param Parser $parser
	 * @param string $name The name of the parser function
	 * @param int $flags Some configuration options, see also definition in Parser.php
	 */
	private static function initFunction( Parser $parser, $name, $flags = 0 ) {
		// register function only if not disabled by configuration:
		global $egVariablesDisabledFunctions;

		if ( !in_array( $name, $egVariablesDisabledFunctions ) ) {
			$parser->setFunctionHook(
				$name,
				[ ExtVariables::class, $flags & Parser::SFH_OBJECT_ARGS ? "pfObj_$name" : "pf_$name" ],
				$flags
			);
		}
	}

	/**
	 * This will clean up the variables store after parsing has finished. It will prevent
	 * strange things to happen for example during import of several pages or job queue is running
	 * for multiple pages. In these cases variables would become some kind of superglobals,
	 * being passed from one page to the other.
	 *
	 * @param Parser $parser
	 */
	public static function onParserClearState( Parser $parser ) {
		/**
		 * MessageCaches Parser clone will mess things up if we don't reset the entire object.
		 * Only resetting the array would unset it in the original object as well! This instead
		 * will break the entire reference to the object
		 */
		$parser->mExtVariables = new ExtVariables();
	}

	/**
	 * Used for '#var_final' parser function to insert the final variable values.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/InternalParseBeforeSanitize
	 *
	 * @since 2.0.1
	 *
	 * @param Parser $parser
	 * @param string &$text The text to parse
	 */
	public static function onInternalParseBeforeSanitize( Parser $parser, &$text ) {
		$text = ExtVariables::get( $parser )->insertFinalizedVars( $text );
	}
}
