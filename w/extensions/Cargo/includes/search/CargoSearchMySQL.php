<?php

use MediaWiki\MediaWikiServices;

/**
 * We need to create subclasses, instead of just calling the functionality,
 * because both filter() and, more importantly, $searchTerms are currently
 * "protected".
 *
 * Unfortunately, in MW 1.31, the methods parseQuery(), regexTerm() and
 * getIndexField() were made private, which means that they need to be
 * copied over here (but declared as public).
 */
class CargoSearchMySQL extends SearchMySQL {

	public function __construct() {
		if ( property_exists( 'SearchMySQL', 'lb' ) ) {
			// MW 1.34+
			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			parent::__construct( $lb );
		} else {
			parent::__construct();
		}
	}

	public function getSearchTerms( $searchString ) {
		$filteredTerm = $this->filter( $searchString );
		$this->parseQuery( $filteredTerm, false );
		return $this->searchTerms;
	}

	/**
	 * Parse the user's query and transform it into two SQL fragments:
	 * a WHERE condition and an ORDER BY expression
	 *
	 * @param string $filteredText
	 * @param string $fulltext
	 *
	 * @return array
	 */
	public function parseQuery( $filteredText, $fulltext ) {
		$lc = $this->legalSearchChars( self::CHARS_NO_SYNTAX ); // Minus syntax chars (" and *)
		$searchon = '';
		$this->searchTerms = [];

		# @todo FIXME: This doesn't handle parenthetical expressions.
		$m = [];
		if ( preg_match_all( '/([-+<>~]?)(([' . $lc . ']+)(\*?)|"[^"]*")/',
				$filteredText, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $bits ) {
				if ( function_exists( 'Wikimedia\suppressWarnings' ) ) {
					// MW >= 1.31
					Wikimedia\suppressWarnings();
				}
				list( /* all */, $modifier, $term, $nonQuoted, $wildcard ) = $bits;
				if ( function_exists( 'Wikimedia\restoreWarnings' ) ) {
					Wikimedia\restoreWarnings();
				}

				if ( $nonQuoted != '' ) {
					$term = $nonQuoted;
					$quote = '';
				} else {
					$term = str_replace( '"', '', $term );
					$quote = '"';
				}

				if ( $searchon !== '' ) {
					$searchon .= ' ';
				}
				if ( $this->strictMatching && ( $modifier == '' ) ) {
					// If we leave this out, boolean op defaults to OR which is rarely helpful.
					$modifier = '+';
				}

				$contLang = CargoUtils::getContentLang();
				// Some languages such as Serbian store the input form in the search index,
				// so we may need to search for matches in multiple writing system variants.
				$convertedVariants = $contLang->autoConvertToAllVariants( $term );
				if ( is_array( $convertedVariants ) ) {
					$variants = array_unique( array_values( $convertedVariants ) );
				} else {
					$variants = [ $term ];
				}

				// The low-level search index does some processing on input to work
				// around problems with minimum lengths and encoding in MySQL's
				// fulltext engine.
				// For Chinese this also inserts spaces between adjacent Han characters.
				$strippedVariants = array_map( [ $contLang, 'normalizeForSearch' ], $variants );

				// Some languages such as Chinese force all variants to a canonical
				// form when stripping to the low-level search index, so to be sure
				// let's check our variants list for unique items after stripping.
				$strippedVariants = array_unique( $strippedVariants );

				$searchon .= $modifier;
				if ( count( $strippedVariants ) > 1 ) {
					$searchon .= '(';
				}
				foreach ( $strippedVariants as $stripped ) {
					$stripped = $this->normalizeText( $stripped );
					if ( $nonQuoted && strpos( $stripped, ' ' ) !== false ) {
						// Hack for Chinese: we need to toss in quotes for
						// multiple-character phrases since normalizeForSearch()
						// added spaces between them to make word breaks.
						$stripped = '"' . trim( $stripped ) . '"';
					}
					$searchon .= "$quote$stripped$quote$wildcard ";
				}
				if ( count( $strippedVariants ) > 1 ) {
					$searchon .= ')';
				}

				// Match individual terms or quoted phrase in result highlighting...
				// Note that variants will be introduced in a later stage for highlighting!
				$regexp = $this->regexTerm( $term, $wildcard );
				$this->searchTerms[] = $regexp;
			}
			wfDebug( __METHOD__ . ": Would search with '$searchon'\n" );
			wfDebug( __METHOD__ . ': Match with /' . implode( '|', $this->searchTerms ) . "/\n" );
		} else {
			wfDebug( __METHOD__ . ": Can't understand search query '{$filteredText}'\n" );
		}

		$searchon = $this->db->addQuotes( $searchon );
		$field = $this->getIndexField( $fulltext );
		return [
			" MATCH($field) AGAINST($searchon IN BOOLEAN MODE) ",
			" MATCH($field) AGAINST($searchon IN NATURAL LANGUAGE MODE) DESC "
		];
	}

	/**
	 * @param string $string
	 * @param bool $wildcard
	 * @return string
	 */
	public function regexTerm( $string, $wildcard ) {
		$regex = preg_quote( $string, '/' );
		$contLang = CargoUtils::getContentLang();
		if ( $contLang->hasWordBreaks() ) {
			if ( $wildcard ) {
				// Don't cut off the final bit!
				$regex = "\b$regex";
			} else {
				$regex = "\b$regex\b";
			}
		} else {
			// For Chinese, words may legitimately abut other words in the text literal.
			// Don't add \b boundary checks... note this could cause false positives
			// for Latin chars.
		}
		return $regex;
	}

	/**
	 * Picks which field to index on, depending on what type of query.
	 * @param bool $fulltext
	 * @return string
	 */
	public function getIndexField( $fulltext ) {
		return $fulltext ? 'si_text' : 'si_title';
	}

}
