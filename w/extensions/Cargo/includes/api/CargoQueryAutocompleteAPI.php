<?php
/**
 * Adds and handles the 'cargoqueryautocomplete' action to the MediaWiki API.
 *
 * @ingroup Cargo
 *
 * @author Ankita Mandal
 */
class CargoQueryAutocompleteAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$substr = $params['search'];
		$tables = $params['tables'];

		// Call appropriate method as per the parameters passed
		if ( $tables === null ) {
			$data = self::getTables( $substr );
		} else {
			$data = self::getFields( $tables, $substr );
		}

		// If we got back an error message, exit with that message.
		if ( !is_array( $data ) ) {
			if ( !$data instanceof Message ) {
				$data = ApiMessage::create( new RawMessage( '$1', [ $data ] ), 'unknownerror' );
			}
			$this->dieWithError( $data );
		}

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $data, 'p' );
		$result->addValue( null, $this->getModuleName(), $data );
	}

	protected function getAllowedParams() {
		return [
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'search' => null,
			'tables' => null,
		];
	}

	protected function getParamDescription() {
		return [
				'search' => 'Search substring',
				'tables' => 'Array of selected cargo table(s)'
		];
	}

	protected function getDescription() {
		return 'Autocompletion call used by the Cargo extension (https://www.mediawiki.org/Extension:Cargo) on the CargoQuery page.';
	}

	protected function getExamples() {
		return [
			'api.php?action=cargoqueryautocomplete&format=json&search=Em',
			'api.php?action=cargoqueryautocomplete&format=json&tables=Employee&search=Skil',
		];
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}

	public function getTables( $substr ) {
		$dbr = wfGetDB( DB_MASTER );
		$tables = [];
		if ( $substr === null || $substr == '' ) {
			$res = $dbr->select(
				'cargo_tables',
				[ 'main_table' ],
				"main_table NOT LIKE '%__NEXT'"
				);
		}
		$res = $dbr->select(
			'cargo_tables',
			[ 'main_table' ],
			[ "main_table LIKE '%$substr%'","main_table NOT LIKE '%__NEXT'" ]
		);
		foreach ( $res as $row ) {
			array_push( $tables, $row );
		}

		return $tables;
	}

	public function getFields( $tableNames, $substr ) {
		$tables = explode( ",", $tableNames );
		$fields = [];
		foreach ( $tables as &$table ) {
			$tableSchemas = [];
			$dbr = wfGetDB( DB_REPLICA );
			$res = $dbr->select( 'cargo_tables', [ 'main_table', 'table_schema' ],
				[ 'main_table' => $table ] );
			foreach ( $res as $row ) {
				$tableName = $row->main_table;
				$tableSchemaString = $row->table_schema;
				$tableSchemas[$tableName] = CargoTableSchema::newFromDBString( $tableSchemaString );
				$mFieldDescriptions = array_column( $tableSchemas, 'mFieldDescriptions' );
				$tempfields = array_keys( call_user_func_array( 'array_merge', $mFieldDescriptions ) );
				array_push( $tempfields, "_pageName", "_pageTitle", "_pageNamespace", "_pageID", "_ID" );
				foreach ( $tempfields as $key => $value ) {
					if ( ( $substr === null || $substr == '' || stripos( $tableName, $substr ) === 0 ) ||
						stristr( $value, $substr ) ||
						stristr( $tableName . '.' . $value, $substr )
					) {
						array_push( $fields, $tableName . '.' . $value );
					}
				}
			}
		}
		return $fields;
	}
}
