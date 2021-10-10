<?php

namespace Tests\MediaWiki\Minerva;

use ContentHandler;
use MediaWiki\Minerva\LanguagesHelper;
use MediaWiki\Minerva\Permissions\IMinervaPagePermissions;
use MediaWiki\Minerva\Permissions\MinervaPagePermissions;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\Permissions\PermissionManager;
use MediaWikiTestCase;
use RequestContext;
use Title;
use User;

/**
 * @group MinervaNeue
 * @coversDefaultClass \MediaWiki\Minerva\Permissions\MinervaPagePermissions
 */
class MinervaPagePermissionsTest extends MediaWikiTestCase {

	private function buildPermissionsObject(
		Title $title,
		array $actions = null,
		array $options = [],
		ContentHandler $contentHandler = null,
		User $user = null,
		$hasOtherLanguagesOrVariants = false,
		$alwaysShowLanguageButton = true
	) {
		$languageHelper = $this->createMock( LanguagesHelper::class );
		$languageHelper->expects( $this->any() )
			->method( 'doesTitleHasLanguagesOrVariants' )
			->willReturn( $hasOtherLanguagesOrVariants );

		$user = $user ?? $this->getTestUser()->getUser();
		$actions = $actions ?? [
				IMinervaPagePermissions::CONTENT_EDIT,
				IMinervaPagePermissions::WATCH,
				IMinervaPagePermissions::TALK,
				IMinervaPagePermissions::SWITCH_LANGUAGE,
		];
		$contentHandler = $contentHandler ??
			$this->getMockForAbstractClass( ContentHandler::class, [], '', false );
		$skinOptions = new SkinOptions();
		if ( $options ) {
			$skinOptions->setMultiple( $options );
		}

		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setConfig( new \HashConfig( [
			'MinervaPageActions' => $actions,
			'MinervaAlwaysShowLanguageButton' => $alwaysShowLanguageButton
		] ) );
		$context->setUser( $user );

		return ( new MinervaPagePermissions(
			$skinOptions,
			$languageHelper,
			$this->getMockBuilder( PermissionManager::class )
				->disableOriginalConstructor()
				->getMock()
		) )->setContext( $context, $contentHandler );
	}

	/**
	 * @covers ::isAllowed
	 */
	public function testWatchAndEditNotAllowedOnMainPage() {
		$perms = $this->buildPermissionsObject( Title::newMainPage() );

		$this->assertFalse( $perms->isAllowed( IMinervaPagePermissions::WATCH ) );
		$this->assertFalse( $perms->isAllowed( IMinervaPagePermissions::CONTENT_EDIT ) );

		// Check to make sure 'talk' and 'switch-language' are enabled on the Main page.
		$this->assertTrue( $perms->isAllowed( IMinervaPagePermissions::TALK ) );
		$this->assertTrue( $perms->isAllowed( IMinervaPagePermissions::SWITCH_LANGUAGE ) );
	}

	/**
	 * @covers ::isAllowed
	 */
	public function testInvalidPageActionsArentAllowed() {
		$perms = $this->buildPermissionsObject( Title::newFromText( 'test' ), [] );

		// By default, the "talk" and "watch" page actions are allowed but are now deemed invalid.
		$this->assertFalse( $perms->isAllowed( IMinervaPagePermissions::TALK ) );
		$this->assertFalse( $perms->isAllowed( IMinervaPagePermissions::WATCH ) );
	}

	/**
	 * @covers ::isAllowed
	 */
	public function testValidPageActionsAreAllowed() {
		$perms = $this->buildPermissionsObject( Title::newFromText( 'test' ) );
		$this->assertTrue( $perms->isAllowed( IMinervaPagePermissions::TALK ) );
		$this->assertTrue( $perms->isAllowed( IMinervaPagePermissions::WATCH ) );
	}

	public static function editPageActionProvider() {
		return [
			[ false, false, false ],
			[ true, false, false ],
			[ true, true, true ]
		];
	}

	/**
	 * The "edit" page action is allowed when the page doesn't support direct editing via the API.
	 *
	 * @dataProvider editPageActionProvider
	 * @covers ::isAllowed
	 */
	public function testEditPageAction(
		$supportsDirectEditing,
		$supportsDirectApiEditing,
		$expected
	) {
		$contentHandler = $this->getMockBuilder( 'ContentHandler' )
			->disableOriginalConstructor()
			->getMock();

		$contentHandler->method( 'supportsDirectEditing' )
			->will( $this->returnValue( $supportsDirectEditing ) );

		$contentHandler->method( 'supportsDirectApiEditing' )
			->will( $this->returnValue( $supportsDirectApiEditing ) );

		$perms = $this->buildPermissionsObject( Title::newFromText( 'test' ), null, [],
			$contentHandler );

		$this->assertEquals( $expected, $perms->isAllowed( IMinervaPagePermissions::CONTENT_EDIT ) );
	}

	/**
	 * @covers ::isAllowed
	 */
	public function testPageActionsWhenOnUserPage() {
		$perms = $this->buildPermissionsObject( Title::newFromText( 'User:Admin' ) );
		$this->assertTrue( $perms->isAllowed( IMinervaPagePermissions::TALK ) );
	}

	/**
	 * @covers ::isAllowed
	 */
	public function testPageActionsWhenOnAnonUserPage() {
		$perms = $this->buildPermissionsObject( Title::newFromText( 'User:1.1.1.1' ) );
		$this->assertTrue( $perms->isAllowed( IMinervaPagePermissions::TALK ) );
	}

	public static function switchLanguagePageActionProvider() {
		return [
			[ true,  false, true ],
			[ false, true,  true ],
			[ false, false, false ],
		];
	}

	/**
	 * MediaWiki defines wgHideInterlanguageLinks which is default set to false, but some wikis
	 * can set this config to true. Minerva page permissions must respect that
	 * @covers ::isAllowed
	 */
	public function testGlobalHideLanguageLinksTakesPrecedenceOnMainPage() {
		$this->setMwGlobals( [ 'wgHideInterlanguageLinks' => true ] );
		$perms = $this->buildPermissionsObject( Title::newMainPage() );
		$this->assertFalse( $perms->isAllowed( IMinervaPagePermissions::SWITCH_LANGUAGE ) );
	}

	/**
	 * MediaWiki defines wgHideInterlanguageLinks which is default set to false, but some wikis
	 * can set this config to true. Minerva page permissions must respect that
	 * @covers ::isAllowed
	 */
	public function testGlobalHideLanguageLinksTakesPrecedence() {
		$this->setMwGlobals( [ 'wgHideInterlanguageLinks' => true ] );
		$perms = $this->buildPermissionsObject( Title::newFromText( 'test' ) );
		$this->assertFalse( $perms->isAllowed( IMinervaPagePermissions::SWITCH_LANGUAGE ) );
	}

	/**
	 * The "switch-language" page action is allowed when: v2 of the page action bar is enabled and
	 * if the page has interlanguage links or if the <code>$wgMinervaAlwaysShowLanguageButton</code>
	 * configuration variable is set to truthy.
	 *
	 * @dataProvider switchLanguagePageActionProvider
	 * @covers ::isAllowed
	 */
	public function testSwitchLanguagePageAction(
		$hasLanguagesOrVariants,
		$minervaAlwaysShowLanguageButton,
		$expected
	) {
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'isMainPage' )
			->willReturn( false );

		$permissions = $this->buildPermissionsObject(
			$title,
			null,
			[],
			null,
			null,
			$hasLanguagesOrVariants,
			$minervaAlwaysShowLanguageButton
		);

		$actual = $permissions->isAllowed( IMinervaPagePermissions::SWITCH_LANGUAGE );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Watch action requires 'viewmywatchlist' and 'editmywatchlist' permissions
	 * to be grated. Verify that isAllowedAction('watch') returns false when user
	 * do not have those permissions granted
	 * @covers ::isAllowed
	 */
	public function testWatchIsAllowedOnlyWhenWatchlistPermissionsAreGranted() {
		$title = Title::newFromText( 'test_watchstar_permissions' );

		$userMock = $this->getMockBuilder( 'User' )
			->disableOriginalConstructor()
			->setMethods( [ 'isAllowedAll' ] )
			->getMock();
		$userMock->expects( $this->once() )
			->method( 'isAllowedAll' )
			->with( 'viewmywatchlist', 'editmywatchlist' )
			->willReturn( false );

		$perms = $this->buildPermissionsObject( $title, null, [], null, $userMock );
		$this->assertTrue( $perms->isAllowed( IMinervaPagePermissions::TALK ) );
		$this->assertFalse( $perms->isAllowed( IMinervaPagePermissions::WATCH ) );
	}

	/**
	 * If Title is not watchable, it cannot be watched
	 * @covers ::isAllowed
	 */
	public function testCannotWatchNotWatchableTitle() {
		$title = $this->createMock( Title::class );
		$title->expects( $this->once() )
			->method( 'isMainPage' )
			->willReturn( false );
		$title->expects( $this->once() )
			->method( 'isWatchable' )
			->willReturn( false );

		$permissions = $this->buildPermissionsObject( $title );
		$this->assertFalse( $permissions->isAllowed( IMinervaPagePermissions::WATCH ) );
	}

}
