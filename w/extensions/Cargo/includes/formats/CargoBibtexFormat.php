<?php
/**
 * @author Tomás Bolaño
 * @ingroup Cargo
 */

class CargoBibtexFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'default entry type' => [ 'type' => 'string' ] ,
			'link text' => [ 'type' => 'string' ]
		];
	}

	/**
	 * Returns the first word from $title with a length equal or greater than
	 * $numChars. If no word exists, it repeats the process for $numChars-1,
	 * $numChars-2, etc., until a word is found. Before returning the word, it
	 * removes all non-alphabetic characters.
	 *
	 * @param string $title
	 * @param int $numChars
	 */
	private static function generateTitleKey( $title, $numChars = 5 ) {
		$titleKey = '';
		if ( $title != '' && $numChars > 0 ) {
			$titleWords = explode( ' ', str_replace( '-', ' ', strtolower( $title ) ) );
			for ( $i = $numChars; $i > 0; $i-- ) {
				foreach ( $titleWords as $titleWord ) {
					if ( strlen( $titleWord ) >= $i ) {
						$titleKey = preg_replace( "/[^a-zA-Z]/", '', $titleWord );
						break 2;
					}
				}
			}
		}
		return $titleKey;
	}

	/**
	 * Returns the last name of the first author. Before returning the last
	 * name, removes all non-alphabetic characters.
	 *
	 * @param string $author The list of authors in bibtex format.
	 */
	private static function generateAuthorKey( $author ) {
		$authorKey = '';
		if ( $author != '' ) {
			$authorList = explode( ' and ', $author );
			$firstAuthor = trim( $authorList[0] );

			// A bibtex author can be in different formats:
			// - First von Last
			// - von Last, First
			// - von Last, Jr, First
			// where von is a lowercase particle such as "de" or "de la"
			if ( strpos( $firstAuthor, ',' ) != false ) {
				$vonLast = trim( strtok( $firstAuthor, ',' ) );
				$nameElems = explode( ' ', $vonLast );
			} else {
				$nameElems = explode( ' ', $firstAuthor );
			}
			$lastName = end( $nameElems );

			// If the name has an hyphen ('-') we will keep only the first part
			$authorKey = preg_replace( "/[^a-zA-Z]/", '', strtok( $lastName, '-' ) );
			$authorKey = strtolower( $authorKey );
		}

		return $authorKey;
	}

	/**
	 * Returns a key for an entry given the author, the title, and the year.
	 */
	private static function generateEntryKey( $author, $title, $year ) {
		return self::generateAuthorKey( $author ) . $year . self::generateTitleKey( $title );
	}

	/**
	 * BibTeX month abbreviations
	 *
	 * @var string[]
	 */
	private static $monthStrings = [ 'jan', 'feb', 'mar', 'apr', 'may',
		'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec' ];

	/**
	 * This is the list of BibTeX fields that do not have special cases for
	 * generating the output. Fields that have special cases are: title, author,
	 * editor, pages, month, and year.
	 *
	 * @var string[]
	 */
	private static $bibtexFields = [ 'address', 'annote', 'booktitle',
		'chapter', 'crossref', 'doi', 'edition', 'howpublished', 'institution',
		'journal', 'key', 'note', 'number', 'organization', 'publisher',
		'school', 'series', 'type', 'volume' ];

	public static function generateBibtexEntries( $valuesTable, $fieldDescriptions, $displayParams ) {
		if ( array_key_exists( 'default entry type', $displayParams ) ) {
			$defaultEntryType = strtolower( $displayParams['default entry type'] );
		} else {
			$defaultEntryType = 'article';
		}

		// We check here the existing fields so that we do not need later
		// to call the array_key_exists function in the for loop
		$bibtexkeyExists = array_key_exists( 'bibtexkey', $fieldDescriptions ) ? true : false;
		$entryTypeExists = array_key_exists( 'entrytype', $fieldDescriptions ) ? true : false;

		$dateExists = array_key_exists( 'date', $fieldDescriptions ) ? true : false;
		$yearExists = array_key_exists( 'year', $fieldDescriptions ) ? true : false;
		$monthExists = array_key_exists( 'month', $fieldDescriptions ) ? true : false;

		$authorExists = array_key_exists( 'author', $fieldDescriptions ) ? true : false;
		$editorExists = array_key_exists( 'editor', $fieldDescriptions ) ? true : false;
		$titleExists = array_key_exists( 'title', $fieldDescriptions ) ? true : false;

		$pagesExists = array_key_exists( 'pages', $fieldDescriptions ) ? true : false;
		$initialPageExists = array_key_exists( 'initialpage', $fieldDescriptions ) ? true : false;
		$lastPageExists = array_key_exists( 'lastpage', $fieldDescriptions ) ? true : false;

		// generate array of bibtex fields to output
		$bibtexOutputFields = [];
		foreach ( self::$bibtexFields as $bibtexField ) {
			if ( array_key_exists( $bibtexField, $fieldDescriptions ) ) {
				$bibtexOutputFields[] = $bibtexField;
			}
		}

		// Define several strings that will be used to generate the output
		$tabString = '  ';
		$newlineString = "\n";
		$bibtexEntryBeforeString = '';
		$bibtexEntryAfterString = "\n\n";

		$text = '';
		foreach ( $valuesTable as $value ) {
			if ( $entryTypeExists && $value['entrytype'] != '' ) {
				$entryType = $value['entrytype'];
			} else {
				$entryType = $defaultEntryType;
			}

			// Obtain values for the fields year, month, author, editor, and title
			if ( $dateExists && $value['date'] != '' ) {
				$year = strtok( $value['date'], '-' );
				if ( $value['date__precision'] <= 2 ) {
					$month = ltrim( strtok( '-' ), '0' );
				} else {
					$month = '';
				}
			} else {
				$year = $yearExists ? $value['year'] : '';
				$month = $monthExists ? $value['month'] : '';
			}

			if ( $authorExists && $value['author'] != '' ) {
				if ( $fieldDescriptions['author']->mIsList ) {
					$delimiter = $fieldDescriptions['author']->getDelimiter();
					$author = str_replace( $delimiter, ' and ', $value['author'] );
				} else {
					$author = $value['author'];
				}
			} else {
				$author == '';
			}

			if ( $editorExists && $value['editor'] != '' ) {
				if ( $fieldDescriptions['editor']->mIsList ) {
					$delimiter = $fieldDescriptions['editor']->getDelimiter();
					$editor = str_replace( $delimiter, ' and ', $value['editor'] );
				} else {
					$editor = $value['editor'];
				}
			} else {
				$editor = '';
			}

			$title = $titleExists ? $value['title'] : '';

			// Generate the entry header (entry type and key)
			$text .= $bibtexEntryBeforeString;
			$text .= '@' . $entryType . '{';
			if ( $bibtexkeyExists && $value['bibtexkey'] != '' ) {
				$text .= $value['bibtexkey'];
			} else {
				$text .= self::generateEntryKey( $author, $title, $year );
			}
			$text .= ',' . $newlineString;

			// Generate title, author, and editor fields
			if ( $title != '' ) {
				$text .= $tabString . 'title={' . $title . '},' . $newlineString;
			}

			if ( $author != '' ) {
				$text .= $tabString . 'author={' . $author . '},' . $newlineString;
			}

			if ( $editor != '' ) {
				$text .= $tabString . 'editor={' . $editor . '},' . $newlineString;
			}

			// Generate remaining fields (except pages, year, and month)
			foreach ( $bibtexOutputFields as $bibtexOutputField ) {
				if ( $value[$bibtexOutputField] != '' ) {
					$text .= $tabString . $bibtexOutputField . '={' . $value[$bibtexOutputField] . '},' . $newlineString;
				}
			}

			// Generate pages, year, and month fields
			if ( $pagesExists && $value['pages'] != '' ) {
				$text .= $tabString . 'pages={' . $value['pages'] . '}' . $newlineString;
			} elseif ( $initialPageExists && $lastPageExists && $value['initialpage'] != '' ) {
				$pages = $value['initialpage'];
				if ( $value['lastpage'] != '' && $value['initialpage'] != $value['lastpage'] ) {
					$pages .= '--' . $value['lastpage'];
				}
				$text .= $tabString . 'pages={' . $pages . '},' . $newlineString;
			}

			if ( $year != '' ) {
				$text .= $tabString . 'year={' . $year . '},' . $newlineString;
			}

			if ( $month != '' ) {
				// For the month field, if it is passed as a number between 1
				// and 12 we use the three letter month abbreviation (i.e., jan,
				// feb, mar, etc.). If it is passed as any other string, then
				// that string will be used.
				if ( $month >= 1 && $month <= 12 ) {
					$text .= $tabString . 'month=' . self::$monthStrings[$month - 1] . ',' . $newlineString;
				} else {
					$text .= $tabString . 'month={' . $month . '},' . $newlineString;
				}
			}

			$text .= '}';
			$text .= $bibtexEntryAfterString;
		}

		return $text;
	}

	/**
	 * This function creates a link for the query. The query and the bibtex
	 * generation are actually executed by the function displayBibtexData of the
	 * CargoExport class.
	 *
	 * @param array $sqlQueries
	 * @param array $displayParams
	 * @param array|null $querySpecificParams Unused
	 * @return string HTML
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$ce = SpecialPage::getTitleFor( 'CargoExport' );
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'bibtex';
		if ( array_key_exists( 'default entry type', $displayParams ) && $displayParams['default entry type'] != '' ) {
			$queryParams['default entry type'] = $displayParams['default entry type'];
		}
		if ( array_key_exists( 'link text', $displayParams ) && $displayParams['link text'] != '' ) {
			$linkText = $displayParams['link text'];
		} else {
			$linkText = wfMessage( 'cargo-viewbibtex' )->text();
		}
		$linkAttrs = [
			'href' => $ce->getFullURL( $queryParams ),
			'target' => '_blank' // link will open in a new tab
		];
		$text = Html::rawElement( 'a', $linkAttrs, $linkText );

		return $text;
	}

}
