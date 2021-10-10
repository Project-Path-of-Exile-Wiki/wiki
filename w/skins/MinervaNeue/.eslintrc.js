// "no-restricted-properties" rules are not properly merged when just using "extends".
// Therefore we have to have this file which calls a custom merge function.
// The merge function calls Object.assign with special handling for configuration such as
// `no-restricted-properties` and `no-restricted-syntax` which are array based - ensuring the two
// values being merged are concatenated.
const merge = require( 'eslint-config-wikimedia/language/merge.js' );
const config = {
	"root": true,
	"extends": [
		"wikimedia/client",
		"wikimedia/jquery",
		"wikimedia/mediawiki"
	],
	"env": {
		"commonjs": true
	},
	"globals": {
		"require": "readonly"
	},
	"rules": {
		"no-restricted-properties": [
			"error",
			{
				"property": "mobileFrontend",
				"message": "Minerva should only make use of core code. Any code using mobileFrontend should be placed inside the MobileFrontend extension"
			},
			{
				"property": "define",
				"message": "The method `define` if used with mw.mobileFrontend is deprecated. Please use `module.exports`."
			},
			{
				"property": "done",
				"message": "The method `done` if used with Deferred objects is incompatible with ES6 Promises. Please use `then`."
			},
			{
				"property": "fail",
				"message": "The method `fail` if used with Deferred objects is incompatible with ES6 Promises. Please use `then`."
			},
			{
				"property": "always",
				"message": "The method `always` if used with Deferred objects is incompatible with ES6 Promises. Please use `then`."
			}
		],
		"object-property-newline": "error",
		"mediawiki/class-doc": "off",
		"no-use-before-define": "off",
		"no-underscore-dangle": "off",
		"jsdoc/no-undefined-types": "off"
	}
};

module.exports = Object.assign(
	config,
	merge( config, require( 'eslint-config-wikimedia/language/not-es5.js' ) )
);
