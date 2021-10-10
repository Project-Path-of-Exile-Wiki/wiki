<?php

use MediaWiki\Extension\Math\InputCheck\InputCheckFactory;
use MediaWiki\Extension\Math\InputCheck\MathoidChecker;

/**
 * @covers \MediaWiki\Extension\Math\InputCheck\InputCheckFactory
 */
class InputCheckFactoryTest extends MediaWikiUnitTestCase {
	use FactoryArgTestTrait;

	protected static function getFactoryClass() {
		return InputCheckFactory::class;
	}

	protected static function getInstanceClass() {
		return MathoidChecker::class;
	}

	protected static function getExtraClassArgCount() {
		return 2;
	}

	/**
	 * This is required since the FactoryArgTestTrait uses the full classname.
	 * Testing without overwriting this function would result in
	 *
	 * ReflectionException: Method MediaWiki\Extension\Math\InputCheck\InputCheckFactory::
	 * newMediaWiki\Extension\Math\InputCheck\MathoidChecker() does not exist
	 *
	 * see T253613
	 * @return string
	 */
	protected function getFactoryMethodName() {
		return 'new' . ( new \ReflectionClass( MathoidChecker::class ) )->getShortName();
	}

	protected function setUp():void {
		parent::setUp();
		$this->markTestSkipped( 'FactoryArgTestTrait can not yet handle the parameter numbers.' );
	}
}
