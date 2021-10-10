'use strict';

const { iClickOnTheCategoryButton,
		iShouldSeeTheCategoriesOverlay,
		iShouldSeeAListOfCategories
	} = require( '../features/step_definitions/category_steps' ),
	path = require( 'path' ),
	{
		iAmInAWikiThatHasCategories
	} = require( '../features/step_definitions/create_page_api_steps' ),
	{
		iAmUsingTheMobileSite,
		iAmOnPage, iAmInBetaMode
	} = require( '../features/step_definitions/common_steps' );

// Feature: Categories
describe( 'Categories', function () {
	before( function () {
		const config = require( path.resolve( `${__dirname}../../../../skin.json` ) ).config;

		// See https://danielkorn.io/post/skipping-tests-in-mochajs/
		// if categories is not enabled by default we won't test this feature.
		// This test will thus need to be run manually
		// or become valid when and if the feature is pushed.
		if ( !config.MinervaShowCategoriesButton.stable ) {
			this.skip();
		}
	} );

	// Scenario: I can view categories
	it( 'I can view categories', function () {

		const title = 'Selenium categories test page';
		// Given I am in a wiki that has categories
		iAmInAWikiThatHasCategories( title );

		// And I am using the mobile site
		iAmUsingTheMobileSite();

		// And I am in beta mode
		iAmInBetaMode();

		// And I am on the "Selenium categories test page" page
		iAmOnPage( title );

		// When I click on the category button
		iClickOnTheCategoryButton();

		// Then I should see the categories overlay
		iShouldSeeTheCategoriesOverlay();

		iShouldSeeAListOfCategories();
	} );
} );
