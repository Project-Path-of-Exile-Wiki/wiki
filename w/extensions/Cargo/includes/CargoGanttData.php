<?php

/**
 * Static functions for dealing with the "_ganttData" table, which stores data
 * produced by the Flex Diagrams extension.
 *
 * @author Yaron Koren
 */
class CargoGanttData {

	/**
	 * Set the schema.
	 */
	public static function getTableSchema() {
		$fieldTypes = [];
		$fieldTypes['_localID'] = [ 'String', false ];
		$fieldTypes['_name'] = [ 'String', false ];
		$fieldTypes['_startDate'] = [ 'Date', false ];
		$fieldTypes['_endDate'] = [ 'Date', false ];
		$fieldTypes['_progress'] = [ 'Float', false ];
		$fieldTypes['_parent'] = [ 'String', false ];
		$fieldTypes['_linksToBB'] = [ 'String', true ];
		$fieldTypes['_linksToBF'] = [ 'String', true ];
		$fieldTypes['_linksToFB'] = [ 'String', true ];
		$fieldTypes['_linksToFF'] = [ 'String', true ];

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

	public static function storeGanttValues( $title ) {
		if ( $title == null ) {
			return;
		}

		// If there is no _ganttData table, getTableSchemas() will
		// throw an error.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( [ '_ganttData' ] );
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

		$data = json_decode( $pageText );

		$allGanttValues = [];
		foreach ( $data->data as $task ) {
			$ganttValues = [
				'_localID' => $task->id,
				'_name' => $task->text,
				'_startDate' => $task->start_date,
				'_endDate' => $task->end_date,
				'_progress' => $task->progress
			];
			if ( $task->parent != 0 ) {
				$ganttValues['_parent'] = $task->parent;
			}
			$allGanttValues[] = $ganttValues;
		}

		foreach ( $data->links as $link ) {
			$sourceID = $link->source;
			foreach ( $allGanttValues as $i => $ganttValues ) {
				if ( $ganttValues['_localID'] == $sourceID ) {
					if ( $link->type == 0 ) {
						$allGanttValues[$i]['_linksToBB'] = $link->target;
					} elseif ( $link->type == 1 ) {
						$allGanttValues[$i]['_linksToFF'] = $link->target;
					} elseif ( $link->type == 2 ) {
						$allGanttValues[$i]['_linksToFB'] = $link->target;
					} else { // if ( $link->type == 3 ) {
						$allGanttValues[$i]['_linksToBF'] = $link->target;
					}
				}
			}
		}

		foreach ( $allGanttValues as $ganttValues ) {
			CargoStore::storeAllData( $title, '_ganttData', $ganttValues, $tableSchemas['_ganttData'] );
		}
	}

}
