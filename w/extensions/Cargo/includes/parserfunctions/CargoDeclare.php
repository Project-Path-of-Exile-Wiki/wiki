<?php
/**
 * Class for the #cargo_declare parser function, as well as for the creation
 * (and re-creation) of Cargo database tables.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoDeclare {

	/**
	 * "Reserved words" - terms that should not be used as table or field
	 * names, because they are reserved for SQL.
	 * (Some words here are much more likely to be used than others.)
	 * @TODO - currently this list only includes reserved words from
	 * MySQL; other DB systems' additional words (if any) should be added
	 * as well.
	 *
	 * @var string[]
	 */
	private static $sqlReservedWords = [
		'accessible', 'add', 'all', 'alter', 'analyze',
		'and', 'as', 'asc', 'asensitive', 'before',
		'between', 'bigint', 'binary', 'blob', 'both',
		'by', 'call', 'cascade', 'case', 'change',
		'char', 'character', 'check', 'collate', 'column',
		'condition', 'constraint', 'continue', 'convert', 'create',
		'cross', 'current_date', 'current_time', 'current_timestamp', 'current_user',
		'cursor', 'database', 'databases', 'day_hour', 'day_microsecond',
		'day_minute', 'day_second', 'dec', 'decimal', 'declare',
		'default', 'delayed', 'delete', 'desc', 'describe',
		'deterministic', 'distinct', 'distinctrow', 'div', 'double',
		'drop', 'dual', 'each', 'else', 'elseif',
		'enclosed', 'escaped', 'exists', 'exit', 'explain',
		'false', 'fetch', 'float', 'float4', 'float8',
		'for', 'force', 'foreign', 'from', 'fulltext',
		'generated', 'get', 'grant', 'group', 'having',
		'high_priority', 'hour_microsecond', 'hour_minute', 'hour_second', 'if',
		'ignore', 'in', 'index', 'infile', 'inner',
		'inout', 'insensitive', 'insert', 'int', 'int1',
		'int2', 'int3', 'int4', 'int8', 'integer',
		'interval', 'into', 'io_after_gtids', 'io_before_gtids', 'is',
		'iterate', 'join', 'key', 'keys', 'kill',
		'leading', 'leave', 'left', 'like', 'limit',
		'linear', 'lines', 'load', 'localtime', 'localtimestamp',
		'lock', 'long', 'longblob', 'longtext', 'loop',
		'low_priority', 'master_bind', 'master_ssl_verify_server_cert', 'match', 'maxvalue',
		'mediumblob', 'mediumint', 'mediumtext', 'middleint', 'minute_microsecond',
		'minute_second', 'mod', 'modifies', 'natural', 'no_write_to_binlog',
		'not', 'null', 'numeric', 'on', 'optimize',
		'optimizer_costs', 'option', 'optionally', 'or', 'order',
		'out', 'outer', 'outfile', 'partition', 'precision',
		'primary', 'procedure', 'purge', 'range', 'read',
		'read_write', 'reads', 'real', 'references', 'regexp',
		'release', 'rename', 'repeat', 'replace', 'require',
		'resignal', 'restrict', 'return', 'revoke', 'right',
		'rlike', 'schema', 'schemas', 'second_microsecond', 'select',
		'sensitive', 'separator', 'set', 'show', 'signal',
		'smallint', 'spatial', 'specific', 'sql', 'sql_big_result',
		'sql_calc_found_rows', 'sql_small_result', 'sqlexception', 'sqlstate', 'sqlwarning',
		'ssl', 'starting', 'stored', 'straight_join', 'table',
		'terminated', 'then', 'tinyblob', 'tinyint', 'tinytext',
		'to', 'trailing', 'trigger', 'true', 'undo',
		'union', 'unique', 'unlock', 'unsigned', 'update',
		'usage', 'use', 'using', 'utc_date', 'utc_time',
		'utc_timestamp', 'values', 'varbinary', 'varchar', 'varcharacter',
		'varying', 'virtual', 'when', 'where', 'while',
		'with', 'write', 'xor', 'year_month', 'zerofill'
	];

	/**
	 * Words that are similarly reserved for Cargo - thankfully, a much
	 * shorter list.
	 *
	 * @var string[]
	 */
	private static $cargoReservedWords = [
		'holds', 'matches', 'near', 'within'
	];

	/**
	 * FIXME: Should this throw an exception instead? This will
	 * enhance the method to be better tested.
	 *
	 * @param string $name The name of the table or field
	 * @param string $type The type of the field provided
	 * @return string|null
	 */
	public static function validateFieldOrTableName( $name, $type ) {
		if ( preg_match( '/\s/', $name ) ) {
			return "Error: $type name \"$name\" contains whitespaces. Whitepaces of any kind are not allowed; consider using underscores (\"_\") instead.";
		} elseif ( strpos( $name, '_' ) === 0 ) {
			return "Error: $type name \"$name\" begins with an underscore; this is not allowed.";
		} elseif ( substr( $name, -1 ) === '_' ) {
			return "Error: $type name \"$name\" ends with an underscore; this is not allowed.";
		} elseif ( strpos( $name, '__' ) !== false ) {
			return "Error: $type name \"$name\" contains more than one underscore in a row; this is not allowed.";
		} elseif ( preg_match( '/[\.,\-<>(){}\[\]\\\\\/]/', $name ) ) {
			return "Error: $type name \"$name\" cannot contain any of the following characters: .,-<>(){}[]\/";
		} elseif ( in_array( strtolower( $name ), self::$sqlReservedWords ) ) {
			return "Error: \"$name\" cannot be used as a Cargo $type name, because it is an SQL keyword.";
		} elseif ( in_array( strtolower( $name ), self::$cargoReservedWords ) ) {
			return "Error: \"$name\" cannot be used as a Cargo $type name, because it is already a Cargo keyword.";
		}
		return null;
	}

	/**
	 * Handles the #cargo_declare parser function.
	 *
	 * @todo Internationalize error messages
	 * @param Parser $parser
	 * @return string
	 */
	public static function run( Parser $parser ) {
		if ( !$parser->getTitle() || $parser->getTitle()->getNamespace() != NS_TEMPLATE ) {
			return CargoUtils::formatError( "Error: #cargo_declare must be called from a template page." );
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tableName = null;
		$parentTables = [];
		$drilldownTabsParams = [];
		$tableSchema = new CargoTableSchema();
		$hasStartEvent = false;
		$hasEndEvent = false;
		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == '_table' ) {
				$tableName = $value;
				if ( in_array( strtolower( $tableName ), self::$sqlReservedWords ) ) {
					return CargoUtils::formatError( "Error: \"$tableName\" cannot be used as a Cargo table name, because it is an SQL keyword." );
				} elseif ( in_array( strtolower( $tableName ), self::$cargoReservedWords ) ) {
					return CargoUtils::formatError( "Error: \"$tableName\" cannot be used as a Cargo table name, because it is already a Cargo keyword." );
				}
			} elseif ( $key == '_parentTables' ) {
				$tables = explode( ';', $value );
				foreach ( $tables as $table ) {
					$parentTable = [];
					$parentTableAlias = '';
					$foundMatch = preg_match( '/([^(]*)\s*\((.*)\)/s', $table, $matches );
					if ( $foundMatch ) {
						$parentTableName = trim( $matches[1] );
						if ( count( $matches ) >= 2 ) {
							$extraParams = explode( ',', $matches[2] );
							foreach ( $extraParams as $extraParam ) {
								if ( $extraParam ) {
									$extraParamParts = explode( '=', $extraParam, 2 );
									$extraParamKey = trim( $extraParamParts[0] );
									$extraParamValue = trim( $extraParamParts[1] );
									if ( $extraParamKey == '_localField' ) {
										$parentTable['_localField'] = $extraParamValue;
									} elseif ( $extraParamKey == '_remoteField' ) {
										$parentTable['_remoteField'] = $extraParamValue;
									} elseif ( $extraParamKey == '_alias' ) {
										$parentTableAlias = $extraParamValue;
									}
								}
							}
						}
					} else {
						$parentTableName = trim( $table );
					}
					if ( $parentTableName ) {
						if ( !$parentTableAlias ) {
							if ( array_key_exists( '_localField', $parentTable ) &&
								 $parentTable['_localField'] != '_pageName' ) {
								$parentTableAlias = strtolower( $parentTable['_localField'] );
								if ( array_key_exists( $parentTableAlias, $parentTables ) ) {
									$count =
										substr_count( implode( ',', array_keys( $parentTables ) ),
											$parentTableAlias );
									$parentTableAlias .= "_$count";
								}
							} else {
								$parentTableAlias = CargoUtils::makeDifferentAlias( $parentTableName );
								if ( array_key_exists( $parentTableAlias, $parentTables ) ) {
									$count =
										substr_count( implode( ',', array_keys( $parentTables ) ),
											$parentTableAlias );
									$parentTableAlias .= "_$count";
								}
							}
						}
						if ( !array_key_exists( '_localField', $parentTable ) ) {
							$parentTable['_localField'] = '_pageName';
						}
						if ( !array_key_exists( '_remoteField', $parentTable ) ) {
							$parentTable['_remoteField'] = '_pageName';
						}
						$parentTable['Name'] = $parentTableName;
						$parentTables[$parentTableAlias] = $parentTable;

						// Validate the parent table's name.
						$validationError = self::validateFieldOrTableName( $parentTableName, 'parent table' );
						if ( $validationError !== null ) {
							return CargoUtils::formatError( $validationError );
						}
					}
				}
			} elseif ( $key == '_drilldownTabs' ) {
				$value = CargoUtils::smartSplit( ',', $value );
				foreach ( $value as $tabValues ) {
					$foundMatch = preg_match_all( '/([^(]*)\s*\(?([^)]*)\)?/s', $tabValues, $matches );
					if ( $foundMatch == false ) {
						continue;
					}
					$tabName = $matches[1][0];
					$tabName = trim( $tabName );
					if ( !$tabName ) {
						continue;
					}
					$extraParams = $matches[2][0];
					$params = preg_split( '~(?<!\\\)' . preg_quote( ';', '~' ) . '~', $extraParams );
					$tabParams = [];
					foreach ( $params as $param ) {
						if ( !$param ) {
							continue;
						}
						$param = array_map( 'trim', explode( '=', $param, 2 ) );
						if ( $param[0] == 'fields' ) {
							$fields = array_map( 'trim', explode( ',', $param[1] ) );
							$drilldownFields = [];
							foreach ( $fields as $field ) {
								$field = array_map( 'trim', explode( '=', $field ) );
								if ( count( $field ) == 2 ) {
									$drilldownFields[$field[1]] = $field[0];
								} else {
									$drilldownFields[] = $field[0];
								}
							}
							$tabParams[$param[0]] = $drilldownFields;
						} else {
							$tabParams[$param[0]] = $param[1];
						}
					}
					if ( !array_key_exists( 'format', $tabParams ) ) {
						$tabParams['format'] = 'category';
					}
					if ( !array_key_exists( 'fields', $tabParams ) ) {
						$tabParams['fields'] = [ 'Title' => '_pageName' ];
					}
					$drilldownTabsParams[$tabName] = $tabParams;
				}
			} else {
				$fieldName = $key;
				$fieldDescriptionStr = $value;
				// Validate field name.
				$validationError = self::validateFieldOrTableName( $fieldName, 'field' );
				if ( $validationError !== null ) {
					return CargoUtils::formatError( $validationError );
				}

				try {
					$fieldDescription = CargoFieldDescription::newFromString( $fieldDescriptionStr );
				} catch ( Exception $e ) {
					return CargoUtils::formatError( $e->getMessage() );
				}
				if ( $fieldDescription == null ) {
					return CargoUtils::formatError( "Error: could not parse type for field \"$fieldName\"." );
				}
				if ( $fieldDescription->mIsHierarchy == true && $fieldDescription->mType == 'Coordinates' ) {
					return CargoUtils::formatError( "Error: Hierarchy enumeration field cannot be created for $fieldDescription->mType type." );
				}
				if ( $fieldDescription->mType == "Start date" || $fieldDescription->mType == "Start datetime" ) {
					if ( !$hasStartEvent ) {
						$hasStartEvent = true;
					} else {
						return CargoUtils::formatError( "Error: There can be only one Start date/datetime type field." );
					}
				}
				if ( $fieldDescription->mType == "End date" || $fieldDescription->mType == "End datetime" ) {
					if ( !$hasEndEvent && $hasStartEvent ) {
						$hasEndEvent = true;
					} elseif ( !$hasStartEvent ) {
						// if End date/datetime is declared before Start date/datetime type field
						return CargoUtils::formatError( "Error: End date/datetime type must be declared after declaring Start date/datetime type field." );
					} else {
						return CargoUtils::formatError( "Error: There can be only one End date/datetime type field." );
					}
				}
				$tableSchema->mFieldDescriptions[$fieldName] = $fieldDescription;
			}
		}

		// Validate table name.
		if ( $tableName == '' ) {
			return CargoUtils::formatError( wfMessage( "cargo-notable" )->parse() );
		}
		$validationError = self::validateFieldOrTableName( $tableName, 'table' );
		if ( $validationError !== null ) {
			return CargoUtils::formatError( $validationError );
		}
		$cdb = CargoUtils::getDB();
		foreach ( $parentTables as $parentTableAlias => $extraParams ) {
			if ( !$cdb->tableExists( $extraParams['Name'] ) ) {
				return CargoUtils::formatError( "Error: Parent table \"$parentTable\" doesn't exist." );
			}
		}
		$parserOutput = $parser->getOutput();

		$parserOutput->setProperty( 'CargoTableName', $tableName );
		$parserOutput->setProperty( 'CargoParentTables', serialize( $parentTables ) );
		$parserOutput->setProperty( 'CargoDrilldownTabsParams', serialize( $drilldownTabsParams ) );
		$parserOutput->setProperty( 'CargoFields', $tableSchema->toDBString() );

		// Link to the Special:CargoTables page for this table, if it
		// exists already - otherwise, explain that it needs to be
		// created.
		$text = wfMessage( 'cargo-definestable', $tableName )->text();
		$cdb = CargoUtils::getDB();
		if ( $cdb->tableExists( $tableName ) ) {
			$ct = SpecialPage::getTitleFor( 'CargoTables' );
			$pageName = $ct->getPrefixedText() . "/$tableName";
			$viewTableMsg = wfMessage( 'cargo-cargotables-viewtablelink' )->parse();
			$text .= " [[$pageName|$viewTableMsg]].";
		} else {
			$text .= ' ' . wfMessage( 'cargo-tablenotcreated' )->text();
		}

		// Also link to the replacement table, if it exists.
		if ( $cdb->tableExists( $tableName . '__NEXT' ) ) {
			$text .= ' ' . wfMessage( "cargo-cargotables-replacementgenerated" )->parse();
			$ctPage = CargoUtils::getSpecialPage( 'CargoTables' );
			$ctURL = $ctPage->getPageTitle()->getFullURL();
			$viewURL = $ctURL . '/' . $tableName;
			$viewURL .= strpos( $viewURL, '?' ) ? '&' : '?';
			$viewURL .= "_replacement";
			$viewReplacementTableMsg = wfMessage( 'cargo-cargotables-viewreplacementlink' )->parse();
			$text .= "; [$viewURL $viewReplacementTableMsg].";
		}

		return $text;
	}

}
