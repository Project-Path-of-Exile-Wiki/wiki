<?php
/**
 * @ingroup Cargo
 * @file
 */

use MediaWiki\MediaWikiServices;

/**
 * Handle the iCalendar export format.
 * @since 2.6
 */
class CargoICalendarFormat extends CargoDeferredFormat {

	public static function allowedParameters() {
		return [
			'link text' => [ 'type' => 'string' ],
			'filename' => [ 'type' => 'string' ],
			'icalendar name' => [ 'type' => 'string' ],
			'icalendar description' => [ 'type' => 'string' ],
		];
	}

	/**
	 * @param CargoSQLQuery[] $sqlQueries
	 * @param string[] $displayParams Unused
	 * @param string[]|null $querySpecificParams Unused
	 * @return string An HTML link to Special:CargoExport with the required query string.
	 */
	public function queryAndDisplay( $sqlQueries, $displayParams, $querySpecificParams = null ) {
		$queryParams = $this->sqlQueriesToQueryParams( $sqlQueries );
		$queryParams['format'] = 'icalendar';
		// Calendar name.
		if ( isset( $displayParams['icalendar name'] ) && $displayParams['icalendar name'] ) {
			$queryParams['icalendar name'] = $displayParams['icalendar name'];
		}
		// Calendar description.
		if ( isset( $displayParams['icalendar description'] )
			&& $displayParams['icalendar description']
		) {
			$queryParams['icalendar description'] = $displayParams['icalendar description'];
		}
		// Filename.
		if ( isset( $displayParams['filename'] ) && $displayParams['filename'] ) {
			$queryParams['filename'] = $displayParams['filename'];
		}
		// Link.
		if ( isset( $displayParams['link text'] ) && $displayParams['link text'] ) {
			$linkText = $displayParams['link text'];
		} else {
			$linkText = wfMessage( 'cargo-viewicalendar' )->parse();
		}
		$export = SpecialPage::getTitleFor( 'CargoExport' );
		return Html::rawElement( 'a', [ 'href' => $export->getFullURL( $queryParams ) ], $linkText );
	}

	/**
	 * Get the iCalendar format output.
	 * @param WebRequest $request
	 * @param CargoSQLQuery[] $sqlQueries
	 * @return string
	 */
	public function getCalendar( WebRequest $request, $sqlQueries ) {
		$name = $request->getText( 'icalendar_name', 'Calendar' );
		$desc = $request->getText( 'icalendar_description', false );
		// Merge, remove empty lines, and re-index the lines of the calendar.
		$calLines = array_values( array_filter( array_merge(
			[
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:mediawiki/cargo',
			],
			$this->text( 'NAME', $name ),
			$this->text( 'X-WR-CALNAME', $name ),
			$desc ? $this->text( 'DESCRIPTION', $desc ) : []
		) ) );
		foreach ( $sqlQueries as $sqlQuery ) {
			$queryResults = $sqlQuery->run();
			foreach ( $queryResults as $result ) {
				$eventLines = $this->getEvent( $result );
				$calLines = array_merge( $calLines, $eventLines );
			}
		}
		$calLines[] = 'END:VCALENDAR';
		return implode( "\r\n", $calLines );
	}

	/**
	 * Get the lines of an event.
	 * @param string[] $result
	 * @return string[]
	 */
	public function getEvent( $result ) {
		$title = Title::newFromText( $result['_pageName'] );
		// Only re-query the Page if its ID or modification date are not included in the original query.
		if ( !isset( $result['_pageID'] ) || !isset( $result['_modificationDate'] ) ) {
			$page = WikiPage::factory( $title );
		}
		$pageId = isset( $result['_pageID'] ) ? $result['_pageID'] : $page->getId();
		$permalink = SpecialPage::getTitleFor( 'Redirect', 'page/' . $pageId, $result['_ID'] ?? '' );
		$uid = $permalink->getCanonicalURL();
		// Page values are stored in the wiki's timezone.
		$wikiTimezone = MediaWikiServices::getInstance()->getMainConfig()->get( 'Localtimezone' );
		$startDateTime = new DateTime( $result['start'], new DateTimeZone( $wikiTimezone ) );
		$start = wfTimestamp( TS_ISO_8601_BASIC, $startDateTime->getTimestamp() );
		$end = false;
		if ( isset( $result['end'] ) && $result['end'] ) {
			$endDateTime = new DateTime( $result['end'], new DateTimeZone( $wikiTimezone ) );
			$end = wfTimestamp( TS_ISO_8601_BASIC, $endDateTime->getTimestamp() );
		}
		// Modification date is stored in UTC.
		$dtstamp = isset( $result['_modificationDate'] )
			? wfTimestamp( TS_ISO_8601_BASIC, $result['_modificationDate'] )
			: MWTimestamp::convert( TS_ISO_8601_BASIC, $page->getTimestamp() );
		$desc = false;
		if ( isset( $result['description'] ) && $result['description'] ) {
			$desc = $result['description'];
		}
		$location = false;
		if ( isset( $result['location'] ) && $result['location'] ) {
			$location = $result['location'];
		}
		// Merge, remove empty lines, and re-index the lines of the event.
		return array_values( array_filter( array_merge(
			[
				'BEGIN:VEVENT',
				'UID:' . $uid,
				'DTSTAMP:' . $dtstamp,
			],
			$this->text( 'SUMMARY', $title->getText() ),
			[
				'DTSTART:' . $start,
				$end ? 'DTEND:' . $end : '',
			],
			$desc ? $this->text( 'DESCRIPTION', $desc ) : [],
			$location ? $this->text( 'LOCATION:', $location ) : [],
			[
				'END:VEVENT',
			]
		) ) );
	}

	/**
	 * Get the lines of a text property (wrapped and escaped).
	 * @param string $prop The text property name.
	 * @param string $str The value of the property.
	 * @return string[]
	 */
	public function text( $prop, $str ) {
		$lang = CargoUtils::getContentLang()->getHtmlCode();
		// Make sure the language conforms to RFC5646.
		$langProp = '';
		if ( Language::isWellFormedLanguageTag( $lang ) ) {
			$langProp = ";LANGUAGE=$lang";
		}
		return $this->wrap( "$prop$langProp:" . $this->esc( $str ) );
	}

	/**
	 * Wrap a line into an array of strings with max length 75 bytes.
	 *
	 * Kudos to  spatie/icalendar-generator, MIT license.
	 * @link https://github.com/spatie/icalendar-generator/blob/00346196cf526de2ae3e4ccc562294a59a27b5b2/src/Builders/ComponentBuilder.php#L76..L92
	 *
	 * @param string $line
	 * @return string[]
	 */
	public function wrap( $line ) {
		$chippedLines = [];
		while ( strlen( $line ) > 0 ) {
			if ( strlen( $line ) > 75 ) {
				$chippedLines[] = mb_strcut( $line, 0, 75, 'utf-8' );
				$line = ' ' . mb_strcut( $line, 75, strlen( $line ), 'utf-8' );
			} else {
				$chippedLines[] = $line;
				break;
			}
		}
		return $chippedLines;
	}

	/**
	 * Escape a string according to RFC5545 (backslashes, semicolons, commas, and newlines).
	 * @link https://tools.ietf.org/html/rfc5545#section-3.3.11
	 * @param string $str
	 * @return string
	 */
	public function esc( $str ) {
		$replacements = [
			'\\' => '\\\\',
			';' => '\\;',
			',' => '\\,',
			"\n" => '\\n',
		];
		return str_replace( array_keys( $replacements ), $replacements, $str );
	}
}
