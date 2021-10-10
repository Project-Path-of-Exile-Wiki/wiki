'use strict';

const assert = require( 'assert' ),
	{ ArticlePage } = require( './../support/world' );

const iClickOnTheCategoryButton = () => {
	ArticlePage.category_element.waitForExist();
	ArticlePage.category_element.click();
};

const iShouldSeeTheCategoriesOverlay = () => {
	ArticlePage.overlay_heading_element.waitForExist();
	assert.strictEqual( ArticlePage.overlay_heading_element.getText(),
		'Categories' );
};

const iShouldSeeAListOfCategories = () => {
	const el = ArticlePage.overlay_category_topic_item_element.waitForDisplayed();
	assert.strictEqual( el, true );
};

module.exports = {
	iClickOnTheCategoryButton,
	iShouldSeeTheCategoriesOverlay,
	iShouldSeeAListOfCategories
};
