<?php

class RSSUtils {

	/**
	 * Output an error message, all wrapped up nicely in HTML.
	 * @param string $errorMessageName The system message that this error is
	 * @param string[]|string|null $params Error parameter (or parameters).
	 * @return string HTML that is the error.
	 */
	public static function getErrorHtml( $errorMessageName, $params = null ) {
		// Anything from a parser tag should use Content lang for message,
		// since the cache doesn't vary by user language: use ->inContentLanguage()
		// The ->parse() part makes everything safe from an escaping standpoint.

		return Html::rawElement( 'span', [ 'class' => 'error' ],
			"Extension:RSS -- Error: " . wfMessage( $errorMessageName )
				->inContentLanguage()->params( $params )->parse()
		);
	}

}
