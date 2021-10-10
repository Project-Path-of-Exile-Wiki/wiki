<?php

class CargoDeclareTest extends MediaWikiIntegrationTestCase {
	/**
	 * @covers CargoDeclare::validateFieldOrTableName
	 * @dataProvider provideInvalidFieldOrTableName
	 */
	public function testValidateInvalidFieldOrTableName( $name, $type ) : void {
		$actual = CargoDeclare::validateFieldOrTableName( $name, $type );

		// We will need to check the strings returned, maybe we should
		// just use exceptions in CargoDeclare::validateFieldOrTableName
		// instead?
		$this->assertIsString( $actual );
	}

	/** @return array */
	public function provideInvalidFieldOrTableName() : array {
		return [
			[ 'table name', 'String' ],
			[ '_table_name', 'String' ],
			[ 'table_name_', 'String' ],
			[ '__table_name', 'String' ],
			[ 'table__name', 'String' ],
			[ 'table.name.', 'String' ],
			[ 'table_name)', 'String' ],
			[ 'table(name', 'String' ],
			[ 'table{name', 'String' ],
			[ 'table}name', 'String' ],
			[ 'table-name.', 'String' ],
			[ 'table[name', 'String' ],
			[ 'table_name]', 'String' ],
			[ 'table<name', 'String' ],
			[ 'table_name>', 'String' ],
			[ 'table,name', 'String' ],
			[ 'and', 'String' ], # SQL reserve word
			[ 'matches', 'String' ], # Cargo reserve word
		];
	}

	/**
	 * @covers CargoDeclare::validateFieldOrTableName
	 * @dataProvider provideValidFieldOrTableName
	 */
	public function testValidateValidFieldOrTableName( $name, $type ) : void {
		$actual = CargoDeclare::validateFieldOrTableName( $name, $type );

		$this->assertNull( $actual );
	}

	/** @return array */
	public function provideValidFieldOrTableName() : array {
		return [
			[ 'table_name', 'String' ],
		];
	}
}
