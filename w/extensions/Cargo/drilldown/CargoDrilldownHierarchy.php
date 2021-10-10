<?php
/**
 * A class for counting the occurences of hierarchy field values in the Cargo tables for displaying in drilldown.
 *
 * @author Feroz Ahmad
 * @ingroup Cargo
 */

class CargoDrilldownHierarchy extends CargoHierarchyTree {
	public $mWithinTreeMatchCount = 0;
	public $mExactRootMatchCount = 0;

	public static function computeNodeCountByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters,
			$mainTableAlias = null, $tableNames = [], $joinConds = [] ) {
		$cdb = CargoUtils::getDB();
		list( $tableNames, $conds, $joinConds ) = $f->getQueryParts( $fullTextSearchTerm,
			$appliedFilters, $tableNames, $joinConds );
		if ( $f->fieldDescription->mIsList ) {
			$fieldTableName = $f->tableName . '__' . $f->name;
			$fieldTableAlias = $f->tableAlias . '__' . $f->name;
			$countColumnName = $cdb->addIdentifierQuotes( $mainTableAlias ) . '.' . $cdb->addIdentifierQuotes( '_pageID' );
			if ( !array_key_exists( $fieldTableAlias, $tableNames ) ) {
				$tableNames[$fieldTableAlias] = $fieldTableName;
			}
			$fieldColumnName = '_value';
			if ( !array_key_exists( $fieldTableAlias, $joinConds ) ) {
				$joinConds[$fieldTableAlias] = CargoUtils::joinOfMainAndFieldTable( $cdb,
					[ $f->tableAlias => $f->tableName ], [ $fieldTableAlias => $fieldTableName ] );
			}
		} else {
			$fieldColumnName = $f->name;
			$fieldTableName = $f->tableName;
			$fieldTableAlias = $f->tableAlias;
			$countColumnName = $cdb->addIdentifierQuotes( $mainTableAlias ) . '.' . $cdb->addIdentifierQuotes( '_pageID' );
		}

		$countClause = "COUNT(DISTINCT($countColumnName)) AS total";

		$hierarchyTableName = $f->tableName . '__' . $f->name . '__hierarchy';
		$hierarchyTableAlias = $f->tableAlias . '__' . $f->name . '__hierarchy';
		if ( !array_key_exists( $hierarchyTableAlias, $tableNames ) ) {
			$tableNames[$hierarchyTableAlias] = $hierarchyTableName;
		}

		if ( !array_key_exists( $hierarchyTableAlias, $joinConds ) ) {
			$joinConds[$hierarchyTableAlias] =
				CargoUtils::joinOfSingleFieldAndHierarchyTable( $cdb,
					[ $fieldTableAlias => $fieldTableName ], $fieldColumnName,
					[ $hierarchyTableAlias => $hierarchyTableName ] );
		}
		$withinTreeHierarchyConds = [];
		$exactRootHierarchyConds = [];
		$withinTreeHierarchyConds[] = "$hierarchyTableAlias._left >= $node->mLeft";
		$withinTreeHierarchyConds[] = "$hierarchyTableAlias._right <= $node->mRight";
		$exactRootHierarchyConds[] = "$hierarchyTableAlias._left = $node->mLeft";
		// within hierarchy tree value count
		$res = $cdb->select( $tableNames, [ $countClause ], array_merge( $conds, $withinTreeHierarchyConds ),
			null, null, $joinConds );
		$row = $cdb->fetchRow( $res );
		$node->mWithinTreeMatchCount = $row['total'];
		$cdb->freeResult( $res );
		// exact hierarchy node value count
		$res = $cdb->select( $tableNames, [ $countClause ], array_merge( $conds, $exactRootHierarchyConds ),
			null, null, $joinConds );
		$row = $cdb->fetchRow( $res );
		$node->mExactRootMatchCount = $row['total'];
		$cdb->freeResult( $res );
	}

	/**
	 * Fill up (set the value) the count data members of nodes of the tree represented by node used
	 * for calling this function. Also return an array of distinct values of the field and their counts.
	 */
	public static function computeNodeCountForTreeByFilter( $node, $f, $fullTextSearchTerm,
			$appliedFilters, $mainTableName = null, $tableNames = [], $joinConds = [] ) {
		$filter_values = [];
		$stack = new SplStack();
		// preorder traversal of the tree
		$stack->push( $node );
		while ( !$stack->isEmpty() ) {
			/** @var CargoDrilldownHierarchy $node */
			$node = $stack->pop();
			self::computeNodeCountByFilter( $node, $f, $fullTextSearchTerm, $appliedFilters,
				$mainTableName, $tableNames, $joinConds );
			if ( $node->mLeft !== 1 ) {
				// check if its not __pseudo_root__ node, then only add count
				$filter_values[$node->mRootValue] = $node->mWithinTreeMatchCount;
			}
			if ( count( $node->mChildren ) > 0 ) {
				if ( $node->mLeft !== 1 ) {
					$filter_values[$node->mRootValue . " only"] = $node->mWithinTreeMatchCount;
				}
				for ( $i = count( $node->mChildren ) - 1; $i >= 0; $i-- ) {
					$stack->push( $node->mChildren[$i] );
				}
			}
		}
		return $filter_values;
	}

	/**
	 * Finds maximum permissible depth for listing values in Drilldown filter line such that total
	 * values appearing on the Filter line is less than or equal to
	 * $wgCargoMaxVisibleHierarchyDrilldownValues
	 *
	 * @param CargoHierarchyTree $node
	 * @return int
	 */
	public static function findMaxDrilldownDepth( $node ) {
		global $wgCargoMaxVisibleHierarchyDrilldownValues;
		if ( !isset( $wgCargoMaxVisibleHierarchyDrilldownValues ) || !is_int( $wgCargoMaxVisibleHierarchyDrilldownValues ) || $wgCargoMaxVisibleHierarchyDrilldownValues < 0 ) {
			return PHP_INT_MAX;
		}
		$maxDepth = 0;
		$nodeCount = 0;
		$queue = new SplQueue();
		$queue->enqueue( $node );
		$queue->enqueue( null );
		while ( !$queue->isEmpty() ) {
			/** @var CargoHierarchyTree|null $node */
			$node = $queue->dequeue();
			if ( $node === null ) {
				if ( !$queue->isEmpty() ) {
					$maxDepth++;
					$queue->enqueue( null );
				}
			} else {
				if ( count( $node->mChildren ) > 0 && $node->mExactRootMatchCount > 0 ) {
					// we will go one level deeper and print "nodevalue_only (x)" in filter line - so count it
					$nodeCount++;
				}
				foreach ( $node->mChildren as $child ) {
					if ( $child->mWithinTreeMatchCount > 0 ) {
						if ( $nodeCount >= $wgCargoMaxVisibleHierarchyDrilldownValues ) {
							break 2;
						}
						$queue->enqueue( $child );
						$nodeCount++;
					}
				}
			}
		}
		return max( 1, $maxDepth );
	}
}
