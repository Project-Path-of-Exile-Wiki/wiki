<?php
/**
 * Displays an interface to let users recreate data via the Cargo
 * extension.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class SpecialCargoRecreateData extends UnlistedSpecialPage {
	public $mTemplateTitle;
	public $mTableName;
	public $mIsDeclared;

	public function __construct( $templateTitle, $tableName, $isDeclared ) {
		parent::__construct( 'RecreateData', 'recreatecargodata' );
		$this->mTemplateTitle = $templateTitle;
		$this->mTableName = $tableName;
		$this->mIsDeclared = $isDeclared;
	}

	public function execute( $query = null ) {
		global $wgScriptPath, $cgScriptPath;

		$this->checkPermissions();

		$out = $this->getOutput();
		$out->enableOOUI();
		$this->setHeaders();

		$tableExists = CargoUtils::tableFullyExists( $this->mTableName );
		if ( !$tableExists ) {
			$out->setPageTitle( $this->msg( 'cargo-createdatatable' )->parse() );
		}

		// Disable page if "replacement table" exists.
		$possibleReplacementTable = $this->mTableName . '__NEXT';
		if ( CargoUtils::tableFullyExists( $this->mTableName ) && CargoUtils::tableFullyExists( $possibleReplacementTable ) ) {
			$text = $this->msg( 'cargo-recreatedata-replacementexists', $this->mTableName, $possibleReplacementTable )->parse();
			$ctURL = SpecialPage::getTitleFor( 'CargoTables' )->getFullURL();
			$viewURL = $ctURL . '/' . $this->mTableName;
			$viewURL .= strpos( $viewURL, '?' ) ? '&' : '?';
			$viewURL .= "_replacement";
			$viewReplacementText = $this->msg( 'cargo-cargotables-viewreplacementlink' )->parse();

			$text .= ' (' . Xml::element( 'a', [ 'href' => $viewURL ], $viewReplacementText ) . ')';
			$out->addHTML( $text );
			return true;
		}

		if ( empty( $this->mTemplateTitle ) ) {
			// No template.
			// TODO - show an error message.
			return true;
		}

		$out->addModules( 'ext.cargo.recreatedata' );

		$templateData = [];
		$dbw = wfGetDB( DB_MASTER );

		$templateData[] = [
			'name' => $this->mTemplateTitle->getText(),
			'numPages' => $this->getNumPagesThatCallTemplate( $dbw, $this->mTemplateTitle )
		];

		if ( $this->mIsDeclared ) {
			// Get all attached templates.
			$res = $dbw->select( 'page_props',
				[
					'pp_page'
				],
				[
					'pp_value' => $this->mTableName,
					'pp_propname' => 'CargoAttachedTable'
				]
			);
			foreach ( $res as $row ) {
				$templateID = $row->pp_page;
				$attachedTemplateTitle = Title::newFromID( $templateID );
				$numPages = $this->getNumPagesThatCallTemplate( $dbw, $attachedTemplateTitle );
				$attachedTemplateName = $attachedTemplateTitle->getText();
				$templateData[] = [
					'name' => $attachedTemplateName,
					'numPages' => $numPages
				];
			}
		}

		$ct = SpecialPage::getTitleFor( 'CargoTables' );
		$viewTableURL = $ct->getInternalURL() . '/' . $this->mTableName;

		// Store all the necesssary data on the page.
		$text = Html::element( 'div', [
				'hidden' => 'true',
				'id' => 'recreateDataData',
				// These two variables are not data-
				// specific, but this seemed like the
				// easiest way to pass them over without
				// interfering with any other pages.
				// (Is this the best way to get the
				// API URL?)
				'apiurl' => $wgScriptPath . "/api.php",
				'cargoscriptpath' => $cgScriptPath,
				'tablename' => $this->mTableName,
				'isdeclared' => $this->mIsDeclared,
				'viewtableurl' => $viewTableURL
			], json_encode( $templateData ) );

		// Simple form.
		$text .= '<div id="recreateDataCanvas">' . "\n";
		if ( $tableExists ) {
			// Possibly disable checkbox, to avoid problems if the
			// DB hasn't been updated for version 1.5+.
			$indexExists = $dbw->indexExists( 'cargo_tables', 'cargo_tables_template_id' );
			if ( $indexExists ) {
				$text .= '<p><em>The checkbox intended to go here is temporarily disabled; please run <tt>update.php</tt> to see it.</em></p>';
			} else {
				$checkBox = new OOUI\FieldLayout(
					new OOUI\CheckboxInputWidget( [
						'name' => 'createReplacement',
						'selected' => true,
						'value' => 1,
					] ),
					[
						'label' => $this->msg( 'cargo-recreatedata-createreplacement' )->parse(),
						'align' => 'inline',
						'infusable' => true,
					]
				);
				$text .= Html::rawElement( 'p', null, $checkBox );
			}
		}
		$msg = $tableExists ? 'cargo-recreatedata-desc' : 'cargo-recreatedata-createdata';
		$text .= Html::element( 'p', null, $this->msg( $msg )->parse() );
		$text .= new OOUI\ButtonInputWidget( [
			'id' => 'cargoSubmit',
			'label' => $this->msg( 'ok' )->parse(),
			'flags' => [ 'primary', 'progressive' ]
		 ] );
		$text .= "\n</div>";

		$out->addHTML( $text );

		return true;
	}

	public function getNumPagesThatCallTemplate( $dbw, $templateTitle ) {
		$res = $dbw->select(
			[ 'page', 'templatelinks' ],
			'COUNT(*) AS total',
			[
				"tl_from=page_id",
				"tl_namespace" => $templateTitle->getNamespace(),
				"tl_title" => $templateTitle->getDBkey()
			],
			__METHOD__,
			[]
		);
		$row = $dbw->fetchRow( $res );
		return intval( $row['total'] );
	}

}
