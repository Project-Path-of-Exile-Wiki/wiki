<?php

use MediaWiki\MediaWikiServices;

/**
 * Displays an interface to let the user drill down through all Cargo data.
 *
 * Based in part on SD_BrowseData.php in the Semantic Drilldown extension.
 *
 * @author Yaron Koren
 * @author Nikhil Kumar
 * @ingroup Cargo
 */

class CargoSpecialDrilldown extends IncludableSpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'Drilldown', 'runcargoqueries' );
	}

	public function execute( $query ) {
		global $cgScriptPath, $wgCargoPageDataColumns;
		global $wgCargoFileDataColumns;

		$this->checkPermissions();

		$request = $this->getRequest();
		$out = $this->getOutput();

		if ( $this->including() ) {
			$parser = MediaWikiServices::getInstance()->getParser();
			$parser->getOutput()->updateCacheExpiry( 0 );
		}
		$this->setHeaders();
		$out->addModules( 'ext.cargo.drilldown' );
		$out->addScript( '<!--[if IE]><link rel="stylesheet" href="' . $cgScriptPath .
			'/drilldown/resources/CargoDrilldownIEFixes.css" media="screen" /><![endif]-->' );

		$queryparts = explode( '/', $query, 1 );
		$mainTable = isset( $queryparts[0] ) ? $queryparts[0] : '';

		// If no table was specified, go with the first table,
		// alphabetically.
		if ( !$mainTable ) {
			$tableNames = CargoUtils::getTables();
			if ( count( $tableNames ) == 0 ) {
				// There are no tables - just exit now.
				return 0;
			}
			$mainTable = $tableNames[0];
		}
		$parentTables = [];
		$parentTables = CargoUtils::getParentTables( $mainTable );
		$drilldownTabsParams = CargoUtils::getDrilldownTabsParams( $mainTable );
		if ( $parentTables ) {
			$parentTablesNames =
				array_map( function ( $table ) {
					return $table['Name'];
				}, $parentTables );
		}

		if ( $request->getCheck( '_replacement' ) ) {
			$mainTable .= '__NEXT';
		}

		$cdb = CargoUtils::getDB();

		// This check is necessary because getTableSchemas(), below,
		// for some reason returns a false positive when an alternate
		// capitalization of the table name is used.
		if ( !$cdb->tableExists( $mainTable ) ) {
			$out->addHTML( CargoUtils::formatError( $this->msg( "cargo-unknowntable", $mainTable )->parse() ) );
			return;
		}

		$mainTableAlias = CargoUtils::makeDifferentAlias( $mainTable );
		try {
			if ( $parentTables ) {
				$tableSchemas = CargoUtils::getTableSchemas( array_merge( [ $mainTable ],
					$parentTablesNames ) );
			} else {
				$tableSchemas = CargoUtils::getTableSchemas( [ $mainTable ] );
			}
		} catch ( MWException $e ) {
			$out->addHTML( CargoUtils::formatError( $e->getMessage() ) );
			return;
		}
		$all_filters = [];
		$fullTextSearchTerm = null;
		$searchablePages = in_array( 'fullText', $wgCargoPageDataColumns );
		$searchableFiles = false;

		$formatBy = $request->getVal( 'formatBy' );
		$format = $request->getVal( 'format' );
		if ( !$format || !$formatBy ) {
			$format = '';
			$formatBy = '';
		}

		// Get this term, whether or not this is actually a searchable
		// table; no point doing complex logic here to determine that.
		$vals_array = $request->getArray( '_search' );
		if ( $vals_array != null ) {
			$fullTextSearchTerm = $vals_array[0];
		}
		$coordsFields = [];
		$dateFields = [];
		$calendarFields = [];
		$fileFields = [];
		$dependentFieldsArray = [];
		$formatByFieldIsList = false;
		foreach ( $tableSchemas[$mainTable]->mFieldDescriptions as $fieldName => $fieldDescription ) {
			if ( $fieldName == $formatBy && $fieldDescription->mIsList ) {
				$formatByFieldIsList = true;
			}
			if ( !$fieldDescription->mIsHidden && $fieldDescription->mType == 'File' && in_array( 'fullText', $wgCargoFileDataColumns ) ) {
				$searchableFiles = true;
			}
		}
		if ( $parentTables ) {
			$tableNames =
				array_merge( [ $mainTableAlias => [ 'Name' => $mainTable ] ],
					$parentTables );
		} else {
			$tableNames = [ $mainTableAlias => [ 'Name' => $mainTable ] ];
		}
		foreach ( $tableNames as $tableAlias => $table ) {
			$tableName = $table['Name'];
			foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
				$dependentFields = [];
				foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName1 =>
						  $fieldDescription1 ) {
					$fieldDescriptionArray1 = $fieldDescription1->toDBArray();
					if ( array_key_exists( 'dependent on', $fieldDescriptionArray1 ) ) {
						if ( in_array( $fieldName, $fieldDescriptionArray1['dependent on'] ) ) {
							$dependentFields[] = $tableName . '.' . $fieldName1;
						}
					}
				}
				$dependentFieldsArray[ $tableName . '.' . $fieldName] = $dependentFields;
			}

			foreach ( $tableSchemas[$tableName]->mFieldDescriptions as $fieldName => $fieldDescription ) {
				// Skip "hidden" fields.
				if ( $fieldDescription->mIsHidden ) {
					continue;
				}

				// Some field types shouldn't get a filter at all.
				if ( in_array( $fieldDescription->mType, [ 'Text', 'File', 'Coordinates', 'URL', 'Email', 'Wikitext', 'Wikitext string', 'Searchtext' ] ) ) {
					if ( ( $tableName == $mainTable || $drilldownTabsParams ) && $fieldDescription->mType == 'Coordinates' ) {
						$coordsFields[$tableAlias] = $fieldName;
					}
					if ( ( $tableName == $mainTable || $drilldownTabsParams ) && $fieldDescription->mType == 'File' ) {
						$fileFields = array_merge( $fileFields, [ $fieldName => $fieldDescription ] );
					}
					continue;
				}

				if ( ( $tableName == $mainTable || $drilldownTabsParams ) &&
					 ( $fieldDescription->mType == 'Date' || $fieldDescription->mType == 'Datetime' ||
					   $fieldDescription->mType == 'Start date' || $fieldDescription->mType == 'Start datetime' ) ) {
					$dateFields[] = $fieldName;
					// If no. of events is more than 4 per month (i.e average days per event < 8),
					// then calendar format is displayed for that field's result.
					if ( $cdb->tableExists( $tableName ) ) {
						if ( $fieldDescription->mIsList ) {
							$queriedTableName = $tableName . '__' . $fieldName;
							$queriedFieldName = $cdb->addIdentifierQuotes( '_value' );
						} else {
							$queriedTableName = $tableName;
							$queriedFieldName = $cdb->addIdentifierQuotes( $fieldName );
						}
						$dbType = $cdb->getType();
						if ( $dbType == 'mysql' ) {
							$daysSpanQuery = "DATEDIFF(MAX($queriedFieldName), MIN($queriedFieldName))";
						} else {
							// PostgreSQL lacks DATEDIFF().
							// @TODO - what about SQLite?
							$daysSpanQuery = "(MAX($queriedFieldName) - MIN($queriedFieldName))";
						}
						$res = $cdb->select( $queriedTableName,
							"$daysSpanQuery / COUNT(*) as avgDaysPerEvent" );
						$row = $cdb->fetchRow( $res );
						if ( $row['avgDaysPerEvent'] < 8 ) {
							$calendarFields[$fieldName] = '';
						}
					}
				}
				$all_filters[] =
					new CargoFilter( $fieldName, $tableAlias, $tableName, $fieldDescription,
						$searchablePages, $searchableFiles );
			}
		}
		if ( $searchableFiles ) {
			$numResultsPerPage = 100;
		} else {
			$numResultsPerPage = 250;
		}
		if ( method_exists( $request, 'getLimitOffsetForUser' ) ) {
			// MW 1.35+
			list( $limit, $offset ) = $request->getLimitOffsetForUser(
				$this->getUser(),
				$numResultsPerPage,
				'limit'
			);
		} else {
			list( $limit, $offset ) = $request->getLimitOffset( $numResultsPerPage, 'limit' );
		}

		$filter_used = [];
		foreach ( $all_filters as $i => $filter ) {
			$filter_used[] = false;
		}
		$applied_filters = [];
		$remaining_filters = [];
		foreach ( $all_filters as $i => $filter ) {
			if ( $parentTables && $filter->tableAlias != $mainTableAlias ) {
				$filter_name = str_replace( [ '_alias', ' ', "'" ], [ '', '_', "\'" ],
					ucfirst( $filter->tableAlias ) . '.' . $filter->name );
			} else {
				$filter_name = str_replace( [ ' ', "'" ], [ '_', "\'" ], $filter->name );
			}
			$search_terms = $request->getArray( '_search_' . $filter_name );
			$lower_date = $request->getArray( '_lower_' . $filter_name );
			$upper_date = $request->getArray( '_upper_' . $filter_name );
			$vals_array = $request->getArray( $filter_name );
			if ( $vals_array ) {
				// If it has both search_terms and normal filter values
				if ( $search_terms != null ) {
					$applied_filters[] =
						CargoAppliedFilter::create( $filter, $vals_array, $search_terms );
					$filter_used[$i] = true;
				} else {
					$applied_filters[] = CargoAppliedFilter::create( $filter, $vals_array );
					$filter_used[$i] = true;
				}
			} elseif ( $search_terms != null ) {
				$applied_filters[] = CargoAppliedFilter::create( $filter, [], $search_terms );
				$filter_used[$i] = true;
			} elseif ( $lower_date != null || $upper_date != null ) {
				$applied_filters[] = CargoAppliedFilter::create( $filter, [], null, $lower_date,
					$upper_date );
				$filter_used[$i] = true;
			}
		}
		// Add every unused filter to the $remaining_filters array,
		// unless it requires some other filter that hasn't been applied.
		foreach ( $all_filters as $i => $filter ) {
			$matched_all_required_filters = true;
			foreach ( $filter->required_filters as $required_filter ) {
				$found_match = false;
				foreach ( $applied_filters as $af ) {
					if ( $af->filter->name == $required_filter ) {
						$found_match = true;
					}
				}
				if ( !$found_match ) {
					$matched_all_required_filters = false;
					continue;
				}
			}
			if ( $matched_all_required_filters ) {
				if ( !$filter_used[$i] ) {
					$remaining_filters[] = $filter;
				}
			}
		}
		$curTabName = $request->getVal( 'tab' );
		if ( $drilldownTabsParams ) {
			if ( !$curTabName ) {
				$curTabName = key( $drilldownTabsParams );
			}
		}

		$out->addHTML( "\n\t\t\t\t<div class=\"drilldown-results\">\n" );
		$rep = new CargoDrilldownPage( $mainTable, $tableSchemas[$tableName], $parentTables,
			$drilldownTabsParams, $all_filters, $applied_filters, $remaining_filters,
			$fullTextSearchTerm, $coordsFields, $dateFields, $calendarFields, $fileFields,
			$searchablePages, $searchableFiles, $dependentFieldsArray, $offset, $limit, $format,
			$formatBy, $formatByFieldIsList, $curTabName );
		$num = $rep->execute( $query );
		$out->addHTML( "\n\t\t\t</div> <!-- drilldown-results -->\n" );

		// This has to be set last, because otherwise the QueryPage
		// code will overwrite it.
		if ( !$mainTable ) {
			$tableTitle = $this->msg( 'drilldown' )->text();
		} else {
			$tableTitle = $this->msg( 'drilldown' )->text() .
				html_entity_decode( $this->msg( 'colon-separator' )->text() ) .
				$rep->displayTableName( $mainTable );
		}
		$out->setPageTitle( $tableTitle );

		return $num;
	}

	protected function getGroupName() {
		return 'cargo';
	}
}
