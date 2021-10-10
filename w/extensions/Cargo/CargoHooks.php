<?php

use MediaWiki\Revision\SlotRecord;

/**
 * CargoHooks class
 *
 * @author Yaron Koren
 * @ingroup Cargo
 */
class CargoHooks {

	public static function registerExtension() {
		global $cgScriptPath, $wgScriptPath, $wgCargoFieldTypes, $wgHooks;

		define( 'CARGO_VERSION', '2.8' );

		// Script path.
		$cgScriptPath = $wgScriptPath . '/extensions/Cargo';

		$wgCargoFieldTypes = [
			'Page', 'String', 'Text', 'Integer', 'Float', 'Date',
			'Datetime', 'Boolean', 'Coordinates', 'Wikitext',
			'Searchtext', 'File', 'URL', 'Email', 'Rating'
		];

		if ( class_exists( 'MediaWiki\HookContainer\HookContainer' ) ) {
			// MW 1.35+
			$wgHooks['SidebarBeforeOutput'][] = "CargoPageValuesAction::addLink";
			$wgHooks['PageSaveComplete'][] = "CargoHooks::onPageSaveComplete";
		} else {
			// MW < 1.35
			$wgHooks['BaseTemplateToolbox'][] = "CargoPageValuesAction::addLinkOld";
			$wgHooks['PageContentSaveComplete'][] = "CargoHooks::onPageContentSaveComplete";
		}
	}

	public static function registerParserFunctions( &$parser ) {
		$parser->setFunctionHook( 'cargo_declare', [ 'CargoDeclare', 'run' ] );
		$parser->setFunctionHook( 'cargo_attach', [ 'CargoAttach', 'run' ] );
		$parser->setFunctionHook( 'cargo_store', [ 'CargoStore', 'run' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'cargo_query', [ 'CargoQuery', 'run' ] );
		$parser->setFunctionHook( 'cargo_compound_query', [ 'CargoCompoundQuery', 'run' ] );
		$parser->setFunctionHook( 'recurring_event', [ 'CargoRecurringEvent', 'run' ] );
		$parser->setFunctionHook( 'cargo_display_map', [ 'CargoDisplayMap', 'run' ] );
		return true;
	}

	/**
	 * ResourceLoaderRegisterModules hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader &$resourceLoader The ResourceLoader object
	 * @return bool Always true
	 */
	public static function registerModules( ResourceLoader &$resourceLoader ) {
		global $wgVersion;

		$cargoDir = __DIR__;

		// Between MW 1.34 and 1.35, all the jquery.ui.* modules were
		// merged into one big module, "jquery.ui".
		if ( version_compare( $wgVersion, '1.35', '>=' ) ) {
			$drilldownDependencies = [
				"jquery.ui",
				"oojs-ui-core"
			];
			$cargoQueryDependencies = [
				"jquery.ui",
				"mediawiki.util",
				"mediawiki.htmlform.ooui"
			];
		} else {
			$drilldownDependencies = [
				"jquery.ui.autocomplete",
				"jquery.ui.button",
				"oojs-ui-core"
			];
			$cargoQueryDependencies = [
				"jquery.ui.autocomplete",
				"mediawiki.util",
				"mediawiki.htmlform.ooui"
			];
		}

		$drilldownDependencies[] = 'ext.cargo.main';
		$cargoQueryDependencies[] = 'ext.cargo.main';

		$resourceLoader->register( [
			"ext.cargo.drilldown" => [
				'localBasePath' => $cargoDir,
				'remoteExtPath' => 'Cargo',
				'styles' => [
					"drilldown/resources/CargoDrilldown.css",
					"drilldown/resources/CargoJQueryUIOverrides.css"
				],
				'scripts' => "drilldown/resources/CargoDrilldown.js",
				'dependencies' => $drilldownDependencies
			],
			"ext.cargo.cargoquery" => [
				'localBasePath' => $cargoDir,
				'remoteExtPath' => 'Cargo',
				'styles' => "libs/balloon.css",
				'scripts' => "libs/ext.cargo.query.js",
				'messages' => [
					"cargo-viewdata-tablesrequired",
					"cargo-viewdata-joinonrequired"
				],
				'dependencies' => $cargoQueryDependencies
			]
		] );

		return true;
	}

	/**
	 * Add date-related messages to Global JS vars in user language
	 *
	 * @global int $wgCargoMapClusteringMinimum
	 * @param array &$vars Global JS vars
	 * @param OutputPage $out
	 * @return bool
	 */
	public static function setGlobalJSVariables( array &$vars, OutputPage $out ) {
		global $wgCargoMapClusteringMinimum;

		$vars['wgCargoMapClusteringMinimum'] = $wgCargoMapClusteringMinimum;

		// Date-related arrays for the 'calendar' and 'timeline'
		// formats.
		// Built-in arrays already exist for month names, but those
		// unfortunately are based on the language of the wiki, not
		// the language of the user.
		$vars['wgCargoMonthNames'] = $out->getLanguage()->getMonthNamesArray();
		/**
		 * @TODO - should these be switched to objects with keys starting
		 *         from 1 to match month indexes instead of 0-index?
		 */
		array_shift( $vars['wgCargoMonthNames'] ); // start keys from 0

		$vars['wgCargoMonthNamesShort'] = $out->getLanguage()->getMonthAbbreviationsArray();
		array_shift( $vars['wgCargoMonthNamesShort'] ); // start keys from 0

		$vars['wgCargoWeekDays'] = [];
		$vars['wgCargoWeekDaysShort'] = [];
		for ( $i = 1; $i < 8; $i++ ) {
			$vars['wgCargoWeekDays'][] = $out->getLanguage()->getWeekdayName( $i );
			$vars['wgCargoWeekDaysShort'][] = $out->getLanguage()->getWeekdayAbbreviation( $i );
		}

		return true;
	}

	/**
	 * Add the "purge cache" link to page actions.
	 *
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 * @param SkinTemplate $skinTemplate
	 * @param mixed[] &$links
	 * @return bool
	 */
	public static function addPurgeCacheTab( SkinTemplate $skinTemplate, array &$links ) {
		global $wgVersion;

		// Only add this tab if neither the Purge nor SemanticMediaWiki extension
		// (which has its own "purge link") is installed.
		$extReg = ExtensionRegistry::getInstance();
		if ( $extReg->isLoaded( 'SemanticMediaWiki' ) || $extReg->isLoaded( 'Purge' ) ) {
			return true;
		}

		if ( $skinTemplate->getUser()->isAllowed( 'purge' ) ) {
			$skinTemplate->getOutput()->addModules( 'ext.cargo.purge' );
			$links['actions']['cargo-purge'] = [
				'class' => false,
				'text' => $skinTemplate->msg( 'cargo-purgecache' )->text(),
				'href' => $skinTemplate->getTitle()->getLocalUrl( [ 'action' => 'purge' ] )
			];
			// The mediawiki.notify module is always loaded in MW 1.35 and later,
			// so we set a DOM flag here so the ext.cargo.purge module
			// knows to load it for versions earlier than that.
			if ( version_compare( $wgVersion, '1.35', '<' ) ) {
				$links['actions']['cargo-purge']['data-ext-cargo-notify'] = true;
			}
		}

		return true;
	}

	public static function addTemplateFieldStart( $field, &$fieldStart ) {
		// If a generated template contains a field of type
		// 'Coordinates', add a #cargo_display_map call to the
		// display of that field.
		if ( $field->getFieldType() == 'Coordinates' ) {
			$fieldStart .= '{{#cargo_display_map:point=';
		}
		return true;
	}

	public static function addTemplateFieldEnd( $field, &$fieldEnd ) {
		// If a generated template contains a field of type
		// 'Coordinates', add (the end of) a #cargo_display_map call
		// to the display of that field.
		if ( $field->getFieldType() == 'Coordinates' ) {
			$fieldEnd .= '}}';
		}
		return true;
	}

	/**
	 * Deletes all Cargo data for a specific page - *except* data
	 * contained in Cargo tables which are read-only because their
	 * "replacement table" exists.
	 *
	 * @param int $pageID
	 * @todo - move this to a different class, like CargoUtils?
	 */
	public static function deletePageFromSystem( $pageID ) {
		// We'll delete every reference to this page in the
		// Cargo tables - in the data tables as well as in
		// cargo_pages. (Though we need the latter to be able to
		// efficiently delete from the former.)

		// Get all the "main" tables that this page is contained in.
		$dbw = wfGetDB( DB_MASTER );
		$cdb = CargoUtils::getDB();
		$cdb->begin();
		$cdbPageIDCheck = [ $cdb->addIdentifierQuotes( '_pageID' ) => $pageID ];

		$res = $dbw->select( 'cargo_pages', 'table_name', [ 'page_id' => $pageID ] );
		foreach ( $res as $row ) {
			$curMainTable = $row->table_name;

			if ( $cdb->tableExists( $curMainTable . '__NEXT' ) ) {
				// It's a "read-only" table - ignore.
				continue;
			}

			// First, delete from the "field" tables.
			$res2 = $dbw->select( 'cargo_tables', 'field_tables', [ 'main_table' => $curMainTable ] );
			$row2 = $dbw->fetchRow( $res2 );
			$fieldTableNames = unserialize( $row2['field_tables'] );
			foreach ( $fieldTableNames as $curFieldTable ) {
				// Thankfully, the MW DB API already provides a
				// nice method for deleting based on a join.
				$cdb->deleteJoin(
					$curFieldTable,
					$curMainTable,
					$cdb->addIdentifierQuotes( '_rowID' ),
					$cdb->addIdentifierQuotes( '_ID' ),
					$cdbPageIDCheck
				);
			}

			// Delete from the "files" helper table, if it exists.
			$curFilesTable = $curMainTable . '___files';
			if ( $cdb->tableExists( $curFilesTable ) ) {
				$cdb->delete( $curFilesTable, $cdbPageIDCheck );
			}

			// Now, delete from the "main" table.
			$cdb->delete( $curMainTable, $cdbPageIDCheck );
		}
		$res3 = $dbw->select( 'cargo_tables', 'field_tables', [ 'main_table' => '_pageData' ] );
		if ( $dbw->numRows( $res3 ) > 0 ) {
			$cdb->delete( '_pageData', $cdbPageIDCheck );
		}

		// Finally, delete from cargo_pages.
		$dbw->delete( 'cargo_pages', [ 'page_id' => $pageID ] );

		// End transaction and apply DB changes.
		$cdb->commit();
	}

	/**
	 * Called by the MediaWiki 'PageContentSaveComplete' hook.
	 *
	 * We use that hook, instead of 'PageContentSave', because we need
	 * the page ID to have been set already for newly-created pages.
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user Unused
	 * @param Content $content
	 * @param string $summary Unused
	 * @param bool $isMinor Unused
	 * @param null $isWatch Unused
	 * @param null $section Unused
	 * @param int $flags Unused
	 * @param Status $status Unused
	 *
	 * @return bool
	 */
	public static function onPageContentSaveComplete(
		WikiPage $wikiPage,
		$user,
		$content,
		$summary,
		$isMinor,
		$isWatch,
		$section,
		$flags,
		$status
	) {
		// First, delete the existing data.
		$pageID = $wikiPage->getID();
		self::deletePageFromSystem( $pageID );

		// Now parse the page again, so that #cargo_store will be
		// called.
		// Even though the page will get parsed again after the save,
		// we need to parse it here anyway, for the settings we
		// added to remain set.
		CargoStore::$settings['origin'] = 'page save';
		CargoUtils::parsePageForStorage( $wikiPage->getTitle(), $content->getNativeData() );

		// Also, save the "page data" and (if appropriate) "file data".
		$cdb = CargoUtils::getDB();
		$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
		CargoPageData::storeValuesForPage( $wikiPage->getTitle(), $useReplacementTable, false );
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		CargoFileData::storeValuesForFile( $wikiPage->getTitle(), $useReplacementTable );

		return true;
	}

	/**
	 * Called by the MediaWiki 'PageSaveComplete' hook.
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 *
	 * @return bool true
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		MediaWiki\User\UserIdentity $user,
		string $summary,
		int $flags,
		MediaWiki\Revision\RevisionRecord $revisionRecord,
		MediaWiki\Storage\EditResult $editResult
	) {
		// First, delete the existing data.
		$pageID = $wikiPage->getID();
		self::deletePageFromSystem( $pageID );

		// Now parse the page again, so that #cargo_store will be
		// called.
		// Even though the page will get parsed again after the save,
		// we need to parse it here anyway, for the settings we
		// added to remain set.
		CargoStore::$settings['origin'] = 'page save';
		CargoUtils::parsePageForStorage(
			$wikiPage->getTitle(),
			$revisionRecord->getContent( SlotRecord::MAIN )->getNativeData()
		);

		// Also, save the "page data" and (if appropriate) "file data".
		$cdb = CargoUtils::getDB();
		$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
		CargoPageData::storeValuesForPage( $wikiPage->getTitle(), $useReplacementTable, false );
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		CargoFileData::storeValuesForFile( $wikiPage->getTitle(), $useReplacementTable );

		return true;
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionApproved( $parser, $title, $revID ) {
		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		// In an unexpected surprise, it turns out that simply adding
		// this setting will (usually) be enough to get the correct
		// revision of this page to be saved by Cargo, since the page
		// will (usually) be parsed right after this.
		// The one exception to that rule is that if it's the latest
		// revision being approved, the page is sometimes not parsed (?) -
		// so in that case, we'll parse it ourselves.
		CargoStore::$settings['origin'] = 'Approved Revs revision approved';
		if ( $revID == $title->getLatestRevID() ) {
			CargoUtils::parsePageForStorage( $title, null );
		}
		$cdb = CargoUtils::getDB();
		$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
		CargoPageData::storeValuesForPage( $title, $useReplacementTable );
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		CargoFileData::storeValuesForFile( $title, $useReplacementTable );

		return true;
	}

	/**
	 * Called by a hook in the Approved Revs extension.
	 */
	public static function onARRevisionUnapproved( $parser, $title ) {
		global $egApprovedRevsBlankIfUnapproved;

		$pageID = $title->getArticleID();
		self::deletePageFromSystem( $pageID );
		if ( !$egApprovedRevsBlankIfUnapproved ) {
			// No point storing the Cargo data if it's blank.
			CargoStore::$settings['origin'] = 'Approved Revs revision unapproved';
		}
		$cdb = CargoUtils::getDB();
		$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
		CargoPageData::storeValuesForPage( $title, $useReplacementTable, true, $egApprovedRevsBlankIfUnapproved );
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		CargoFileData::storeValuesForFile( $title, $useReplacementTable, $egApprovedRevsBlankIfUnapproved );

		return true;
	}

	/**
	 * @param Title &$title Unused
	 * @param Title &$newtitle
	 * @param User &$user Unused
	 * @param int $oldid
	 * @param int $newid Unused
	 * @param string $reason Unused
	 * @return bool
	 */
	public static function onTitleMoveComplete( Title &$title, Title &$newtitle, User &$user, $oldid,
		$newid, $reason ) {
		// For each main data table to which this page belongs, change
		// the page name-related fields.
		$newPageName = $newtitle->getPrefixedText();
		$newPageTitle = $newtitle->getText();
		$newPageNamespace = $newtitle->getNamespace();
		$dbw = wfGetDB( DB_MASTER );
		$cdb = CargoUtils::getDB();
		$cdb->begin();
		// We use $oldid, because that's the page ID - $newid is the
		// ID of the redirect page.
		$res = $dbw->select( 'cargo_pages', 'table_name', [ 'page_id' => $oldid ] );
		foreach ( $res as $row ) {
			$curMainTable = $row->table_name;
			$cdb->update( $curMainTable,
				[
					$cdb->addIdentifierQuotes( '_pageName' ) => $newPageName,
					$cdb->addIdentifierQuotes( '_pageTitle' ) => $newPageTitle,
					$cdb->addIdentifierQuotes( '_pageNamespace' ) => $newPageNamespace
				],
				[ $cdb->addIdentifierQuotes( '_pageID' ) => $oldid ]
			);
		}

		// Update the page title in the "general data" tables.
		$generalTables = [ '_pageData', '_fileData' ];
		foreach ( $generalTables as $generalTable ) {
			if ( $cdb->tableExists( $generalTable ) ) {
				// Update in the replacement table, if one exists.
				if ( $cdb->tableExists( $generalTable . '__NEXT' ) ) {
					$generalTable = $generalTable . '__NEXT';
				}
				$cdb->update( $generalTable,
					[
						$cdb->addIdentifierQuotes( '_pageName' ) => $newPageName,
						$cdb->addIdentifierQuotes( '_pageTitle' ) => $newPageTitle,
						$cdb->addIdentifierQuotes( '_pageNamespace' ) => $newPageNamespace
					],
					[ $cdb->addIdentifierQuotes( '_pageID' ) => $oldid ]
				);
			}
		}

		// End transaction and apply DB changes.
		$cdb->commit();

		// Save data for the original page (now a redirect).
		if ( $newid != 0 ) {
			$useReplacementTable = $cdb->tableExists( '_pageData__NEXT' );
			CargoPageData::storeValuesForPage( $title, $useReplacementTable );
		}

		return true;
	}

	/**
	 * Deletes all Cargo data about a page, if the page has been deleted.
	 */
	public static function onArticleDeleteComplete( &$article, User &$user, $reason, $id, $content,
		$logEntry ) {
		self::deletePageFromSystem( $id );
		return true;
	}

	/**
	 * Called by the MediaWiki 'UploadComplete' hook.
	 *
	 * Updates a file's entry in the _fileData table if it has been
	 * uploaded or re-uploaded.
	 *
	 * @param Image $image
	 * @return bool true
	 */
	public static function onUploadComplete( $image ) {
		$cdb = CargoUtils::getDB();
		if ( !$cdb->tableExists( '_fileData' ) ) {
			return true;
		}
		$title = $image->getLocalFile()->getTitle();
		$useReplacementTable = $cdb->tableExists( '_fileData__NEXT' );
		$pageID = $title->getArticleID();
		$cdbPageIDCheck = [ $cdb->addIdentifierQuotes( '_pageID' ) => $pageID ];
		$fileDataTable = $useReplacementTable ? '_fileData__NEXT' : '_fileData';
		$cdb->delete( $fileDataTable, $cdbPageIDCheck );
		CargoFileData::storeValuesForFile( $title, $useReplacementTable );
	}

	/**
	 * Called by the MediaWiki 'CategoryAfterPageAdded' hook.
	 *
	 * @param Category $category
	 * @param WikiPage $wikiPage
	 */
	public static function addCategoryToPageData( $category, $wikiPage ) {
		self::addOrRemoveCategoryData( $category, $wikiPage, true );
	}

	/**
	 * Called by the MediaWiki 'CategoryAfterPageRemoved' hook.
	 *
	 * @param Category $category
	 * @param WikiPage $wikiPage
	 */
	public static function removeCategoryFromPageData( $category, $wikiPage ) {
		self::addOrRemoveCategoryData( $category, $wikiPage, false );
	}

	/**
	 * We use hooks to modify the _categories field in _pageData, instead of
	 * saving it on page save as is done with all other fields (in _pageData
	 * and elsewhere), because the categories information is often not set
	 * until after the page has already been saved, due to the use of jobs.
	 * We can use the same function for both adding and removing categories
	 * because it's almost the same code either way.
	 * If anything gets messed up in this process, the data can be recreated
	 * by calling setCargoPageData.php.
	 */
	public static function addOrRemoveCategoryData( $category, $wikiPage, $isAdd ) {
		global $wgCargoPageDataColumns;
		if ( !in_array( 'categories', $wgCargoPageDataColumns ) ) {
			return true;
		}

		$cdb = CargoUtils::getDB();

		// We need to make sure that the "categories" field table
		// already exists, because we're only modifying it here, not
		// creating it.
		if ( $cdb->tableExists( '_pageData__NEXT___categories' ) ) {
			$pageDataTable = '_pageData__NEXT';
		} elseif ( $cdb->tableExists( '_pageData___categories' ) ) {
			$pageDataTable = '_pageData';
		} else {
			return true;
		}
		$categoriesTable = $pageDataTable . '___categories';
		$categoryName = $category->getName();
		$pageID = $wikiPage->getId();

		$cdb = CargoUtils::getDB();
		$cdb->begin();
		$res = $cdb->select( $pageDataTable, '_ID', [ '_pageID' => $pageID ] );
		if ( $cdb->numRows( $res ) == 0 ) {
			$cdb->commit();
			return true;
		}
		$row = $res->fetchRow();
		$rowID = $row['_ID'];
		$categoriesForPage = [];
		$res2 = $cdb->select( $categoriesTable, '_value',  [ '_rowID' => $rowID ] );
		foreach ( $res2 as $row2 ) {
			$categoriesForPage[] = $row2->_value;
		}
		$categoryAlreadyListed = in_array( $categoryName, $categoriesForPage );
		// This can be done with a NOT XOR (i.e. XNOR), but let's not make it more confusing.
		if ( ( $isAdd && $categoryAlreadyListed ) || ( !$isAdd && !$categoryAlreadyListed ) ) {
			$cdb->commit();
			return true;
		}

		// The real operation is here.
		if ( $isAdd ) {
			$categoriesForPage[] = $categoryName;
		} else {
			foreach ( $categoriesForPage as $i => $cat ) {
				if ( $cat == $categoryName ) {
					unset( $categoriesForPage[$i] );
				}
			}
		}
		$newCategoriesFull = implode( '|', $categoriesForPage );
		$cdb->update( $pageDataTable, [ '_categories__full' => $newCategoriesFull ], [ '_pageID' => $pageID ] );
		if ( $isAdd ) {
			$res3 = $cdb->select( $categoriesTable, 'MAX(_position) as MaxPosition',  [ '_rowID' => $rowID ] );
			$row3 = $res3->fetchRow();
			$maxPosition = $row3['MaxPosition'];
			$cdb->insert( $categoriesTable, [ '_rowID' => $rowID, '_value' => $categoryName, '_position' => $maxPosition + 1 ] );
		} else {
			$cdb->delete( $categoriesTable, [ '_rowID' => $rowID, '_value' => $categoryName ] );
		}

		// End transaction and apply DB changes.
		$cdb->commit();
		return true;
	}

	public static function describeDBSchema( DatabaseUpdater $updater ) {
		// DB updates
		// For now, there's just a single SQL file for all DB types.

		if ( $updater->getDB()->getType() == 'mysql' || $updater->getDB()->getType() == 'sqlite' ) {
			$updater->addExtensionTable( 'cargo_tables', __DIR__ . "/sql/Cargo.sql" );
			$updater->addExtensionTable( 'cargo_pages', __DIR__ . "/sql/Cargo.sql" );
		} elseif ( $updater->getDB()->getType() == 'postgres' ) {
			$updater->addExtensionUpdate( [ 'addTable', 'cargo_tables', __DIR__ . "/sql/Cargo.pg.sql", true ] );
			$updater->addExtensionUpdate( [ 'addTable', 'cargo_pages', __DIR__ . "/sql/Cargo.pg.sql", true ] );
		} elseif ( $updater->getDB()->getType() == 'mssql' ) {
			$updater->addExtensionUpdate( [ 'addTable', 'cargo_tables', __DIR__ . "/sql/Cargo.mssql.sql", true ] );
			$updater->addExtensionUpdate( [ 'addTable', 'cargo_pages', __DIR__ . "/sql/Cargo.mssql.sql", true ] );
		}
		return true;
	}

	/**
	 * Called by a hook in the Admin Links extension.
	 *
	 * @param ALTree &$adminLinksTree
	 * @return bool
	 */
	public static function addToAdminLinks( &$adminLinksTree ) {
		$browseSearchSection = $adminLinksTree->getSection(
			wfMessage( 'adminlinks_browsesearch' )->text() );
		$cargoRow = new ALRow( 'cargo' );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'CargoTables' ) );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'Drilldown' ) );
		$cargoRow->addItem( ALItem::newFromSpecialPage( 'CargoQuery' ) );
		$browseSearchSection->addRow( $cargoRow );

		return true;
	}

	/**
	 * Called by MediaWiki's ResourceLoaderStartUpModule::getConfig()
	 * to set static (not request-specific) configuration variables
	 * @param array &$vars
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $cgScriptPath;

		$vars['cgDownArrowImage'] = "$cgScriptPath/drilldown/resources/down-arrow.png";
		$vars['cgRightArrowImage'] = "$cgScriptPath/drilldown/resources/right-arrow.png";

		return true;
	}

	public static function addLuaLibrary( $engine, &$extraLibraries ) {
		$extraLibraries['mw.ext.cargo'] = 'CargoLuaLibrary';
		return true;
	}

	public static function cargoSchemaUpdates( DatabaseUpdater $updater ) {
		if ( $updater->getDB()->getType() == 'mysql' || $updater->getDB()->getType() == 'sqlite' ) {
			$updater->addExtensionField( 'cargo_tables', 'field_helper_tables', __DIR__ . '/sql/cargo_tables.patch.field_helper_tables.sql', true );
			$updater->dropExtensionIndex( 'cargo_tables', 'cargo_tables_template_id', __DIR__ . '/sql/cargo_tables.patch.index_template_id.sql', true );
		} elseif ( $updater->getDB()->getType() == 'postgres' ) {
			$updater->addExtensionField( 'cargo_tables', 'field_helper_tables', __DIR__ . '/sql/cargo_tables.patch.field_helper_tables.pg.sql', true );
			$updater->dropExtensionIndex( 'cargo_tables', 'cargo_tables_template_id', __DIR__ . '/sql/cargo_tables.patch.index_template_id.pg.sql', true );
		} elseif ( $updater->getDB()->getType() == 'mssql' ) {
			$updater->addExtensionField( 'cargo_tables', 'field_helper_tables', __DIR__ . '/sql/cargo_tables.patch.field_helper_tables.mssql.sql', true );
			$updater->dropExtensionIndex( 'cargo_tables', 'cargo_tables_template_id', __DIR__ . '/sql/cargo_tables.patch.index_template_id.mssql.sql', true );
		}
		return true;
	}

}
