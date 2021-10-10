<?php

/**
 * Static functions for dealing with the "_bpmnData" table, which stores data
 * produced by the Flex Diagrams extension.
 *
 * @author Yaron Koren
 */
class CargoBPMNData {

	/**
	 * Set the schema.
	 */
	public static function getTableSchema() {
		$fieldTypes = [];
		$fieldTypes['_BPMNID'] = [ 'String', false ];
		$fieldTypes['_name'] = [ 'String', false ];
		$fieldTypes['_type'] = [ 'String', false ];
		$fieldTypes['_connectsTo'] = [ 'String', true ];
		$fieldTypes['_annotation'] = [ 'Text', false ];

		$tableSchema = new CargoTableSchema();
		foreach ( $fieldTypes as $field => $fieldVals ) {
			list( $type, $isList ) = $fieldVals;
			$fieldDesc = new CargoFieldDescription();
			$fieldDesc->mType = $type;
			if ( $isList ) {
				$fieldDesc->mIsList = true;
				$fieldDesc->setDelimiter( '|' );
			}
			$tableSchema->mFieldDescriptions[$field] = $fieldDesc;
		}

		return $tableSchema;
	}

	public static function storeBPMNValues( $title ) {
		if ( $title == null ) {
			return;
		}

		// If there is no _bpmnData table, getTableSchemas() will
		// throw an error.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( [ '_bpmnData' ] );
		} catch ( MWException $e ) {
			return;
		}

		if ( class_exists( 'MediaWiki\Revision\SlotRecord' ) ) {
			// MW 1.32+
			$revisionRecord = MediaWiki\MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
			$role = MediaWiki\Revision\SlotRecord::MAIN;
			$pageText = $revisionRecord->getContent( $role )->getNativeData();
		} else {
			$revision = Revision::newFromTitle( $title );
			$pageText = $revision->getContent()->getNativeData();
		}
		$xml = new SimpleXMLElement( $pageText );

		$allBPMNValues = [];
		$annotations = [];
		$associations = [];
		foreach ( $xml->children( 'bpmn', true ) as $key => $value ) {
			if ( $key == 'process' ) {
				foreach ( $value->children( 'bpmn', true ) as $k2 => $v2 ) {
					if ( in_array( $k2, [ 'task', 'exclusiveGateway', 'sequenceFlow', 'startEvent' ] ) ) {
						$bpmnValues = [ '_type' => $k2, '_connectsTo' => [] ];
						foreach ( $v2->attributes() as $ak1 => $av1 ) {
							if ( $ak1 == 'id' ) {
								$bpmnValues['_BPMNID'] = (string)$av1;
							}
							if ( $ak1 == 'name' ) {
								$bpmnValues['_name'] = (string)$av1;
							}
						}
						foreach ( $v2->children( 'bpmn', true ) as $k3 => $v3 ) {
							if ( $k3 == 'outgoing' ) {
								$bpmnValues['_connectsTo'][] = (string)$v3;
							}
						}
						$allBPMNValues[] = $bpmnValues;
					} elseif ( $k2 == 'textAnnotation' ) {
						$curAnnotation = [];
						foreach ( $v2->attributes() as $ak1 => $av1 ) {
							if ( $ak1 == 'id' ) {
								$curAnnotation['id'] = (string)$av1;
							}
						}
						foreach ( $v2->children( 'bpmn', true ) as $k3 => $v3 ) {
							if ( $k3 == 'text' ) {
								$curAnnotation['text'] = (string)$v3;
							}
						}
						$annotations[] = $curAnnotation;
					} elseif ( $k2 == 'association' ) {
						$curAssociation = [];
						foreach ( $v2->attributes() as $ak1 => $av1 ) {
							if ( $ak1 == 'sourceRef' ) {
								$curAssociation['sourceRef'] = (string)$av1;
							} elseif ( $ak1 == 'targetRef' ) {
								$curAssociation['targetRef'] = (string)$av1;
							}
						}
						$associations[] = $curAssociation;
					}
				}
			}
		}

		foreach ( $associations as $association ) {
			// Find actual text.
			foreach ( $annotations as $annotation ) {
				if ( $association['targetRef'] == $annotation['id'] ) {
					$annotationText = $annotation['text'];
					break;
				}
			}
			if ( $annotationText == null ) {
				continue;
			}
			foreach ( $allBPMNValues as $i => $bpmnValues ) {
				if ( $association['sourceRef'] == $bpmnValues['_BPMNID'] ) {
					$allBPMNValues[$i]['_annotation'] = $annotationText;
				}
			}
		}

		foreach ( $allBPMNValues as $bpmnValues ) {
			$bpmnValues['_connectsTo'] = implode( '|', $bpmnValues['_connectsTo'] );
			CargoStore::storeAllData( $title, '_bpmnData', $bpmnValues, $tableSchemas['_bpmnData'] );
		}
	}

}
