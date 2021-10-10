<?php

use DataValues\StringValue;
use MediaWiki\Extension\Math\MathMLRdfBuilder;
use MediaWiki\Extension\Math\MathRestbaseInterface;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikimedia\Purtle\NTriplesRdfWriter;

/**
 * Test the MathML RDF formatter
 *
 * @group Math
 * @covers \MediaWiki\Extension\Math\MathMLRdfBuilder
 * @author Moritz Schubotz (physikerwelt)
 */
class MathMLRdfBuilderTest extends MediaWikiTestCase {
	private const ACME_PREFIX_URL = 'http://acme/';
	private const ACME_REF = 'testing';
	protected static $hasRestbase;

	public static function setUpBeforeClass() : void {
		$rbi = new MathRestbaseInterface();
		self::$hasRestbase = $rbi->checkBackend( true );
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() : void {
		$this->markTestSkipped( 'All HTTP requests are banned in tests. See T265628.' );
		parent::setUp();
		if ( !self::$hasRestbase ) {
			$this->markTestSkipped( "Can not connect to Restbase Math interface." );
		}
	}

	/**
	 * @param string $test
	 * @return string
	 */
	private function makeCase( $test ) {
		$builder = new MathMLRdfBuilder();
		$writer = new NTriplesRdfWriter();
		$writer->prefix( 'www', "http://www/" );
		$writer->prefix( 'acme', self::ACME_PREFIX_URL );

		$writer->start();
		$writer->about( 'www', 'Q1' );

		$snak = new PropertyValueSnak( new PropertyId( 'P1' ), new StringValue( $test ) );
		$builder->addValue( $writer, 'acme', self::ACME_REF, 'DUMMY', '', $snak );

		return trim( $writer->drain() );
	}

	public function testValidInput() {
		$triples = $this->makeCase( 'a^2' );
		$this->assertStringContainsString(
			self::ACME_PREFIX_URL . self::ACME_REF . '> "<math',
			$triples
		);
		$this->assertStringContainsString( '<mi>a</mi>\n', $triples );
		$this->assertStringContainsString( '<mn>2</mn>\n', $triples );
		$this->assertStringContainsString( 'a^{2}', $triples );
		$this->assertStringContainsString( '^^<http://www.w3.org/1998/Math/MathML> .', $triples );
	}

	public function testInvalidInput() {
		$triples = $this->makeCase( '\notExists' );
		$this->assertStringContainsString( '<math', $triples );
		$this->assertStringContainsString( 'unknown function', $triples );
		$this->assertStringContainsString( 'notExists', $triples );
		$this->assertStringContainsString( '^^<http://www.w3.org/1998/Math/MathML> .', $triples );
	}
}
