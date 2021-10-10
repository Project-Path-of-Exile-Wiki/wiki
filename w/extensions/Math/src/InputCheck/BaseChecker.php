<?php

namespace MediaWiki\Extension\Math\InputCheck;

use MediaWiki\Extension\Math\Hooks;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\MathSource;
use stdClass;

/**
 * MediaWiki math extension
 *
 * @copyright 2002-2014 Tomasz Wegrzanowski, Brion Vibber, Moritz Schubotz,
 * and other MediaWiki contributors
 * @license GPL-2.0-or-later
 * @author Moritz Schubotz
 */
abstract class BaseChecker {
	/** @var string */
	protected $inputTeX;
	/** @var string|null */
	protected $validTeX;
	/** @var bool */
	protected $isValid = false;
	/** @var string|null */
	protected $lastError = null;

	/**
	 * @param string $tex the TeX InputString to be checked
	 */
	public function __construct( $tex = '' ) {
		$this->inputTeX = $tex;
		$this->isValid = false;
	}

	/**
	 * Returns true if the TeX input String is valid
	 * @return bool
	 */
	public function isValid() {
		return $this->isValid;
	}

	/**
	 * Returns the string of the last error.
	 * @return string
	 */
	public function getError() {
		return $this->lastError;
	}

	/**
	 * Some TeX checking programs may return
	 * a modified tex string after having checked it.
	 * You can get the altered tex string with this method
	 * @return string A valid Tex string
	 */
	public function getValidTex() {
		return $this->validTeX;
	}

	/**
	 * @see https://phabricator.wikimedia.org/T119300
	 * @param stdClass $e
	 * @param MathRenderer|null $errorRenderer
	 * @param string $host
	 * @return string|null
	 */
	protected function errorObjectToHtml( stdClass $e, $errorRenderer = null, $host = 'invalid' ) {
		if ( $errorRenderer === null ) {
			$errorRenderer = new MathSource( $this->inputTeX );
		}
		if ( isset( $e->error->message ) ) {
			if ( $e->error->message === 'Illegal TeX function' ) {
				return $errorRenderer->getError( 'math_unknown_function', $e->error->found );
			} elseif ( preg_match( '/Math extension/', $e->error->message ) ) {
				$names = Hooks::getMathNames();
				$mode = $names['mathml'];
				$msg = $e->error->message;

				return $errorRenderer->getError( 'math_invalidresponse', $mode, $host, $msg );
			}

			return $errorRenderer->getError( 'math_syntax_error' );
		}

		return $errorRenderer->getError( 'math_unknown_error' );
	}
}
