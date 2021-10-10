<?php
/**
 * @file
 */

/**
 * Tests for the iCalendar format.
 */
class CargoICalendarFormatTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		// Set useless article path, for easier URL testing.
		$this->setMwGlobals( [ 'wgArticlePath' => 'cargotest/$1' ] );
	}

	/**
	 * @covers CargoICalendarFormat::getCalendar
	 * @dataProvider provideGetCalendar
	 */
	public function testGetCalendar( $expected, $queryResult, $requestParams ): void {
		$format = new CargoICalendarFormat( $this->createMock( OutputPage::class ) );
		$sqlQuery = $this->createMock( CargoSQLQuery::class );
		$sqlQuery->expects( $this->once() )->method( 'run' )
			->willReturn( [ $queryResult ] );
		$cal = $format->getCalendar( new FauxRequest( $requestParams ), [ $sqlQuery ] );
		static::assertSame( $expected, $cal );
	}

	public function provideGetCalendar() {
		return [
			'simple' => [
				"BEGIN:VCALENDAR\r\n"
				. "VERSION:2.0\r\n"
				. "PRODID:mediawiki/cargo\r\n"
				. "NAME;LANGUAGE=en:Calendar\r\n"
				. "X-WR-CALNAME;LANGUAGE=en:Calendar\r\n"
				. "BEGIN:VEVENT\r\n"
				. "UID:cargotest/Special:Redirect/page/0\r\n"
				. "DTSTAMP:20200102T030405Z\r\n"
				. "SUMMARY;LANGUAGE=en:Lorem ipsum\r\n"
				. "DTSTART:20200102T030405Z\r\n"
				. "END:VEVENT\r\n"
				. "END:VCALENDAR",
				[
					'_pageName' => 'Lorem ipsum',
					'start' => '2020-01-02 03:04:05',
					'_modificationDate' => '2020-01-02 03:04:05',
				],
				[],
			],
			'long name' => [
				"BEGIN:VCALENDAR\r\n"
				. "VERSION:2.0\r\n"
				. "PRODID:mediawiki/cargo\r\n"
				. "NAME;LANGUAGE=en:Lorem ipsum dolor sit amet\, consectetur adipiscing elit. \r\n"
				. " Etiam placerat nisi.\r\n"
				. "X-WR-CALNAME;LANGUAGE=en:Lorem ipsum dolor sit amet\, consectetur adipiscin\r\n"
				. " g elit. Etiam placerat nisi.\r\n"
				. "DESCRIPTION;LANGUAGE=en:Lorem ipsum consectetur adipiscing elit. Etiam plac\r\n"
				. " erat nisi lorem ipsum.\r\n"
				. "BEGIN:VEVENT\r\n"
				. "UID:cargotest/Special:Redirect/page/0\r\n"
				. "DTSTAMP:20200102T030405Z\r\n"
				. "SUMMARY;LANGUAGE=en:Lorem ipsum\r\n"
				. "DTSTART:20200102T030405Z\r\n"
				. "END:VEVENT\r\n"
				. "END:VCALENDAR",
				[
					'_pageName' => 'Lorem ipsum',
					'start' => '2020-01-02 03:04:05',
					'_modificationDate' => '2020-01-02 03:04:05',
				],
				[
					'icalendar_name' => 'Lorem ipsum dolor sit amet, consectetur '
						. 'adipiscing elit. Etiam placerat nisi.',
					'icalendar_description' => 'Lorem ipsum consectetur adipiscing '
						. 'elit. Etiam placerat nisi lorem ipsum.',
				]
			],
		];
	}

	/**
	 * @covers CargoICalendarFormat::getEvent
	 * @dataProvider provideGetEvent
	 */
	public function testGetEvent( $dbRow, $icalLines, $localtimezone = null ) {
		if ( $localtimezone ) {
			$this->setMwGlobals( 'wgLocaltimezone', $localtimezone );
		}
		$format = new CargoICalendarFormat( $this->createMock( OutputPage::class ) );
		static::assertSame( $icalLines, $format->getEvent( $dbRow ) );
	}

	public function provideGetEvent() {
		return [
			'simple' => [
				[
					'_pageName' => 'Lorem ipsum',
					'start' => '2020-01-02 03:04:05',
					'_modificationDate' => '2020-01-02 03:04:05',
				],
				[
					'BEGIN:VEVENT',
					'UID:cargotest/Special:Redirect/page/0',
					'DTSTAMP:20200102T030405Z',
					'SUMMARY;LANGUAGE=en:Lorem ipsum',
					'DTSTART:20200102T030405Z',
					'END:VEVENT',
				]
			],
			'start and end, long name, description' => [
				[
					'_pageName' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '
						. 'Etiam placerat nisi non metus porta, ac tincidunt quam molestie.',
					'_modificationDate' => '2020-01-02 03:04:05',
					'start' => '2020-01-02 03:04:05',
					'end' => '2020-02-02 03:04:05',
					'description' => "Foo bar; lorem, and: escaped\ncharacters."
				],
				[
					'BEGIN:VEVENT',
					'UID:cargotest/Special:Redirect/page/0',
					'DTSTAMP:20200102T030405Z',
					'SUMMARY;LANGUAGE=en:Lorem ipsum dolor sit amet\, consectetur adipiscing eli',
					' t. Etiam placerat nisi non metus porta\, ac tincidunt quam molestie.',
					'DTSTART:20200102T030405Z',
					'DTEND:20200202T030405Z',
					'DESCRIPTION;LANGUAGE=en:Foo bar\; lorem\, and: escaped\ncharacters.',
					'END:VEVENT',
				]
			],
			'different wiki timezone' => [
				[
					'_pageName' => 'Lorem ipsum',
					'start' => '2020-01-02 23:04:05',
					'_modificationDate' => '2020-01-02 03:04:05',
				],
				[
					'BEGIN:VEVENT',
					'UID:cargotest/Special:Redirect/page/0',
					'DTSTAMP:20200102T030405Z',
					'SUMMARY;LANGUAGE=en:Lorem ipsum',
					// 2020-01-02 in Melbourne was +1100
					'DTSTART:20200102T120405Z',
					'END:VEVENT',
				],
				'Australia/Melbourne',
			],
		];
	}
}
