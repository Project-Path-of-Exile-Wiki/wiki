<?php
/**
 * Class for the #cargo_attach parser function.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoAttach {

	/**
	 * Handles the #cargo_attach parser function.
	 *
	 * @param Parser $parser
	 * @return string Wikitext
	 */
	public static function run( Parser $parser ) {
		if ( !$parser->getTitle() || $parser->getTitle()->getNamespace() != NS_TEMPLATE ) {
			return CargoUtils::formatError( "Error: #cargo_attach must be called from a template page." );
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		$tableName = null;
		foreach ( $params as $param ) {
			$parts = explode( '=', $param, 2 );

			if ( count( $parts ) != 2 ) {
				continue;
			}
			$key = trim( $parts[0] );
			$value = trim( $parts[1] );
			if ( $key == '_table' ) {
				$tableName = $value;
			}
		}

		// Validate table name.
		if ( $tableName == '' ) {
			return CargoUtils::formatError( wfMessage( "cargo-notable" )->parse() );
		}

		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'cargo_tables', 'COUNT(*) AS total', [ 'main_table' => $tableName ] );
		$row = $dbw->fetchRow( $res );
		if ( !empty( $row ) && $row['total'] == 0 ) {
			return CargoUtils::formatError( "Error: The specified table, \"$tableName\", does not exist." );
		}

		$parserOutput = $parser->getOutput();
		$parserOutput->setProperty( 'CargoAttachedTable', $tableName );

		// Link to the Special:ViewTable page for this table, and to the template that
		// declares it.
		$declaringTemplateID = CargoUtils::getTemplateIDForDBTable( $tableName );
		$declaringTemplateTitle = Title::newFromID( $declaringTemplateID );
		if ( $declaringTemplateTitle !== null ) {
			$declaringTemplateLink = '[[' . $declaringTemplateTitle->getFullText() . '|' .
				$declaringTemplateTitle->getText() . ']]';
			$text = wfMessage( 'cargo-addsrows', $tableName, $declaringTemplateLink )->text();
		} else {
			$text = wfMessage( 'cargo-addsrows-missingdeclare', $tableName )->text();
		}
		$ct = SpecialPage::getTitleFor( 'CargoTables' );
		$pageName = $ct->getPrefixedText() . "/$tableName";
		$viewTableMsg = wfMessage( 'cargo-cargotables-viewtablelink' )->parse();
		$text .= " [[$pageName|$viewTableMsg]].";

		return $text;
	}

}
