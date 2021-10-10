'use strict';

const assert = require( 'assert' );
const { ArticlePage } = require( '../support/world.js' );

const iSeeALinkToAboutPage = () => {
	assert.strictEqual( ArticlePage.menu_element.$( '*=About' ).isDisplayed(), true );
};

const iClickOnTheMainNavigationButton = () => {
	ArticlePage.menu_button_element.click();
};

const iShouldSeeAUserPageLinkInMenu = () => {
	ArticlePage.menu_element.$( '.primary-action' );
};

const iShouldSeeLogoutLinkInMenu = () => {
	ArticlePage.menu_element.$( '.secondary-action' );
};

const iShouldSeeALinkInMenu = ( text ) => {
	assert.strictEqual( ArticlePage.menu_element.$( `span=${text}` ).isDisplayed(),
		true, `Link to ${text} is visible.` );
};

const iShouldSeeALinkToDisclaimer = () => {
	ArticlePage.menu_element.$( '=Disclaimers' ).waitForDisplayed();
	assert.strictEqual( ArticlePage.menu_element.$( '=Disclaimers' ).isDisplayed(), true );
};

module.exports = {
	iClickOnTheMainNavigationButton,
	iSeeALinkToAboutPage, iShouldSeeAUserPageLinkInMenu,
	iShouldSeeLogoutLinkInMenu,
	iShouldSeeALinkInMenu, iShouldSeeALinkToDisclaimer
};
