<?php

/**
 * Class to print query results in a "tree" display, using a field that
 * defines a "parent" relationship between rows.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTreeFormatNode {

	private $mParent;
	private $mChildren = [];
	private $mValues = [];

	public function getParent() {
		return $this->mParent;
	}

	public function setParent( $parent ) {
		$this->mParent = $parent;
	}

	public function getChildren() {
		return $this->mChildren;
	}

	public function addChild( $child ) {
		$this->mChildren[] = $child;
	}

	public function getValues() {
		return $this->mValues;
	}

	public function setValues( $values ) {
		$this->mValues = $values;
	}
}
