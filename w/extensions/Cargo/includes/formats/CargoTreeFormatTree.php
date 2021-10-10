<?php

/**
 * Class to print query results in a "tree" display, using a field that
 * defines a "parent" relationship between rows.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoTreeFormatTree {

	private $mNodes = [];

	public function getNodes() {
		return $this->mNodes;
	}

	public function getNode( $nodeName ) {
		return $this->mNodes[$nodeName];
	}

	/**
	 * @param string $nodeName
	 * @param string $parentName
	 * @param array $nodeValues
	 * @throws MWException
	 */
	public function addNode( $nodeName, $parentName, $nodeValues ) {
		// Add node for child, if it's not already there.
		if ( array_key_exists( $nodeName, $this->mNodes ) ) {
			// Make sure it doesn't have more than one parent.
			$existingParent = $this->mNodes[$nodeName]->getParent();
			if ( $existingParent != null && $existingParent != $parentName ) {
				throw new MWException( "The value \"$nodeName\" cannot have more than one parent "
				. "defined for it" );
			}
		} else {
			$this->mNodes[$nodeName] = new CargoTreeFormatNode();
		}
		$this->mNodes[$nodeName]->setParent( $parentName );
		$this->mNodes[$nodeName]->setValues( $nodeValues );

		// Add node for parent, if it's not already there
		if ( !array_key_exists( $parentName, $this->mNodes ) ) {
			$this->mNodes[$parentName] = new CargoTreeFormatNode();
		}
		$this->mNodes[$parentName]->addChild( $nodeName );
	}
}
