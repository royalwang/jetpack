/**
 * External dependencies
 */
import { wrap } from 'lodash';
/**
 * Internal dependencies
 */
import { sendFailedTestScreenshotToSlack, sendFailedTestMessageToSlack } from './reporters/slack';
import { takeScreenshot } from './reporters/screenshot';
import { logHTML, logDebugLog } from './page-helper';
/**
 * WordPress dependencies
 */
import { setBrowserViewport, enablePageDialogAccept } from '@wordpress/e2e-test-utils';
/**
 * Environment variables
 */
const { PUPPETEER_TIMEOUT, E2E_DEBUG, CI, E2E_LOG_HTML } = process.env;
let currentBlock;

const defaultErrorHandler = async ( error, name ) => {
	// If running tests in CI
	if ( CI ) {
		const filePath = await takeScreenshot( currentBlock, name );
		await sendFailedTestMessageToSlack( { block: currentBlock, name, error } );
		await sendFailedTestScreenshotToSlack( filePath );
		await logDebugLog();
	}

	if ( E2E_LOG_HTML ) {
		logHTML();
	}

	if ( E2E_DEBUG ) {
		console.log( error );
		await jestPuppeteer.debug();
	}

	throw error;
};

/**
 * Wrapper around `beforeAll` to be able to handle thrown exceptions within the hook.
 * Main reason is to be able to universaly capture screenshots on puppeteer exceptions.
 *
 * @param {*} callback
 * @param {*} errorHandler
 */
export const catchBeforeAll = async ( callback, errorHandler = defaultErrorHandler ) => {
	beforeAll( async () => {
		try {
			await callback();
		} catch ( error ) {
			await errorHandler( error, 'beforeAll' );
		}
	} );
};

async function setupBrowser() {
	const userAgent = await browser.userAgent();
	await page.setUserAgent( userAgent + ' wp-e2e-tests' );
	await setBrowserViewport( 'large' );
}

function setupConsoleLogs() {
	page.on( 'pageerror', function( err ) {
		const theTempValue = err.toString();
		console.log( 'Page error: ' + theTempValue );
	} );
	page.on( 'error', function( err ) {
		const theTempValue = err.toString();
		console.log( 'Error: ' + theTempValue );
	} );
}

// The Jest timeout is increased because these tests are a bit slow
jest.setTimeout( PUPPETEER_TIMEOUT || 300000 );
if ( E2E_DEBUG ) {
	jest.setTimeout( 2147483647 ); // max 32-bit signed integer
}

// Use wrap to preserve all previous `wrap`s
jasmine.getEnv().describe = wrap( jasmine.getEnv().describe, ( func, ...args ) => {
	try {
		currentBlock = args[ 0 ];
		func( ...args );
	} catch ( e ) {
		throw e;
	}
} );

/**
 * Override the test case method so we can take screenshots of assertion failures.
 *
 * See: https://github.com/smooth-code/jest-puppeteer/issues/131#issuecomment-469439666
 *
 * @param {string} name
 * @param {Function} func
 */
global.it = async ( name, func ) => {
	return await test( name, async () => {
		try {
			await func();
		} catch ( error ) {
			await defaultErrorHandler( error, name );
		}
	} );
};

jasmine.getEnv().addReporter( {
	specStarted( result ) {
		console.log( `Spec name: ${ result.fullName }, description: ${ result.description }` );
		jasmine.currentTest = result;
	},
	specDone: result => ( jasmine.currentTest = result ),
} );

// Before every test suite run, delete all content created by the test. This ensures
// other posts/comments/etc. aren't dirtying tests and tests don't depend on
// each other's side-effects.
beforeAll( async () => {
	await setupBrowser();

	// Handles not saved changed dialog in block editor
	await enablePageDialogAccept();
	setupConsoleLogs();
} );

afterEach( async () => {
	await setupBrowser();
} );
