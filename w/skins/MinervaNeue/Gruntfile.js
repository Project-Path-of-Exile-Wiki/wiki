/* eslint-env node */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'skin.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-notify' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true
			},
			all: [
				'**/*.{js,json}',
				'!docs/**',
				'!libs/**',
				'!node_modules/**',
				'!vendor/**',
				'!tests/resource-loader-bundlesize.js'
			]
		},
		stylelint: {
			all: [
				'**/*.{css,less}',
				// TODO: Nested imports cause stylelint to crash
				'!resources/skins.minerva.base.styles/print/styles.less',
				'!docs/**',
				'!libs/**',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		// eslint-disable-next-line es/no-object-assign
		banana: Object.assign( {
			options: { requireLowerCase: false }
		}, conf.MessagesDirs ),
		watch: {
			lint: {
				files: [ '{resources,tests/qunit}/**/*.{js,less}' ],
				tasks: [ 'lint' ]
			},
			scripts: {
				files: [ '{resources,tests/qunit}/**/*.js' ],
				tasks: [ 'test' ]
			},
			configFiles: {
				files: [ 'Gruntfile.js' ],
				options: {
					reload: true
				}
			}
		}
	} );

	grunt.registerTask( 'lint', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'test', [ 'lint' ] );

	grunt.registerTask( 'default', [ 'test' ] );
};
