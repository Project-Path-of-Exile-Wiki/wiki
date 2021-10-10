'use strict';

const { iAmOnATalkPageWithNoTalkTopics } = require( '../features/step_definitions/create_page_api_steps' ),
	{
		pageExists, iAmOnAPageThatDoesNotExist,
		iAmUsingTheMobileSite,
		iAmLoggedIntoTheMobileWebsite,
		iAmOnPage
	} = require( '../features/step_definitions/common_steps' ),
	{
		iClickTheAddTalkButton,
		iAddATopic,
		iSeeTheTalkOverlay,
		thereShouldBeASaveDiscussionButton,
		noTopicIsPresent,
		thereShouldBeAnAddDiscussionButton,
		thereShouldBeATalkTab,
		thereShouldBeNoTalkButton,
		iShouldSeeTheTopicInTheListOfTopics
	} = require( '../features/step_definitions/talk_steps' );

// @chrome @en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org @vagrant
describe( 'Talk', () => {

	before( () => {
		pageExists( 'Talk:Selenium talk test' );
		pageExists( 'Selenium talk test' );
	} );

	beforeEach( () => {
		iAmUsingTheMobileSite();
	} );

	it( 'Talk button not visible as logged out user', () => {
		iAmOnPage( 'Selenium talk test' );
		thereShouldBeNoTalkButton();
	} );

	// @login
	it( 'Talk tab visible as logged in user', () => {
		iAmLoggedIntoTheMobileWebsite();
		iAmOnPage( 'Selenium talk test' );
		thereShouldBeATalkTab();
	} );

	// @login
	it( 'Talk on a page that doesn\'t exist (bug 64268)', () => {
		iAmLoggedIntoTheMobileWebsite();
		iAmOnAPageThatDoesNotExist();
		thereShouldBeATalkTab();
	} );

	// @smoke @login
	it( 'Add discussion button shows on talk pages for logged in users', () => {
		iAmLoggedIntoTheMobileWebsite();
		iAmOnPage( 'Talk:Selenium talk test' );
		thereShouldBeAnAddDiscussionButton();
	} );

	// @smoke @login
	it( 'Add discussion for talk page possible as logged in user', () => {
		iAmLoggedIntoTheMobileWebsite();
		iAmOnPage( 'Talk:Selenium talk test' );
		iClickTheAddTalkButton();
		thereShouldBeASaveDiscussionButton();
	} );

	it.skip( 'A newly created topic appears in the list of topics', () => {
		iAmLoggedIntoTheMobileWebsite();
		iAmOnATalkPageWithNoTalkTopics();
		noTopicIsPresent();
		iClickTheAddTalkButton();
		iSeeTheTalkOverlay();
		iAddATopic( 'New topic' );
		iShouldSeeTheTopicInTheListOfTopics( 'New topic' );
	} );

} );
