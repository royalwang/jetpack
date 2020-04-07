/**
 * For a detailed explanation of configuration properties, visit:
 * https://jestjs.io/docs/en/configuration.html
 */

module.exports = {
	preset: 'jest-puppeteer',
	globalTeardown: './lib/global-teardown.js',
	setupFiles: [ '<rootDir>/lib/setup.js' ],
	setupFilesAfterEnv: [
		'<rootDir>/lib/setup-env.js',
		'<rootDir>/lib/jest.test.failure.js',
		'expect-puppeteer',
	],
};
