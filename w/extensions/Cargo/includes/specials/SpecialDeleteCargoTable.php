<?php
/**
 * An interface to delete a "Cargo table", which can be one or more real
 * database tables.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class SpecialDeleteCargoTable extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'DeleteCargoTable', 'deletecargodata' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * The table being deleted here is a Cargo table, not a DB table per
	 * se - a Cargo table corresponds to a main DB table, plus
	 * potentially one or more helper tables; all need to be deleted.
	 * Also, records need to be removed from the cargo_tables and
	 * cargo_pages tables.
	 */
	public static function deleteTable( $mainTable, $fieldTables, $fieldHelperTables ) {
		$cdb = CargoUtils::getDB();
		try {
			$cdb->begin();
			foreach ( $fieldTables as $fieldTable ) {
				$cdb->dropTable( $fieldTable );
			}
			if ( is_array( $fieldHelperTables ) ) {
				foreach ( $fieldHelperTables as $fieldHelperTable ) {
					$cdb->dropTable( $fieldHelperTable );
				}
			}
			$cdb->dropTable( $mainTable );
			$cdb->commit();
		} catch ( Exception $e ) {
			throw new MWException( "Caught exception ($e) while trying to drop Cargo table. "
			. "Please make sure that your database user account has the DROP permission." );
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'cargo_tables', [ 'main_table' => $mainTable ] );
		$dbw->delete( 'cargo_pages', [ 'table_name' => $mainTable ] );
	}

	public function execute( $subpage = false ) {
		$this->checkPermissions();

		$out = $this->getOutput();
		$req = $this->getRequest();

		$out->enableOOUI();

		$this->setHeaders();
		if ( $subpage == '' ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-notable" ) );
			return true;
		}

		$replacementTable = $req->getCheck( '_replacement' );
		$origTableName = $subpage;
		if ( $replacementTable ) {
			$tableName = $subpage . '__NEXT';
		} else {
			$tableName = $subpage;
		}

		// Make sure that this table exists.
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'cargo_tables', [ 'main_table', 'field_tables', 'field_helper_tables' ],
			[ 'main_table' => $tableName ] );
		if ( $res->numRows() == 0 ) {
			CargoUtils::displayErrorMessage( $out, $this->msg( "cargo-unknowntable", $tableName ) );
			return true;
		}

		$ctPage = CargoUtils::getSpecialPage( 'CargoTables' );
		$row = $res->fetchRow();
		$fieldTables = unserialize( $row['field_tables'] );
		$fieldHelperTables = unserialize( $row['field_helper_tables'] );

		if ( $this->getRequest()->getCheck( 'delete' ) ) {
			self::deleteTable( $tableName, $fieldTables, $fieldHelperTables );
			$text = Html::element( 'p', null, $this->msg( 'cargo-deletetable-success', $tableName )->parse() ) . "\n";
			$tablesLink = CargoUtils::makeLink( $this->getLinkRenderer(),
				$ctPage->getPageTitle(),
				htmlspecialchars( $ctPage->getDescription() ) );
			$text .= Html::rawElement( 'p', null, $this->msg( 'returnto', $tablesLink )->text() );
			$out->addHTML( $text );
			if ( !$replacementTable ) {
				CargoUtils::logTableAction( 'deletetable', $tableName, $this->getUser() );
			}
			return true;
		}

		$ctURL = $ctPage->getPageTitle()->getFullURL();
		$tableLink = "[$ctURL/$origTableName $origTableName]";

		if ( $replacementTable ) {
			$replacementTableURL = "$ctURL/$origTableName";
			$replacementTableURL .= ( strpos( $replacementTableURL, '?' ) ) ? '&' : '?';
			$replacementTableURL .= '_replacement';
			$text = Html::rawElement( 'p',
				[ 'class' => 'plainlinks' ],
				$this->msg( 'cargo-deletetable-replacementconfirm', $replacementTableURL, $tableLink )->parse()
			);
		} else {
			$text = Html::rawElement( 'p',
				[ 'class' => 'plainlinks' ],
				$this->msg( 'cargo-deletetable-confirm', $tableLink )->parse()
			);
		}
		$out->addHTML( $text );

		$htmlForm = HTMLForm::factory( 'ooui', [], $this->getContext() );

		if ( $replacementTable ) {
			$htmlForm = $htmlForm->addHiddenField( '_replacement', '' );
		}

		$htmlForm
			->setSubmitName( 'delete' )
			->setSubmitTextMsg( 'delete' )
			->setSubmitDestructive()
			->prepareForm()
			->displayForm( false );

		return true;
	}
}
