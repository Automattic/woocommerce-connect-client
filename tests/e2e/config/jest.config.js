/**
 * @flow strict
 * @format
 */
const path = require( 'path' );
const { jestConfig: baseE2Econfig } = require( '@woocommerce/e2e-environment' );

// https://jestjs.io/docs/en/configuration.html

module.exports = {
	...baseE2Econfig,
	// Automatically clear mock calls and instances between every test
	clearMocks: true,

	// An array of file extensions your modules use
	moduleFileExtensions: [ 'js' ],

	preset: 'jest-puppeteer',

	// Where to look for test files
	// roots: [ '<rootDir>/specs' ],
	roots: [ path.resolve( __dirname, '../specs' ) ],

	//setupFiles: [ '<rootDir>/.node_modules/regenerator-runtime/runtime' ],

	// A list of paths to modules that run some code to configure or set up the testing framework
	// before each test
	// setupFilesAfterEnv: [
	// 	'<rootDir>jest.setup.js',
	// 	'<rootDir>jest.test.failure.js',
	// 	'expect-puppeteer',
	// ],

	// The glob patterns Jest uses to detect test files
	testMatch: [ '**/*.(test|spec).js' ],
};
