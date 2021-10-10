<?php

class CargoFieldDescriptionTest extends MediaWikiIntegrationTestCase {
	private $cargoFieldDescription;

	public function setUp() : void {
		$this->cargoFieldDescription = new CargoFieldDescription();
	}

	/**
	 * @covers CargoFieldDescription::newFromString
	 */
	public function testNewFromStringReturnNull() {
		$actual = CargoFieldDescription::newFromString( 'list' );
		$this->assertNull( $actual );
	}

	/**
	 * @covers CargoFieldDescription::newFromString
	 */
	public function testNewFromString() {
		$fieldDescStr = '{{#cargo_declare:_table=Test|Name='
			. 'String (size=10;dependent on=size;delimiter=\;allowed values=*One**Oneone;mandatory;'
			. 'unique;regex=xxx;hidden;hierarchy;)}}';

		$actual = CargoFieldDescription::newFromString( $fieldDescStr );
		$this->assertInstanceOf(
			CargoFieldDescription::class, $actual
		);
	}

	/**
	 * @covers CargoFieldDescription::newFromDBArray
	 * @dataProvider provideDescriptionData
	 */
	public function testNewFromDBArray( $fieldDescData ) {
		$actual = CargoFieldDescription::newFromDBArray( $fieldDescData );

		$this->assertInstanceOf( CargoFieldDescription::class, $actual );
		$this->assertSame( 'String', $actual->mType );
		$this->assertSame( 100, $actual->mSize );
		$this->assertIsArray( $actual->mDependentOn );
		$this->assertTrue( $actual->mIsList );
		$this->assertSame(
			"Something\nto delimit",
			$actual->getDelimiter()
		);
		$this->assertIsArray( $actual->mAllowedValues );
		$this->assertTrue( $actual->mIsMandatory );
		$this->assertTrue( $actual->mIsUnique );
		$this->assertSame( 'regex', $actual->mRegex );
		$this->assertTrue( $actual->mIsHidden );
		$this->assertTrue( $actual->mIsHierarchy );
		$this->assertIsArray( $actual->mHierarchyStructure );
		$this->assertSame( 'Nothing', $actual->mOtherParams['extra'] );
	}

	/** @return array */
	public function provideDescriptionData() {
		return [
			[
				[
					'type' => 'String',
					'size' => 100,
					'dependent on' => [],
					'isList' => true,
					'delimiter' => 'Something\nto delimit',
					'allowedValues' => [],
					'mandatory' => true,
					'unique' => true,
					'regex' => 'regex',
					'hidden' => true,
					'hierarchy' => true,
					'hierarchyStructure' => [],
					'extra' => 'Nothing'
				]
			],
		];
	}

	/**
	 * @covers CargoFieldDescription::setDelimiter
	 * @covers CargoFieldDescription::getDelimiter
	 */
	public function testGetDelimiter() {
		$this->cargoFieldDescription->setDelimiter(
			'A delimiter\n\nto test'
		);

		$this->assertSame(
			"A delimiter\n\nto test",
			$this->cargoFieldDescription->getDelimiter()
		);
	}

	/**
	 * @covers CargoFieldDescription::isDateOrDatetime
	 * @dataProvider provideDateTimeFormat
	 */
	public function testIsDateOrDatetimeFormat( $format ) {
		$this->cargoFieldDescription->mType = $format;

		$this->assertTrue( $this->cargoFieldDescription->isDateOrDatetime() );
	}

	/** @return array */
	public function provideDateTimeFormat() {
		return [
			[ 'Date' ],
			[ 'Start date' ],
			[ 'End date' ],
			[ 'Datetime' ],
			[ 'Start datetime' ],
			[ 'End datetime' ],
		];
	}

	/**
	 * @covers CargoFieldDescription::isDateOrDatetime
	 * @dataProvider provideNotDateTimeFormat
	 */
	public function testIsNotDateOrDatetimeFormat( $format ) {
		$this->cargoFieldDescription->mType = $format;

		$this->assertFalse( $this->cargoFieldDescription->isDateOrDatetime() );
	}

	/** @return array */
	public function provideNotDateTimeFormat() {
		return [
			[ 'Da-te' ],
			[ 'Start-date' ],
			[ 'End-date' ],
			[ 'Date-time' ],
			[ 'Start-datetime' ],
			[ 'End-datetime' ],
		];
	}

	/**
	 * @covers CargoFieldDescription::getFieldSize
	 * @dataProvider provideFieldSize
	 */
	public function testGetFieldSize( $type, $size, $expected ) {
		$this->cargoFieldDescription->mType = $type;
		$this->cargoFieldDescription->mSize = $size;

		$actual = $this->cargoFieldDescription->getFieldSize();
		$this->assertSame( $expected, $actual );
	}

	/** @return array */
	public function provideFieldSize() {
		return [
			[ 'Date', 100, null ],
			[ 'Integer', 200, null ],
			[ 'String', 400, 400 ],
		];
	}

	/**
	 * @covers CargoFieldDescription::getFieldSize
	 */
	public function testGetFieldSizeWithCargoDefaultStringBytes() {
		$this->setMwGlobals( [
			"wgCargoDefaultStringBytes" => 500
		] );

		$actual = $this->cargoFieldDescription->getFieldSize();
		$this->assertSame( 500, $actual );
	}

	/**
	 * @covers CargoFieldDescription::toDBArray
	 */
	public function testToDBArray() {
		$this->cargoFieldDescription->mType = 'String';
		$this->cargoFieldDescription->mSize = 40;
		$this->cargoFieldDescription->mDependentOn = [ 'nothing' ];
		$this->cargoFieldDescription->mIsList = true;
		$this->cargoFieldDescription->setDelimiter( '\n' );
		$this->cargoFieldDescription->mAllowedValues = [ 'nothing' ];
		$this->cargoFieldDescription->mIsMandatory = true;
		$this->cargoFieldDescription->mIsUnique = true;
		$this->cargoFieldDescription->mRegex = 'regex';
		$this->cargoFieldDescription->mIsHidden = true;
		$this->cargoFieldDescription->mIsHierarchy = true;
		$this->cargoFieldDescription->mOtherParams['extra'] = 'nothing';

		$fieldDescArray = $this->cargoFieldDescription->toDBArray();
		$this->assertIsArray( $fieldDescArray );
		$this->assertArrayHasKey( 'type', $fieldDescArray );
		$this->assertArrayHasKey( 'size', $fieldDescArray );
		$this->assertArrayHasKey( 'dependent on', $fieldDescArray );
		$this->assertArrayHasKey( 'isList', $fieldDescArray );
		$this->assertArrayHasKey( 'delimiter', $fieldDescArray );
		$this->assertArrayHasKey( 'allowedValues', $fieldDescArray );
		$this->assertArrayHasKey( 'mandatory', $fieldDescArray );
		$this->assertArrayHasKey( 'unique', $fieldDescArray );
		$this->assertArrayHasKey( 'regex', $fieldDescArray );
		$this->assertArrayHasKey( 'hidden', $fieldDescArray );
		$this->assertArrayHasKey( 'hierarchy', $fieldDescArray );
		$this->assertArrayHasKey( 'hierarchyStructure', $fieldDescArray );
		$this->assertArrayHasKey( 'extra', $fieldDescArray );
	}
}
