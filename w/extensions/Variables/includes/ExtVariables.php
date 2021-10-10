<?php

/**
 * Extension class with basic extension information. This class serves as static
 * class with the static parser functions but also as variables store instance
 * as object assigned to a Parser object.
 */
class ExtVariables {

	/**
	 * Version of the 'Variables' extension.
	 * Using this constant is deprecated, please use the data in extension.json instead.
	 *
	 * @deprecated since 2.3.0
	 * @since 1.4
	 *
	 * @var string
	 */
	const VERSION = '2.5.1';

	/**
	 * Internal store for variable values
	 *
	 * @var string[]
	 */
	private $mVariables = [];

	/**
	 * Array with all names of variables requested by '#var_final'. Key of the values is the
	 * stripSateId of the strip-item placed where the final var should appear.
	 *
	 * @since 2.0
	 *
	 * @var string[]
	 */
	private $mFinalizedVars = [];

	/**
	 * Variables extensions own private StripState manager to manage 'finalizedvar' placeholders
	 * and their replacement with the final var value or a defined default.
	 *
	 * @since 2.0
	 *
	 * @var StripState
	 */
	private $mFinalizedVarsStripState;

	// Parser Functions

	/**
	 * Sets up #vardefine parser function to save variable values internally.
	 * The parameters of the parser function correspond with the parameters of this function.
	 *
	 * @param Parser $parser The parser instance these variables are bound to
	 * @param string $varName The name of the variable
	 * @param string $value The value of the defined variable
	 *
	 * @return string '' This parser function has no output
	 */
	public static function pf_vardefine( Parser $parser, $varName = '', $value = '' ) {
		self::get( $parser )->setVarValue( $varName, $value );
		return '';
	}

	/**
	 * Sets up #vardefineecho parser function to save variable values internally.
	 * The parameters of the parser function correspond with the parameters of this function.
	 *
	 * @param Parser $parser The parser instance these variables are bound to
	 * @param string $varName The name of the variable
	 * @param string $value The value of the defined variable
	 *
	 * @return string The value assigned to the variable
	 */
	public static function pf_vardefineecho( Parser $parser, $varName = '', $value = '' ) {
		self::get( $parser )->setVarValue( $varName, $value );
		return $value;
	}

	/**
	 * Sets up #varexists parser function to check if a variable was ever defined on a page.
	 * Even if an empty string is assigned to an variable, it still exists for this function.
	 * The parameters of the parser function correspond with the content of the $args param.
	 *
	 * @param Parser $parser The parser instance these variables are bound to
	 * @param PPFrame $frame The current frame
	 * @param array $args The arguments of the parser function
	 *
	 * @return string the content of the second or third parameter
	 * If none are provided, 1 or empty string by default
	 */
	public static function pfObj_varexists( Parser $parser, PPFrame $frame, array $args ) {
		// first argument expanded already but lets do this anyway
		$varName = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';

		// this prevents issues due to template caching,
		// templates using variables are reparsed every call.
		global $egVariablesAreVolatile;
		if ( $egVariablesAreVolatile ) {
			$frame->setVolatile();
		}

		// if you expand these arguments earlier, you parse wikitext you discard later on.
		// doing so would lead to unexpected effects and decrease performance.
		if ( self::get( $parser )->varExists( $varName ) ) {
			$exists = isset( $args[1] ) ? trim( $frame->expand( $args[1] ) ) : '1';
			return $exists;
		} else {
			$noexists = isset( $args[2] ) ? trim( $frame->expand( $args[2] ) ) : '';
			return $noexists;
		}
	}

	/**
	 * Sets up #var parser function to return the value of a variable.
	 *
	 * @param Parser $parser The parser instance these variables are bound to
	 * @param PPFrame $frame The current frame
	 * @param array $args The arguments of the parser function
	 *
	 * @return string the value assigned to the variable
	 */
	public static function pfObj_var( Parser $parser, PPFrame $frame, array $args ) {
		// first argument expanded already but lets do this anyway
		$varName = trim( $frame->expand( $args[0] ) );
		$varVal = self::get( $parser )->getVarValue( $varName, null );

		// this prevents issues due to template caching,
		// templates using variables are reparsed every call
		global $egVariablesAreVolatile;
		if ( $egVariablesAreVolatile ) {
			$frame->setVolatile();
		}

		// default applies if var doesn't exist but also in case it is an empty string!
		if ( $varVal === null || $varVal === '' ) {
			// only expand argument when needed:
			$defaultVal = isset( $args[1] ) ? trim( $frame->expand( $args[1] ) ) : '';
			return $defaultVal;
		}
		return $varVal;
	}

	/**
	 * Sets up #var_final parser function to call the final value of an variable
	 * after parsing the page is done.
	 * The parameters of the parser function correspond with the parameters of this function.
	 *
	 * @param Parser $parser The parser instance these variables are bound to
	 * @param string $varName The name of the variable
	 * @param string $defaultVal The output if no such variable is defined
	 *
	 * @return string
	 */
	public static function pf_var_final( Parser $parser, $varName, $defaultVal = '' ) {
		return self::get( $parser )->requestFinalizedVar( $parser, $varName, $defaultVal );
	}

	// Private Helper

	/**
	 * Takes care of setting a strip state pair
	 *
	 * @param string $marker
	 * @param string $value
	 */
	protected function stripStatePair( $marker, $value ) {
		$this->mFinalizedVarsStripState->addGeneral( $marker, $value );
	}

	// Public functions for interaction
	//
	// Public non-parser functions, accessible for
	// other extensions doing interactive stuff
	// with 'Variables' (like Extension:Loops)

	/**
	 * Convenience function to return the 'Variables' extensions variables store connected
	 * to a certain Parser object. Each parser has its own store which will be reset after
	 * a parsing process [Parser::parse()] has finished.
	 *
	 * @param Parser $parser
	 *
	 * @return self
	 */
	public static function get( Parser $parser ) {
		return $parser->mExtVariables;
	}

	/**
	 * Defines a variable, accessible by getVarValue() or '#var' parser function. Name and
	 * value will be trimmed and converted to string.
	 *
	 * @param string $varName
	 * @param string $value will be converted to string if no string is given
	 */
	public function setVarValue( $varName, $value = '' ) {
		$this->mVariables[ trim( $varName ) ] = trim( $value );
	}

	/**
	 * Returns a variables value or null if it doesn't exist.
	 *
	 * @param string $varName
	 * @param string|null $defaultVal
	 *
	 * @return string|null
	 */
	public function getVarValue( $varName, $defaultVal = null ) {
		$varName = trim( $varName );
		if ( $this->varExists( $varName ) ) {
			return $this->mVariables[ $varName ];
		} else {
			return $defaultVal;
		}
	}

	/**
	 * Checks whether a variable exists within the scope.
	 *
	 * @param string $varName
	 *
	 * @return bool
	 */
	public function varExists( $varName ) {
		$varName = trim( $varName );
		return array_key_exists( $varName, $this->mVariables );
	}

	/**
	 * Allows to unset a certain variable
	 *
	 * @param string $varName
	 */
	public function unsetVar( $varName ) {
		unset( $this->mVariables[ $varName ] );
	}

	/**
	 * Allows to register the usage of '#var_final'. Meaning a variable can be set as well
	 * as a default value. The return value, a strip-item then can be inserted into any
	 * wikitext processed by the same parser. Later that strip-item will be replaced with
	 * the final var text.
	 * Note: It's not possible to use the returned strip-item within other stripped text
	 *       since 'Variables' unstripping will happen before the general unstripping!
	 *
	 * @param Parser $parser
	 * @param string $varName
	 * @param string $defaultVal
	 *
	 * @return string strip-item
	 */
	public function requestFinalizedVar( Parser $parser, $varName, $defaultVal = '' ) {
		if ( $this->mFinalizedVarsStripState === null ) {
			$this->mFinalizedVarsStripState = new StripState;
		}
		$id = count( $this->mFinalizedVars );
		/*
		 * strip-item which will be unstripped in self::onInternalParseBeforeSanitize()
		 * In case the requested final variable has a value in the end, this strip-item
		 * value will be replaced with that value before unstripping.
		 */
		$rnd = Parser::MARKER_PREFIX . "-finalizedvar-{$id}-" . Parser::MARKER_SUFFIX;

		$this->stripStatePair( $rnd, trim( $defaultVal ) );
		$this->mFinalizedVars[ $rnd ] = trim( $varName );

		return $rnd;
	}

	/**
	 * Does the actual insertion of the finalized var values in place of the strip-markers.
	 *
	 * @param string $text The text to parse
	 * @return string The parsed text
	 */
	public function insertFinalizedVars( $text ) {
		// only do this if '#var_final' was used
		if ( $this->mFinalizedVarsStripState === null ) {
			return $text;
		}

		/*
		 * all vars are final now, check whether requested vars can be inserted for 'finalizedvar' or
		 * if the default has to be inserted. In any case, adjust the strip item value
		 */
		foreach ( $this->mFinalizedVars as $stripStateId => $varName ) {
			$varVal = $this->getVarValue( $varName, '' );
			if ( $varVal !== '' ) {
				// replace strip item value with final variables value or registered default:
				$this->stripStatePair( $stripStateId, $varVal );
			}
		}

		/**
		 * Unstrip all '#var_final' strip-markers with their final '#var' or default values.
		 * This HAS to be done here and can't be done through the normal unstrip process of MW.
		 * This is because the default value as well as the variables value still have to be
		 * rendered properly since they may contain links or even category links.
		 * On the other hand, they can't be parsed with Parser::recursiveTagParse() since this
		 * would parse wiki templates and functions which are intended as normal text, kind of
		 * similar to returning a parser functions value with 'noparse' => true.
		 * Also, there is no way to expand the '#var_final' default value here, just if needed,
		 * since the output could be an entirely different, e.g. if variables are used.
		 * This method also takes care of recursive '#var_final' calls
		 * (within the default value) quite well.
		 */
		$text = $this->mFinalizedVarsStripState->unstripGeneral( $text );
		return $text;
	}
}
