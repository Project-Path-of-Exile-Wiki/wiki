<?php
/**
 * Defines a class, CargoAppliedFilter, that adds a value or a value range
 * onto a CargoFilter instance.
 *
 * Based heavily on SD_AppliedFilter.php in the Semantic Drilldown extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoAppliedFilter {
	public $filter;
	public $values = [];
	public $search_terms;
	public $lower_date;
	public $upper_date;
	public $lower_date_string;
	public $upper_date_string;

	public static function create( $filter, $values, $search_terms = null, $lower_date = null,
		$upper_date = null ) {
		$af = new CargoAppliedFilter();
		$af->filter = $filter;
		if ( $search_terms != null ) {
			$af->search_terms = [];
			foreach ( $search_terms as $search_term ) {
				$af->search_terms[] = htmlspecialchars( $search_term );
			}
		}
		if ( $lower_date != null ) {
			$af->lower_date = $lower_date;
			$af->lower_date_string = CargoDrilldownUtils::monthToString( $lower_date['month'] ) .
				" " . $lower_date['day'] . ", " . $lower_date['year'];
		}
		if ( $upper_date != null ) {
			$af->upper_date = $upper_date;
			$af->upper_date_string = CargoDrilldownUtils::monthToString( $upper_date['month'] ) .
				" " . $upper_date['day'] . ", " . $upper_date['year'];
		}
		if ( !is_array( $values ) ) {
			$values = [ $values ];
		}
		foreach ( $values as $val ) {
			$filter_val = CargoFilterValue::create( $val, $filter );
			$af->values[] = $filter_val;
		}
		return $af;
	}

	/**
	 * Returns a string that adds a check for this filter/value
	 * combination to an SQL "WHERE" clause.
	 */
	public function checkSQL() {
		$cdb = CargoUtils::getDB();

		if ( $this->filter->fieldDescription->mIsList ) {
			$fieldTableName = $this->filter->tableName . '__' . $this->filter->name;
			$fieldTableAlias = $this->filter->tableAlias . '__' . $this->filter->name;
			$value_field =
				CargoUtils::escapedFieldName( $cdb, [ $fieldTableAlias => $fieldTableName ],
					'_value' );
		} else {
			$value_field =
				CargoUtils::escapedFieldName( $cdb,
					[ $this->filter->tableAlias => $this->filter->tableName ],
					$this->filter->name );
		}
		$sql = "(";
		if ( $this->search_terms != null ) {
			$quoteReplace = ( $cdb->getType() == 'postgres' ? "''" : "\'" );
			foreach ( $this->search_terms as $i => $search_term ) {
				$search_term = str_replace( "'", $quoteReplace, $search_term );
				if ( $i > 0 ) {
					$sql .= ' OR ';
				}
				if ( $this->filter->fieldType === 'page' ) {
					// FIXME: 'LIKE' is supposed to be
					// case-insensitive, but it's not acting
					// that way here.
					// $search_term = strtolower( $search_term );
					// $search_term = str_replace( ' ', '\_', $search_term );
					$sql .= $value_field . ' ' . $cdb->buildLike( $cdb->anyString(), $search_term, $cdb->anyString() );
				} else {
					// $search_term = strtolower( $search_term );
					$sql .= $value_field . ' ' . $cdb->buildLike( $cdb->anyString(), $search_term, $cdb->anyString() );
				}
			}
		}
		if ( $this->lower_date != null ) {
			$date_string = $this->lower_date['year'] . "-" . $this->lower_date['month'] . "-" .
				$this->lower_date['day'];
			$sql .= "date($value_field) >= date('$date_string') ";
		}
		if ( $this->upper_date != null ) {
			if ( $this->lower_date != null ) {
				$sql .= " AND ";
			}
			$date_string = $this->upper_date['year'] . "-" . $this->upper_date['month'] . "-" .
				$this->upper_date['day'];
			$sql .= "date($value_field) <= date('$date_string') ";
		}
		foreach ( $this->values as $i => $fv ) {
			// Add an OR if filter also has search terms in addition to normal filter values
			if ( $this->search_terms != null && $i == 0 ) {
				$sql .= " OR ";
			}
			if ( $i > 0 ) {
				$sql .= " OR ";
			}
			if ( $fv->is_other ) {
				$checkNullOrEmptySql = "$value_field IS NULL " . ( $cdb->getType() == 'postgres' ? '' :
						"OR $value_field = '' " );
				$notOperatorSql = ( $cdb->getType() == 'postgres' ? "not" : "!" );
				$sql .= "($notOperatorSql ($checkNullOrEmptySql ";
				foreach ( $this->filter->possible_applied_filters as $paf ) {
					$sql .= " OR " . $paf->checkSQL();
				}
				$sql .= "))";
			} elseif ( $this->filter->fieldDescription->mIsHierarchy && preg_match( "/^~within (.+)/", $fv->text ) ) {
				$matches = [];
				if ( preg_match( "/^~within (.+)/", $fv->text, $matches ) ) {
					$value = $matches[1];
					$hierarchyTableName = $this->filter->tableName . '__' . $this->filter->name . '__hierarchy';
					$hierarchyTableAlias = $this->filter->tableAlias . '__' . $this->filter->name . '__hierarchy';
					$drilldownHierarchyRoot =
						CargoDrilldownHierarchy::newFromWikiText( $this->filter->fieldDescription->mHierarchyStructure );
					$stack = new SplStack();
					// preorder traversal of the tree
					$stack->push( $drilldownHierarchyRoot );
					while ( !$stack->isEmpty() ) {
						/** @var CargoHierarchyTree $node */
						$node = $stack->pop();
						if ( $node->mRootValue === $value ) {
							$drilldownHierarchyRoot = $node;
							break;
						}
						for ( $i = count( $node->mChildren ) - 1; $i >= 0; $i-- ) {
							$stack->push( $node->mChildren[$i] );
						}
					}
					$leftCond = " $hierarchyTableAlias._left >= $node->mLeft";
					$rightCond = " $hierarchyTableAlias._right <= $node->mRight";
					$sql .= "( (" . $leftCond . ") AND (" . $rightCond . ") )";
				}
			} elseif ( $fv->is_none ) {
				// For some reason, 0 values are treated as
				// blank, so we need the "!= 0" check.
				$checkNullOrEmptySql = ( $cdb->getType() == 'postgres' ? '' : "($value_field = '' AND $value_field != 0) OR " ) .
					"$value_field IS NULL";
				$sql .= "($checkNullOrEmptySql) ";
			} elseif ( $this->filter->fieldDescription->isDateOrDatetime() ) {
				if ( $this->filter->fieldDescription->mIsList ) {
					$dateFieldTableAlias = $this->filter->tableAlias . '__' . $this->filter->name;
					$date_field = $cdb->addIdentifierQuotes( $dateFieldTableAlias ) . '._value';
				} else {
					$date_field = $cdb->addIdentifierQuotes( $this->filter->tableAlias ) . '.' . $cdb->addIdentifierQuotes( $this->filter->name );
				}
				list( $yearValue, $monthValue, $dayValue ) = CargoUtils::getDateFunctions( $date_field );
				if ( $fv->time_period == 'day' ) {
					$sql .= "$yearValue = {$fv->year} AND $monthValue = {$fv->month} AND $dayValue = {$fv->day} ";
				} elseif ( $fv->time_period == 'month' ) {
					$sql .= "$yearValue = {$fv->year} AND $monthValue = {$fv->month} ";
				} elseif ( $fv->time_period == 'year' ) {
					$sql .= "$yearValue = {$fv->year} ";
				} else { // if ( $fv->time_period == 'year range' ) {
					$sql .= "$yearValue >= {$fv->year} AND $yearValue <= {$fv->end_year} ";
				}
			} elseif ( $fv->is_numeric ) {
				if ( $fv->lower_limit && $fv->upper_limit ) {
					$sql .= "($value_field >= {$fv->lower_limit} AND $value_field <= {$fv->upper_limit}) ";
				} elseif ( $fv->lower_limit ) {
					$sql .= "$value_field > {$fv->lower_limit} ";
				} elseif ( $fv->upper_limit ) {
					$sql .= "$value_field < {$fv->upper_limit} ";
				}
			} else {
				$value = $fv->text;
				$sql .= "$value_field = '{$cdb->strencode( $value )}'";
			}
		}
		$sql .= ")";
		return $sql;
	}

	public function getQueryParts( $mainTableName ) {
		$cdb = CargoUtils::getDB();

		$tableNames = [];
		$conds = [];
		$joinConds = [];
		$fieldTableName = $this->filter->tableName;
		$fieldTableAlias = $this->filter->tableAlias;
		$fieldName = $this->filter->name;

		$conds[] = $this->checkSQL();

		if ( $this->filter->fieldDescription->mIsList ) {
			$fieldTableName = $this->filter->tableName . '__' . $this->filter->name;
			$fieldTableAlias = $this->filter->tableAlias . '__' . $this->filter->name;
			$fieldName = '_value';
			$tableNames[$fieldTableAlias] = $fieldTableName;
			$joinConds[$fieldTableAlias] =
				CargoUtils::joinOfMainAndFieldTable( $cdb,
					[ $this->filter->tableAlias => $this->filter->tableName ],
					[ $fieldTableAlias => $fieldTableName ] );
		}

		if ( $this->filter->fieldDescription->mIsHierarchy ) {
			$hierarchyTableName = $this->filter->tableName . '__' . $this->filter->name . '__hierarchy';
			$hierarchyTableAlias = $this->filter->tableAlias . '__' . $this->filter->name . '__hierarchy';
			$tableNames[$hierarchyTableAlias] = $hierarchyTableName;
			$joinConds[$hierarchyTableAlias] =
				CargoUtils::joinOfSingleFieldAndHierarchyTable( $cdb,
					[ $fieldTableAlias => $fieldTableName ], $fieldName, [
						$hierarchyTableAlias => $hierarchyTableName,
					] );
		}

		return [ $tableNames, $conds, $joinConds ];
	}

	/**
	 * Gets an array of all values that this filter has.
	 */
	public function getAllOrValues() {
		$possible_values = [];
		if ( $this->filter->fieldDescription->mIsList ) {
			$tableName = $this->filter->tableName . '__' . $this->filter->name;
			$tableAlias = $this->filter->tableAlias . '__' . $this->filter->name;
			$value_field = '_value';
		} else {
			$tableName = $this->filter->tableName;
			$tableAlias = $this->filter->tableAlias;
			$value_field = $this->filter->name;
		}
		$table = [ $tableAlias => $tableName ];

		$cdb = CargoUtils::getDB();
		$res = $cdb->select( $table, "DISTINCT " . $cdb->addIdentifierQuotes( $value_field ) );
		foreach ( $res as $row ) {
			$possible_values[] = $row->$value_field;
		}
		return $possible_values;
	}

}
