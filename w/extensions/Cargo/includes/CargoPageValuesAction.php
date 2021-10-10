<?php
/**
 * Handles the 'pagevalues' action.
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */

class CargoPageValuesAction extends Action {

	/**
	 * Return the name of the action this object responds to.
	 * @return string lowercase
	 */
	public function getName() {
		return 'pagevalues';
	}

	/**
	 * The main action entry point. Do all output for display and send it
	 * to the context output.
	 * $this->getOutput(), etc.
	 */
	public function show() {
		$title = $this->page->getTitle();
		$pageValuesPage = new CargoPageValues( $title );
		$pageValuesPage->execute();
	}

	public static function getPageValuesActionArray( $title ) {
		return [
			'msg' => 'pagevalues',
			'href' => $title->getLocalUrl( [ 'action' => 'pagevalues' ] ),
			'id' => 't-cargopagevalueslink',
			'rel' => 'cargo-pagevalues'
		];
	}

	/**
	 * Add the "Page values" link to the toolbox.
	 *
	 * Called for MW versions before 1.35, with the (now-deprecated)
	 * BaseTemplateToolbox hook.
	 *
	 * @param BaseTemplate $skinTemplate
	 * @param array &$toolbox
	 * @return bool
	 */
	public static function addLinkOld( BaseTemplate $skinTemplate, array &$toolbox ) {
		$title = $skinTemplate->getSkin()->getTitle();
		// This function doesn't usually get called for special pages,
		// but sometimes it is.
		if ( $title->isSpecialPage() ) {
			return true;
		}

		$toolbox['cargo-pagevalues'] =
			self::getPageValuesActionArray( $title );

		return true;
	}

	/**
	 * Add the "Page values" link to the toolbox.
	 *
	 * Called for MW version 1.35 and higher, with the SidebarBeforeOutput
	 * hook. This function can run with earlier versions as well, but it
	 * doesn't add anything to the sidebar.
	 *
	 * @param Skin $skin
	 * @param array &$sidebar
	 * @return void
	 */
	public static function addLink( Skin $skin, array &$sidebar ) {
		$title = $skin->getTitle();
		// This function doesn't usually get called for special pages,
		// but sometimes it is.
		if ( $title->isSpecialPage() ) {
			return;
		}

		$sidebar['TOOLBOX']['cargo-pagevalues'] =
			self::getPageValuesActionArray( $title );
	}

}
