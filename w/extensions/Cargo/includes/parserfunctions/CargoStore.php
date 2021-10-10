<?php
/**
 * Class for the #cargo_store function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoStore {

	public static $settings = [];

	public const DATE_AND_TIME = 0;
	public const DATE_ONLY = 1;
	public const MONTH_ONLY = 2;
	public const YEAR_ONLY = 3;

	// This can be removed when support for Cargo < 3.0 is dropped.
	public const PARAMS_OPTIONAL = true;

	/**
	 * Handles the #cargo_store parser function - saves data for one
	 * template call.
	 *
	 * @global string $wgCargoDigitGroupingCharacter
	 * @global string $wgCargoDecimalMark
	 * @param Parser &$parser
	 * @throws MWException
	 */
	public static function run( &$parser, $frame, $args ) {
		// Get page-related information early on, so we can exit
		// quickly if there's a problem.
		$title = $parser->getTitle();
		$pageID = $title->getArticleID();
		if ( $pageID <= 0 ) {
			// This will most likely happen if the title is a
			// "special" page.
			wfDebugLog( 'cargo', "CargoStore::run() - skipping; not called from a wiki page.\n" );
			return;
		}

		$params = [];
		foreach ( $args as $arg ) {
			$params[] = trim( $frame->expand( $arg ) );
		}

		$tableName = null;
		$tableFieldValues = [];

		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == '_table' ) {
				$tableName = $value;
			} else {
				$fieldName = $key;
				// Since we don't know whether any empty
				// value is meant to be blank or null, let's
				// go with null.
				if ( $value == '' ) {
					$value = null;
				}
				$fieldValue = $value;
				$tableFieldValues[$fieldName] = $fieldValue;
			}
		}

		if ( $tableName == '' ) {
			$templateTitle = $frame->title;
			list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $templateTitle );
		}

		if ( $tableName == '' ) {
			return;
		}

		// Go through all the fields for this table, setting any that
		// were not explicitly set in the #cargo_store call.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( [ $tableName ] );
		} catch ( MWException $e ) {
			// Most likely, this table was never created - just exit.
			return;
		}
		$fieldDescriptions = $tableSchemas[$tableName]->mFieldDescriptions;
		$fieldNames = array_keys( $fieldDescriptions );
		foreach ( $fieldNames as $fieldName ) {
			// Skip it if it's already being handled.
			if ( array_key_exists( $fieldName, $tableFieldValues ) ) {
				continue;
			}
			// Look for a template parameter with the same name
			// as this field, both with underscores and with spaces.
			$curFieldValue = $frame->getArgument( $fieldName );
			if ( $curFieldValue == null ) {
				$unescapedFieldName = str_replace( '_', ' ', $fieldName );
				$curFieldValue = $frame->getArgument( $unescapedFieldName );
			}
			$tableFieldValues[$fieldName] = $curFieldValue;
		}

		$origTableName = $tableName;

		// Always store data in the replacement table if it exists.
		$cdb = CargoUtils::getDB();
		$cdb->begin();
		if ( $cdb->tableExists( $tableName . '__NEXT' ) ) {
			$tableName .= '__NEXT';
		}

		// Get the declaration of the table.
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'cargo_tables', 'table_schema', [ 'main_table' => $tableName ] );
		$row = $dbw->fetchRow( $res );
		if ( $row == '' ) {
			// This table probably has not been created yet -
			// just exit silently.
			wfDebugLog( 'cargo', "CargoStore::run() - skipping; Cargo table ($tableName) does not exist.\n" );
			$cdb->rollback();
			return;
		}
		$tableSchema = CargoTableSchema::newFromDBString( $row['table_schema'] );

		$errors = self::blankOrRejectBadData( $cdb, $title, $tableName, $tableFieldValues, $tableSchema );
		$cdb->commit();

		if ( $errors ) {
			$parserOutput = $parser->getOutput();
			$parserOutput->setProperty( 'CargoStorageError', $errors );
			wfDebugLog( 'cargo', "CargoStore::run() - skipping; storage error encountered.\n" );
			return;
		}

		// This function does actual DB modifications - so only proceed
		// if this is called via either a page save or a "recreate
		// data" action for a template that this page calls.
		if ( count( self::$settings ) == 0 ) {
			wfDebugLog( 'cargo', "CargoStore::run() - skipping; no settings defined.\n" );
			return;
		} elseif ( !array_key_exists( 'origin', self::$settings ) ) {
			wfDebugLog( 'cargo', "CargoStore::run() - skipping; no origin defined.\n" );
			return;
		}

		if ( self::$settings['origin'] == 'template' ) {
			// It came from a template "recreate data" action -
			// make sure it passes various criteria.
			if ( self::$settings['dbTableName'] != $origTableName ) {
				wfDebugLog( 'cargo', "CargoStore::run() - skipping; dbTableName not set.\n" );
				return;
			}
		}

		self::storeAllData( $title, $tableName, $tableFieldValues, $tableSchema );

		// Finally, add a record of this to the cargo_pages table, if
		// necessary.
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'cargo_pages', 'page_id',
			[ 'table_name' => $tableName, 'page_id' => $pageID ] );
		if ( !$dbw->fetchRow( $res ) ) {
			$dbw->insert( 'cargo_pages', [ 'table_name' => $tableName, 'page_id' => $pageID ] );
		}
	}

	/**
	 * Deal with data that is considered invalid, for one reason or
	 * another. For the most part we simply ignore the data (if it's an
	 * invalid field) or blank it (if it's an invalid value), but if it's
	 * a mandatory value, we have no choice but to reject the whole row.
	 */
	public static function blankOrRejectBadData( $cdb, $title, $tableName, &$tableFieldValues, $tableSchema ) {
		foreach ( $tableFieldValues as $fieldName => $fieldValue ) {
			if ( !array_key_exists( $fieldName, $tableSchema->mFieldDescriptions ) ) {
				unset( $tableFieldValues[$fieldName] );
			}
		}

		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !array_key_exists( $fieldName, $tableFieldValues ) ) {
				continue;
			}
			$fieldValue = $tableFieldValues[$fieldName];
			if ( $fieldDescription->mIsMandatory && $fieldValue == '' ) {
				return "Mandatory field, \"$fieldName\", cannot have a blank value.";
			}
			if ( $fieldDescription->mIsUnique && $fieldValue != '' ) {
				$res = $cdb->select( $tableName, 'COUNT(*)', [ $fieldName => $fieldValue ] );
				$row = $cdb->fetchRow( $res );
				$numExistingValues = $row['COUNT(*)'];
				if ( $numExistingValues == 1 ) {
					$rowAlreadyExists = self::doesRowAlreadyExist( $cdb, $title, $tableName, $tableFieldValues, $tableSchema );
					if ( $rowAlreadyExists ) {
						$numExistingValues = 0;
					}
				}
				if ( $numExistingValues > 0 ) {
					if ( $fieldDescription->mIsMandatory ) {
						return "Cannot store mandatory field \"$fieldName\" as it contains a duplicate value.";
					}
					$tableFieldValues[$fieldName] = null;
				}
			}
			if ( $fieldDescription->mRegex != null && !preg_match( '/^' . $fieldDescription->mRegex . '$/', $fieldValue ) ) {
				if ( $fieldDescription->mIsMandatory ) {
					return "Cannot store mandatory field \"$fieldName\" as the value does not match the field's regex constraint.";
				}
				$tableFieldValues[$fieldName] = null;
			}
		}
	}

	public static function getDateValueAndPrecision( $dateStr, $fieldType ) {
		$precision = null;

		// Special handling if it's just a year. If it's a number and
		// less than 8 digits, assume it's a year (hey, it could be a
		// very large BC year). If it's 8 digits, it's probably a full
		// date in the form YYYYMMDD.
		if ( ctype_digit( $dateStr ) && strlen( $dateStr ) < 8 ) {
			// Add a fake date - it will get ignored later.
			return [ "$dateStr-01-01", self::YEAR_ONLY ];
		}

		// Determine if there's a month but no day. There's no ideal
		// way to do this, so: we'll just look for the total number of
		// spaces, slashes and dashes, and if there's exactly one
		// altogether, we'll guess that it's a month only.
		$numSpecialChars = substr_count( $dateStr, ' ' ) +
			substr_count( $dateStr, '/' ) + substr_count( $dateStr, '-' );
		if ( $numSpecialChars == 1 ) {
			// No need to add anything - PHP will set it to the
			// first of the month.
			$precision = self::MONTH_ONLY;
		} else {
			// We have at least a full date.
			if ( $fieldType == 'Date' ) {
				$precision = self::DATE_ONLY;
			}
		}

		$seconds = strtotime( $dateStr );
		// If the precision has already been set, then we know it
		// doesn't include a time value - we can set the value already.
		if ( $precision != null ) {
			// Put into YYYY-MM-DD format.
			return [ date( 'Y-m-d', $seconds ), $precision ];
		}

		// It's a Datetime field, which may or may not have a time -
		// check for that now.
		$datePortion = date( 'Y-m-d', $seconds );
		$timePortion = date( 'G:i:s', $seconds );
		// If it's not right at midnight, there's definitely a time
		// there.
		$precision = self::DATE_AND_TIME;
		if ( $timePortion !== '0:00:00' ) {
			return [ $datePortion . ' ' . $timePortion, $precision ];
		}

		// It's midnight, so chances are good that there was no time
		// specified, but how do we know for sure?
		// Slight @HACK - look for either "00" or "AM" (or "am") in the
		// original date string. If neither one is there, there's
		// probably no time.
		if ( strpos( $dateStr, '00' ) === false &&
			strpos( $dateStr, 'AM' ) === false &&
			strpos( $dateStr, 'am' ) === false ) {
			$precision = self::DATE_ONLY;
		}
		// Either way, we just need the date portion.
		return [ $datePortion, $precision ];
	}

	public static function storeAllData( $title, $tableName, $tableFieldValues, $tableSchema ) {
		$pageID = $title->getArticleID();
		$pageName = $title->getPrefixedText();
		$pageTitle = $title->getText();
		$pageNamespace = $title->getNamespace();

		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			// If it's null or not set, skip this value.
			if ( !array_key_exists( $fieldName, $tableFieldValues ) ) {
				continue;
			}
			$curValue = $tableFieldValues[$fieldName];
			if ( $curValue === null ) {
				continue;
			}

			$valueArray = $fieldDescription->prepareAndValidateValue( $curValue );
			$tableFieldValues[$fieldName] = $valueArray['value'];
			if ( array_key_exists( 'precision', $valueArray ) ) {
				$tableFieldValues[$fieldName . '__precision'] = $valueArray['precision'];
			}
		}

		// Add the "metadata" field values.
		$tableFieldValues['_pageName'] = $pageName;
		$tableFieldValues['_pageTitle'] = $pageTitle;
		$tableFieldValues['_pageNamespace'] = $pageNamespace;
		$tableFieldValues['_pageID'] = $pageID;

		// Allow other hooks to modify the values.
		Hooks::run( 'CargoBeforeStoreData', [ $title, $tableName, &$tableSchema, &$tableFieldValues ] );

		$cdb = CargoUtils::getDB();

		// Somewhat of a @HACK - recreating a Cargo table from the web
		// interface can lead to duplicate rows, due to the use of jobs.
		// So before we store this data, check if a row with this
		// exact set of data is already in the database. If it is, just
		// ignore this #cargo_store call.
		// This is not ideal, because there can be valid duplicate
		// data - a page can have multiple calls to the same template,
		// with identical data, for various reasons. However, that's
		// a very rare case, while unwanted code duplication is
		// unfortunately a common case. So until there's a real
		// solution, this workaround will be helpful.
		$rowAlreadyExists = self::doesRowAlreadyExist( $cdb, $title, $tableName, $tableFieldValues, $tableSchema );
		if ( $rowAlreadyExists ) {
			return;
		}

		// The _position field was only added to list tables in Cargo
		// 2.1, which means that any list table last created or
		// re-created before then will not have that field. How to know
		// whether to populate that field? We go to the first list
		// table for this main table (there may be more than one), query
		// that field, and see whether it throws an exception. (We'll
		// assume that either all the list tables for this main table
		// have a _position field, or none do.)
		$hasPositionField = true;
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( $fieldDescription->mIsList ) {
				$listFieldTableName = $tableName . '__' . $fieldName;
				try {
					$res = $cdb->select( $listFieldTableName, 'COUNT(' .
						$cdb->addIdentifierQuotes( '_position' ) . ')' );
				} catch ( Exception $e ) {
					$hasPositionField = false;
				}
				break;
			}
		}

		// We put the retrieval of the row ID, and the saving of the new row, into a
		// single DB transaction, to avoid "collisions".
		$cdb->begin();
		if ( method_exists( $cdb, 'lockForUpdate' ) ) {
			// MW 1.32+
			$cdb->lockForUpdate( $tableName );
		}

		$res = $cdb->select( $tableName, 'MAX(' .
			$cdb->addIdentifierQuotes( '_ID' ) . ') AS "ID"' );
		$row = $cdb->fetchRow( $res );
		$curRowID = $row['ID'] + 1;
		$tableFieldValues['_ID'] = $curRowID;
		$fieldTableFieldValues = [];

		// For each field that holds a list of values, also add its
		// values to its own table; and rename the actual field.
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !array_key_exists( $fieldName, $tableFieldValues ) ) {
				continue;
			}
			$fieldType = $fieldDescription->mType;
			if ( $fieldDescription->mIsList ) {
				$fieldTableName = $tableName . '__' . $fieldName;
				$delimiter = $fieldDescription->getDelimiter();
				$individualValues = explode( $delimiter, $tableFieldValues[$fieldName] );
				$valueNum = 1;
				foreach ( $individualValues as $individualValue ) {
					$individualValue = trim( $individualValue );
					// Ignore blank values.
					if ( $individualValue == '' ) {
						continue;
					}
					$fieldValues = [
						'_rowID' => $curRowID,
						'_value' => $individualValue
					];
					if ( $hasPositionField ) {
						$fieldValues['_position'] = $valueNum++;
					}
					// For coordinates, there are two more
					// fields, for latitude and longitude.
					if ( $fieldType == 'Coordinates' ) {
						try {
							list( $latitude, $longitude ) = CargoUtils::parseCoordinatesString( $individualValue );
						} catch ( MWException $e ) {
							continue;
						}
						$fieldValues['_lat'] = $latitude;
						$fieldValues['_lon'] = $longitude;
					}
					// We could store these values in the DB
					// now, but we'll do it later, to keep
					// the transaction as short as possible.
					$fieldTableFieldValues[] = [ $fieldTableName, $fieldValues ];
				}

				// Now rename the field.
				$tableFieldValues[$fieldName . '__full'] = $tableFieldValues[$fieldName];
				unset( $tableFieldValues[$fieldName] );
			} elseif ( $fieldType == 'Coordinates' ) {
				try {
					list( $latitude, $longitude ) = CargoUtils::parseCoordinatesString( $tableFieldValues[$fieldName] );
				} catch ( MWException $e ) {
					unset( $tableFieldValues[$fieldName] );
					continue;
				}
				// Rename the field.
				$tableFieldValues[$fieldName . '__full'] = $tableFieldValues[$fieldName];
				unset( $tableFieldValues[$fieldName] );
				$tableFieldValues[$fieldName . '__lat'] = $latitude;
				$tableFieldValues[$fieldName . '__lon'] = $longitude;
			}
		}

		// Insert the current data into the main table.
		CargoUtils::escapedInsert( $cdb, $tableName, $tableFieldValues );

		// End transaction and apply DB changes.
		$cdb->commit();

		// Now, store the data for all the "field tables".
		foreach ( $fieldTableFieldValues as $tableNameAndValues ) {
			list( $fieldTableName, $fieldValues ) = $tableNameAndValues;
			CargoUtils::escapedInsert( $cdb, $fieldTableName, $fieldValues );
		}

		// Also insert the names of any "attached" files into the
		// "files" helper table.
		$fileTableName = $tableName . '___files';
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			$fieldType = $fieldDescription->mType;
			if ( $fieldType != 'File' ) {
				continue;
			}
			if ( $fieldDescription->mIsList ) {
				$delimiter = $fieldDescription->getDelimiter();
				$individualValues = explode( $delimiter, $tableFieldValues[$fieldName . '__full'] );
				foreach ( $individualValues as $individualValue ) {
					$individualValue = trim( $individualValue );
					// Ignore blank values.
					if ( $individualValue == '' ) {
						continue;
					}
					$fileName = CargoUtils::removeNamespaceFromFileName( $individualValue );
					$fieldValues = [
						'_pageName' => $pageName,
						'_pageID' => $pageID,
						'_fieldName' => $fieldName,
						'_fileName' => $fileName
					];
					CargoUtils::escapedInsert( $cdb, $fileTableName, $fieldValues );
				}
			} else {
				$fullFileName = $tableFieldValues[$fieldName];
				if ( $fullFileName == '' ) {
					continue;
				}
				$fileName = CargoUtils::removeNamespaceFromFileName( $fullFileName );
				$fieldValues = [
					'_pageName' => $pageName,
					'_pageID' => $pageID,
					'_fieldName' => $fieldName,
					'_fileName' => $fileName
				];
				CargoUtils::escapedInsert( $cdb, $fileTableName, $fieldValues );
			}
		}
	}

	/**
	 * Determines whether a row with the specified set of values already
	 * exists in the specified Cargo table.
	 */
	public static function doesRowAlreadyExist( $cdb, $title, $tableName, $tableFieldValues, $tableSchema ) {
		$pageID = $title->getArticleID();
		$tableFieldValuesForCheck = [ $cdb->addIdentifierQuotes( '_pageID' ) => $pageID ];
		foreach ( $tableSchema->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( !array_key_exists( $fieldName, $tableFieldValues ) ) {
				continue;
			}
			if ( $fieldDescription->mIsList || $fieldDescription->mType == 'Coordinates' ) {
				$quotedFieldName = $cdb->addIdentifierQuotes( $fieldName . '__full' );
			} else {
				$quotedFieldName = $cdb->addIdentifierQuotes( $fieldName );
			}
			$fieldValue = $tableFieldValues[$fieldName];

			if ( in_array( $fieldDescription->mType, [ 'Text', 'Wikitext', 'Searchtext' ] ) ) {
				// @HACK - for some reason, there are times
				// when, for long values, the check only works
				// if there's some kind of limit in place.
				// Rather than delve into that, we'll just
				// make sure to only check a (relatively large)
				// substring - which should be good enough.
				$fieldSize = 1000;
			} else {
				$fieldSize = $fieldDescription->getFieldSize();
			}

			if ( $fieldValue === '' ) {
				// Needed for correct SQL handling of blank values, for some reason.
				$fieldValue = null;
			} elseif ( $fieldSize != null && strlen( $fieldValue ) > $fieldSize ) {
				// In theory, this SUBSTR() call is not needed,
				// since the value stored in the DB won't be
				// greater than this size. But that's not
				// always true - there's the hack mentioned
				// above, plus some other cases.
				$quotedFieldName = "SUBSTR($quotedFieldName, 1, $fieldSize)";
				$fieldValue = mb_substr( $fieldValue, 0, $fieldSize );
			}

			$tableFieldValuesForCheck[$quotedFieldName] = $fieldValue;
		}
		$res = $cdb->select( $tableName, 'COUNT(*)', $tableFieldValuesForCheck );
		$row = $cdb->fetchRow( $res );
		return ( $row[0] > 0 );
	}
}
