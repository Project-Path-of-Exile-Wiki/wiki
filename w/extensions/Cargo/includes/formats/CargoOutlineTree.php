<?php

/**
 * A tree structure for holding the outline data.
 */
class CargoOutlineTree {
	public $mTree;
	public $mUnsortedRows;
	public $mFormattedValue;

	public function __construct( $rows = [], $formattedValue = null ) {
		$this->mTree = [];
		$this->mUnsortedRows = $rows;
		$this->mFormattedValue = $formattedValue;
	}

	public function addRow( $row ) {
		$this->mUnsortedRows[] = $row;
	}

	public function categorizeRow( $vals, $row, $formattedVals ) {
		foreach ( $vals as $val ) {
			if ( array_key_exists( $val, $this->mTree ) ) {
				$this->mTree[$val]->mUnsortedRows[] = $row;
			} else {
				$formattedVal = reset( $formattedVals );
				$this->mTree[$val] = new CargoOutlineTree( [ $row ], $formattedVal );
			}
		}
	}

	public function addField( $field ) {
		if ( count( $this->mUnsortedRows ) > 0 ) {
			foreach ( $this->mUnsortedRows as $row ) {
				$fieldValues = $row->getOutlineFieldValues( $field );
				$formattedFieldValues = $row->getFormattedOutlineFieldValues( $field );
				$this->categorizeRow( $fieldValues, $row, $formattedFieldValues );
			}
			$this->mUnsortedRows = [];
		} else {
			foreach ( $this->mTree as $i => $node ) {
				$this->mTree[$i]->addField( $field );
			}
		}
	}
}
