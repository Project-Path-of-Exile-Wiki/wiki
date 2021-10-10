<?php

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Entity\PropertyDataTypeMatcher;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;

/**
 * Test the MathDataUpdater for Wikidata
 *
 * @covers \MediaWiki\Extension\Math\MathDataUpdater
 *
 * @license GPL-2.0-or-later
 */
class MathDataUpdaterTest extends MediaWikiTestCase {

	/**
	 * @var PropertyId
	 */
	private $mathProperty;
	/**
	 * @var PropertyId
	 */
	private $otherProperty;

	/**
	 * @inheritDoc
	 */
	protected function setUp() : void {
		parent::setUp();
		$this->mathProperty = new PropertyId( 'P' . DummyPropertyDataTypeLookup::$mathId );
		$this->otherProperty = new PropertyId( 'P' . ( DummyPropertyDataTypeLookup::$mathId + 1 ) );
	}

	public function testNoMath() {
		$matcher = new PropertyDataTypeMatcher( new DummyPropertyDataTypeLookup() );
		$updater = new MathDataUpdater( $matcher );
		$statement = new Statement( new PropertyNoValueSnak( $this->otherProperty ) );
		$updater->processStatement( $statement );
		$parserOutput = $this->getMockBuilder( ParserOutput::class )->setMethods( [
			'addModules',
			'addModuleStyles',
		] )->getMock();
		$parserOutput->expects( $this->never() )->method( 'addModules' );
		$parserOutput->expects( $this->never() )->method( 'addModuleStyles' );
		/** @var ParserOutput $parserOutput */
		$updater->updateParserOutput( $parserOutput );
	}

	public function testMath() {
		$matcher = new PropertyDataTypeMatcher( new DummyPropertyDataTypeLookup() );
		$updater = new MathDataUpdater( $matcher );
		$statement = new Statement( new PropertyNoValueSnak( $this->mathProperty ) );
		$updater->processStatement( $statement );
		$parserOutput = $this->getMockBuilder( ParserOutput::class )->setMethods( [
			'addModules',
			'addModuleStyles',
		] )->getMock();
		$parserOutput->expects( $this->once() )->method( 'addModules' );
		$parserOutput->expects( $this->once() )->method( 'addModuleStyles' );
		/** @var ParserOutput $parserOutput */
		$updater->updateParserOutput( $parserOutput );
	}
}
