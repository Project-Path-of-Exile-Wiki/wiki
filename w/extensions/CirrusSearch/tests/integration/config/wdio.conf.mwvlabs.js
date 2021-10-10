'use strict';

const merge = require( 'deepmerge' ),
	wdioConf = require( './wdio.conf.js' );

// Overwrite default settings
exports.config = merge( wdioConf.config, {
	reporters: [ 'dot', 'junit' ],
	reporterOptions: {
		junit: {
			outputDir: __dirname + '/../log'
		}
	},
	wikis: {
		cirrustest: {
			apiUrl: 'https://cirrustest-' + process.env.MWV_LABS_HOSTNAME + '.wmflabs.org/w/api.php',
			baseUrl: 'https://cirrustest-' + process.env.MWV_LABS_HOSTNAME + '.wmflabs.org'
		},
		commons: {
			apiUrl: 'https://commons-' + process.env.MWV_LABS_HOSTNAME + '.wmflabs.org/w/api.php',
			baseUrl: 'https://commons-' + process.env.MWV_LABS_HOSTNAME + '.wmflabs.org'
		},
		ru: {
			apiUrl: 'https://ru-' + process.env.MWV_LABS_HOSTNAME + '.wmflabs.org/w/api.php',
			baseUrl: 'https://ru-' + process.env.MWV_LABS_HOSTNAME + '.wmflabs.org'
		}
	}
// overwrite so new reporters override previous instead of merging into combined reporters
}, { arrayMerge: ( dest, source ) => source } );
