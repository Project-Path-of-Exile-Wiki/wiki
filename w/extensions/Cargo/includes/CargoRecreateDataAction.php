<?php

use MediaWiki\MediaWikiServices;

/**
 * Handles the 'recreatedata' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoRecreateDataAction extends Action {
	/**
	 * Return the name of the action this object responds to
	 * @return string lowercase
	 */
	public function getName() {
		return 'recreatedata';
	}

	/**
	 * The main action entry point. Do all output for display and send it
	 * to the context output.
	 * $this->getOutput(), etc.
	 */
	public function show() {
		$title = $this->page->getTitle();

		// These tabs should only exist for template pages, that
		// either call (or called) #cargo_declare, or call
		// #cargo_attach.
		list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $title );

		if ( $tableName == '' ) {
			$out = $this->getOutput();
			$out->setPageTitle( $this->msg( 'cargo-createdatatable' )->parse() );
			// @TODO - create an i18n message for this.
			$out->addHTML( CargoUtils::formatError( 'This template does not declare any Cargo table.' ) );
			return;
		}

		$recreateDataPage = new SpecialCargoRecreateData( $title, $tableName, $isDeclared );
		$recreateDataPage->execute();
	}

	/**
	 * Adds an "action" (i.e., a tab) to recreate the current article's data
	 *
	 * @param Title $obj
	 * @param array &$links
	 * @return bool
	 */
	public static function displayTab( $obj, &$links ) {
		$title = $obj->getTitle();
		if ( !$title || $title->getNamespace() !== NS_TEMPLATE ) {
			return true;
		}

		$user = $obj->getUser();
		if ( method_exists( 'MediaWiki\Permissions\PermissionManager', 'userCan' ) ) {
			// MW 1.33+
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			if ( !$permissionManager->userCan( 'recreatecargodata', $user, $title ) ) {
				return true;
			}
		} else {
			if ( !$title->userCan( 'recreatecargodata', $user ) ) {
				return true;
			}
		}

		$request = $obj->getRequest();

		// Make sure that this is a template page, that it either
		// has (or had) a #cargo_declare call or has a #cargo_attach
		// call, and that the user is allowed to recreate its data.
		list( $tableName, $isDeclared ) = CargoUtils::getTableNameForTemplate( $title );
		if ( $tableName == '' ) {
			return true;
		}

		// Check if table already exists, and set tab accordingly.
		if ( CargoUtils::tableFullyExists( $tableName ) ) {
			$recreateDataTabMsg = 'recreatedata';
		} else {
			$recreateDataTabMsg = 'cargo-createdatatable';
		}

		$recreateDataTab = [
			'class' => ( $request->getVal( 'action' ) == 'recreatedata' ) ? 'selected' : '',
			'text' => $obj->msg( $recreateDataTabMsg )->parse(),
			'href' => $title->getLocalURL( 'action=recreatedata' )
		];

		$links['views']['recreatedata'] = $recreateDataTab;

		return true;
	}

}
