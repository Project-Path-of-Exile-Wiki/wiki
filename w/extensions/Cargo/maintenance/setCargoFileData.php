<?php

/**
 * This script populates the Cargo _fileData DB table (and possibly other
 * auxiliary tables) for all pages in the wiki.
 *
 * Usage:
 *  php setCargoFileData.php --delete --replacement
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @author Yaron Koren
 * @ingroup Maintenance
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	require_once getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php';
} else {
	require_once __DIR__ . '/../../../maintenance/Maintenance.php';
}

$maintClass = "SetCargoFileData";

class SetCargoFileData extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Cargo' );
		$this->addDescription( "Stores a set of data for each file in the wiki in one or more database tables, for use within Cargo queries." );
		$this->addOption( "delete", "Delete the file data DB table(s)", false, false );
		$this->addOption( 'replacement', 'Put all new data into a replacement table, to be switched in later' );
	}

	public function execute() {
		$createReplacement = $this->hasOption( 'replacement' );
		$fileDataTable = $createReplacement ? '_fileData__NEXT' : '_fileData';

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', [ 'field_tables', 'field_helper_tables' ],
			[ 'main_table' => $fileDataTable ] );

		$numRows = $res->numRows();
		if ( $numRows >= 0 ) {
			$row = $res->fetchRow();
			$fieldTables = unserialize( $row['field_tables'] );
			$fieldHelperTables = unserialize( $row['field_helper_tables'] );
			SpecialDeleteCargoTable::deleteTable( $fileDataTable, $fieldTables, $fieldHelperTables );
		}

		if ( $this->getOption( "delete" ) ) {
			if ( $numRows > 0 ) {
				$this->output( "\n Deleted file data table(s).\n" );
			} else {
				$this->output( "\n No file data tables found; exiting.\n" );
			}
			return;
		}

		$tableSchema = CargoFileData::getTableSchema();
		$tableSchemaString = $tableSchema->toDBString();

		$cdb = CargoUtils::getDB();
		$dbw = wfGetDB( DB_MASTER );
		CargoUtils::createCargoTableOrTables( $cdb, $dbw, $fileDataTable, $tableSchema, $tableSchemaString, -1 );

		$pages = $dbr->select( 'page', [ 'page_id' ] );

		foreach ( $pages as $page ) {
			$title = Title::newFromID( $page->page_id );
			if ( $title == null ) {
				continue;
			}
			CargoFileData::storeValuesForFile( $title, $createReplacement );
			$this->output( wfTimestamp( TS_DB ) . ' Stored file data for page "' . $title->getFullText() . "\".\n" );
		}

		$this->output( "\n Finished populating file data table(s).\n" );

		$user = User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
		if ( $numRows >= 0 ) {
			CargoUtils::logTableAction( 'recreatetable', $fileDataTable, $user );
		} else {
			CargoUtils::logTableAction( 'createtable', $fileDataTable, $user );
		}
	}

}

require_once RUN_MAINTENANCE_IF_MAIN;
