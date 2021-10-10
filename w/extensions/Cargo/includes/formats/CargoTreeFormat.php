<?php

/**
 * Class to print query results in a "tree" display, using a field that
 * defines a "parent" relationship between rows.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTreeFormat extends CargoListFormat {
	/** @var string|null */
	protected $mParentField = null;
	/** @var array|null */
	public $mFieldDescriptions;

	public static function allowedParameters() {
		return [ 'parent field' => [ 'type' => 'string' ] ];
	}

	/**
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams
	 * @return string HTML
	 * @throws MWException
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		if ( !array_key_exists( 'parent field', $displayParams ) ) {
			throw new MWException( wfMessage( "cargo-query-missingparam", "parent field", "tree" )->parse() );
		}
		$this->mParentField = str_replace( '_', ' ', trim( $displayParams['parent field'] ) );
		$this->mFieldDescriptions = $fieldDescriptions;

		// Early error-checking.
		if ( !array_key_exists( $this->mParentField, $fieldDescriptions ) ) {
			throw new MWException( wfMessage( "cargo-query-specifiedfieldmissing", $this->mParentField, "parent field" )->parse() );
		}
		if ( array_key_exists( 'isList', $fieldDescriptions[$this->mParentField] ) ) {
			throw new MWException( "Error: 'parent field' is declared to hold a list of values; "
			. "only one parent value is allowed for the 'tree' format." );
		}

		// For each result row, get the main name, the parent value,
		// and all additional display values, and add it to the tree.
		$tree = new CargoTreeFormatTree();
		foreach ( $formattedValuesTable as $queryResultsRow ) {
			$name = null;
			$parentName = null;
			$values = [];
			foreach ( $queryResultsRow as $fieldName => $value ) {
				if ( $name == null ) {
					$name = $value;
				}
				if ( $fieldName == $this->mParentField ) {
					$parentName = $value;
				} else {
					$values[$fieldName] = $value;
				}
			}
			$tree->addNode( $name, $parentName, $values );
		}

		$result = self::printTree( $tree );
		return $result;
	}

	private function printNode( $tree, $nodeName, $level ) {
		$node = $tree->getNode( $nodeName );
		$text = str_repeat( '*', $level );
		if ( $level == 1 ) {
			$text .= "$nodeName\n";
		} else {
			$text .= $this->displayRow( $node->getValues(), $this->mFieldDescriptions ) . "\n";
		}
		foreach ( $node->getChildren() as $childName ) {
			$text .= $this->printNode( $tree, $childName, $level + 1 );
		}
		return $text;
	}

	private function printTree( $tree ) {
		// Print subtree for each top-level node.
		$text = '';
		foreach ( $tree->getNodes() as $nodeName => $node ) {
			if ( $node->getParent() == null ) {
				$text .= $this->printNode( $tree, $nodeName, 1 );
			}
		}
		return $text;
	}
}
