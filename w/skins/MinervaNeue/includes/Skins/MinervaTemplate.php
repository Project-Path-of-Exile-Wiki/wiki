<?php
/**
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
 * @file
 */
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\SkinOptions;

/**
 * Extended Template class of BaseTemplate for mobile devices
 */
class MinervaTemplate extends BaseTemplate {
	/** @var bool Specify whether the page is a special page */
	protected $isSpecialPage;

	/** @var bool Specify whether the page is main page */
	protected $isMainPage;

	/** @var bool */
	protected $isMainPageTalk;

	/**
	 * Start render the page in template
	 * @deprecated please migrate code here to SkinMinerva::getTemplateData
	 * @return array
	 */
	public function execute() {
		$title = $this->getSkin()->getTitle();
		$this->isSpecialPage = $title->isSpecialPage();
		$this->isMainPage = $title->isMainPage();
		$subjectPage = MediaWikiServices::getInstance()->getNamespaceInfo()
			->getSubjectPage( $title );

		$this->isMainPageTalk = Title::newFromLinkTarget( $subjectPage )->isMainPage();
		Hooks::run( 'MinervaPreRender', [ $this ], '1.35' );
		return $this->getTemplateData();
	}

	/**
	 * Returns available page actions
	 * @return array
	 */
	protected function getPageActions() {
		return $this->isFallbackEditor() ? [] : $this->data['pageActionsMenu'];
	}

	/**
	 * Get the HTML for rendering the available page actions
	 * @return string
	 */
	protected function getPageActionsHtml() {
		$templateParser = new TemplateParser( __DIR__ . '/../../components' );
		$pageActions = $this->getPageActions();
		$html = '';

		if ( $pageActions && $pageActions['toolbar'] ) {
			$html = $templateParser->processTemplate( 'PageActionsMenu',  $pageActions );
		}
		return $html;
	}

	/**
	 * Returns the 'Last edited' message, e.g. 'Last edited on...'
	 * @param array $data Data used to build the page
	 * @return string
	 */
	protected function getHistoryLinkHtml( $data ) {
		$action = Action::getActionName( RequestContext::getMain() );
		if ( isset( $data['historyLink'] ) && $action === 'view' ) {
			$args = [
				'historyIconClass' => MinervaUI::iconClass(
					'history-base20', 'mw-ui-icon-small', '', 'wikimedia'
				),
				'arrowIconClass' => MinervaUI::iconClass(
					'expand-gray', 'small',
					'mf-mw-ui-icon-rotate-anti-clockwise indicator',
					// Uses icon in MobileFrontend so must be prefixed mf.
					// Without MobileFrontend it will not render.
					// Rather than maintain 2 versions (and variants) of the arrow icon which can conflict
					// with each othe and bloat CSS, we'll
					// use the MobileFrontend one. Long term when T177432 and T160690 are resolved
					// we should be able to use one icon definition and break this dependency.
					'mf'
				 ),
			] + $data['historyLink'];
			$templateParser = new TemplateParser( __DIR__ );
			return $templateParser->processTemplate( 'history', $args );
		}

		return '';
	}

	/**
	 * @return bool
	 */
	protected function isFallbackEditor() {
		$action = $this->getSkin()->getRequest()->getVal( 'action' );
		return $action === 'edit';
	}

	/**
	 * Get page secondary actions
	 * @return array
	 */
	protected function getSecondaryActions() {
		if ( $this->isFallbackEditor() ) {
			return [];
		}

		return $this->data['secondary_actions'];
	}

	/**
	 * Get HTML representing secondary page actions like language selector
	 * @return string
	 */
	protected function getSecondaryActionsHtml() {
		$baseClass = MinervaUI::buttonClass( '', 'button' );
		/** @var SkinMinerva $skin */
		$skin = $this->getSkin();
		$html = '';
		// no secondary actions on the user page
		if ( $skin instanceof SkinMinerva && !$skin->getUserPageHelper()->isUserPage() ) {
			foreach ( $this->getSecondaryActions() as $el ) {
				if ( isset( $el['attributes']['class'] ) ) {
					$el['attributes']['class'] .= ' ' . $baseClass;
				} else {
					$el['attributes']['class'] = $baseClass;
				}
				// @phan-suppress-next-line PhanTypeMismatchArgument
				$html .= Html::element( 'a', $el['attributes'], $el['label'] );
			}
		}

		return $html;
	}

	/**
	 * Get the HTML for the content of a page
	 * @param array $data Data used to build the page
	 * @return string representing HTML of content
	 */
	protected function getContentHtml( $data ) {
		if ( !$data[ 'unstyledContent' ] ) {
			$content = Html::openElement( 'div', [
				'id' => 'bodyContent',
				'class' => 'content',
			] );
			$content .= $data[ 'bodytext' ];
			if ( isset( $data['subject-page'] ) ) {
				$content .= $data['subject-page'];
			}
			return $content . Html::closeElement( 'div' );
		}

		return $data[ 'bodytext' ];
	}

	/**
	 * Gets the main menu HTML.
	 * @param array $data Data used to build the page
	 * @return string
	 */
	protected function getMainMenuData( $data ) {
		return $data['mainMenu']['items'];
	}

	/**
	 * Render the entire page
	 * @deprecated please migrate code here to SkinMinerva::getTemplateData
	 * @return array
	 */
	protected function getTemplateData() {
		$data = $this->data;
		$skinOptions = MediaWikiServices::getInstance()->getService( 'Minerva.SkinOptions' );
		$templateParser = new TemplateParser( __DIR__ );

		$internalBanner = $data[ 'internalBanner' ];
		$preBodyHtml = $data['prebodyhtml'] ?? '';
		$hasHeadingHolder = $internalBanner || $preBodyHtml || isset( $data['pageActionsMenu'] );
		$hasPageActions = $this->hasPageActions( $data['skin']->getContext() );

		// prepare template data
		return [
			'banners' => $data['banners'],
			'wgScript' => $data['wgScript'],
			'isAnon' => $data['username'] === null,
			'search' => $data['search'],
			'placeholder' => wfMessage( 'mobile-frontend-placeholder' ),
			'main-menu-tooltip' => $this->getMsg( 'mobile-frontend-main-menu-button-tooltip' ),
			'mainPageURL' => Title::newMainPage()->getLocalURL(),
			'userNavigationLabel' => wfMessage( 'minerva-user-navigation' ),
			// A button when clicked will submit the form
			// This is used so that on tablet devices with JS disabled the search button
			// passes the value of input to the search
			// We avoid using input[type=submit] as these cannot be easily styled as mediawiki ui icons
			// which is problematic in Opera Mini (see T140490)
			'searchButton' => Html::rawElement( 'button', [
				'id' => 'searchIcon',
				'class' => MinervaUI::iconClass(
					'search-base20', 'element', 'skin-minerva-search-trigger', 'wikimedia'
				)
			], wfMessage( 'searchbutton' )->escaped() ),
			'userNotificationsHTML' => $data['userNotificationsHTML'] ?? '',
			'data-main-menu' => $this->getMainMenuData( $data ),
			'hasheadingholder' => $hasHeadingHolder,
			'taglinehtml' => $data['taglinehtml'],
			'internalBanner' => $internalBanner,
			'prebodyhtml' => $preBodyHtml,
			'headinghtml' => $data['headinghtml'] ?? '',
			'postheadinghtml' => $data['postheadinghtml'] ?? '',
			'pageactionshtml' => $hasPageActions ? $this->getPageActionsHtml() : '',
			'userMenuHTML' => $data['userMenuHTML'],
			'subtitle' => $data['subtitle'],
			'contenthtml' => $this->getContentHtml( $data ),
			'secondaryactionshtml' => $this->getSecondaryActionsHtml(),

			'html-minerva-lastmodified' => $this->getHistoryLinkHtml( $data ),
			// Note mobile-license is only available on the mobile skin. It is outputted as part of
			// footer-info on desktop hence the conditional check.
			'html-minerva-license' => ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) ?
				MobileFrontendSkinHooks::getLicenseText( $this->getSkin() ) : '',

			'isBeta' => $skinOptions->get( SkinOptions::BETA_MODE ),
			'tabs' => $this->showTalkTabs( $hasPageActions, $skinOptions ) &&
					  $skinOptions->get( SkinOptions::TALK_AT_TOP ) ? [
				'items' => array_values( $data['content_navigation']['namespaces'] ),
			] : false,
			'searchPageTitle' => $this->getSkin()->getSearchPageTitle(),
		];
	}

	/**
	 * @param IContextSource $context
	 * @return bool
	 */
	private function hasPageActions( IContextSource $context ) {
		return !$this->isSpecialPage && !$this->isMainPage &&
		   Action::getActionName( $context ) === 'view';
	}

	/**
	 * @param bool $hasPageActions
	 * @param SkinOptions $skinOptions
	 * @return bool
	 */
	private function showTalkTabs( $hasPageActions, SkinOptions $skinOptions ) {
		$hasTalkTabs = $hasPageActions && !$this->isMainPageTalk;
		if ( !$hasTalkTabs && $this->isSpecialPage &&
			 $skinOptions->get( SkinOptions::TABS_ON_SPECIALS ) ) {
			$hasTalkTabs = true;
		}
		return $hasTalkTabs;
	}
}
