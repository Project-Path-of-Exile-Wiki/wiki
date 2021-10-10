/**
 * See also: http://webdriver.io/guide/testrunner/configurationfile.html
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const logPath = process.env.LOG_DIR || path.join( __dirname, '/log' );

let ffmpeg;

// get current test title and clean it, to use it as file name
function fileName( title ) {
	return encodeURIComponent( title.replace( /\s+/g, '-' ) );
}

// build file path
function filePath( test, screenshotPath, extension ) {
	return path.join( screenshotPath, `${fileName( test.parent )}-${fileName( test.title )}.${extension}` );
}

exports.config = {
	// ======
	// Custom WDIO config specific to MediaWiki
	// ======
	// Use in a test as `browser.options.<key>`.
	// Defaults are for convenience with MediaWiki-Vagrant

	// Wiki admin
	mwUser: process.env.MEDIAWIKI_USER || 'Admin',
	mwPwd: process.env.MEDIAWIKI_PASSWORD || 'vagrant',

	// Base for browser.url() and Page#openTitle()
	baseUrl: ( process.env.MW_SERVER || 'http://127.0.0.1:8080' ) + (
		process.env.MW_SCRIPT_PATH || '/w'
	),

	// ==================
	// Test Files
	// ==================
	specs: [
		__dirname + '/specs/*.js'
	],

	// ============
	// Capabilities
	// ============
	capabilities: [ {
		// https://sites.google.com/a/chromium.org/chromedriver/capabilities
		browserName: 'chrome',
		maxInstances: 1,
		'goog:chromeOptions': {
			// If DISPLAY is set, assume developer asked non-headless or CI with Xvfb.
			// Otherwise, use --headless (added in Chrome 59)
			// https://chromium.googlesource.com/chromium/src/+/59.0.3030.0/headless/README.md
			args: [
				...( process.env.DISPLAY ? [] : [ '--headless' ] ),
				// Chrome sandbox does not work in Docker
				...( fs.existsSync( '/.dockerenv' ) ? [ '--no-sandbox' ] : [] )
			]
		}
	} ],

	// ===================
	// Test Configurations
	// ===================

	// Level of verbosity: silent | verbose | command | data | result | error
	logLevel: 'error',

	// Setting this enables automatic screenshots for when a browser command fails
	// It is also used by afterTest for capturig failed assertions.
	screenshotPath: process.env.LOG_DIR || __dirname + '/log',

	// Default timeout for each waitFor* command.
	waitforTimeout: 10 * 1000,

	// See also: http://webdriver.io/guide/testrunner/reporters.html
	reporters: [ 'spec' ],

	// See also: http://mochajs.org
	mochaOpts: {
		ui: 'bdd',
		timeout: process.env.DEBUG ? ( 60 * 60 * 1000 ) : ( 60 * 1000 )
	},

	// Make sure you have the wdio adapter package for the specific framework
	// installed before running any tests.
	framework: 'mocha',

	// =====
	// Hooks
	// =====
	/**
	 * Executed before a Mocha test starts.
	 *
	 * @param {Object} test Mocha Test object
	 */
	beforeTest: function ( test ) {
		if ( process.env.DISPLAY && process.env.DISPLAY.startsWith( ':' ) ) {
			const videoPath = filePath( test, logPath, 'mp4' );
			const { spawn } = require( 'child_process' );
			ffmpeg = spawn( 'ffmpeg', [
				'-f', 'x11grab', //  grab the X11 display
				'-video_size', '1280x1024', // video size
				'-i', process.env.DISPLAY, // input file url
				'-loglevel', 'error', // log only errors
				'-y', // overwrite output files without asking
				'-pix_fmt', 'yuv420p', // QuickTime Player support, "Use -pix_fmt yuv420p for compatibility with outdated media players"
				videoPath // output file
			] );

			const logBuffer = function ( buffer, prefix ) {
				const lines = buffer.toString().trim().split( '\n' );
				lines.forEach( function ( line ) {
					console.log( prefix + line );
				} );
			};

			ffmpeg.stdout.on( 'data', ( data ) => {
				logBuffer( data, 'ffmpeg stdout: ' );
			} );

			ffmpeg.stderr.on( 'data', ( data ) => {
				logBuffer( data, 'ffmpeg stderr: ' );
			} );

			ffmpeg.on( 'close', ( code, signal ) => {
				console.log( '\n\tVideo location:', videoPath, '\n' );
				if ( code !== null ) {
					console.log( `\tffmpeg exited with code ${code} ${videoPath}` );
				}
				if ( signal !== null ) {
					console.log( `\tffmpeg received signal ${signal} ${videoPath}` );
				}
			} );
		}
	},
	/**
	 * Executed after a Mocha test ends.
	 *
	 * @param {Object} test Mocha Test object
	 */
	afterTest: function ( test ) {
		if ( ffmpeg ) {
			// stop video recording
			ffmpeg.kill( 'SIGINT' );
		}

		// if test passed, ignore, else take and save screenshot
		if ( test.passed ) {
			return;
		}
		// save screenshot
		const screenshotfile = filePath( test, logPath, 'png' );
		browser.saveScreenshot( screenshotfile );
		console.log( '\n\tScreenshot location:', screenshotfile, '\n' );
	}
};

module.exports = exports;
