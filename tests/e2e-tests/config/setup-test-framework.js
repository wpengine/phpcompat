/**
 * Forked from Gutenberg, with only minor changes.
 *
 * @see https://github.com/WordPress/gutenberg/blob/df2b096185818f89b2c1668d22a848990acb2799/packages/e2e-tests/config/setup-test-framework.js
 */

 /**
  * WordPress dependencies
  */
 import {
	 setBrowserViewport,
	 switchUserToAdmin,
	 switchUserToTest,
	 visitAdminPage,
 } from '@wordpress/e2e-test-utils';
 import { addQueryArgs } from '@wordpress/url';
 
 /**
  * Timeout, in seconds, that the test should be allowed to run.
  *
  * @type {string|undefined}
  */
 export const PUPPETEER_TIMEOUT = process.env.PUPPETEER_TIMEOUT;
 
 /**
  * Set of console logging types observed to protect against unexpected yet
  * handled (i.e. not catastrophic) errors or warnings. Each key corresponds
  * to the Puppeteer ConsoleMessage type, its value the corresponding function
  * on the console global object.
  *
  * @type {Object<string,string>}
  */
 export const OBSERVED_CONSOLE_MESSAGE_TYPES = {
	 warning: 'warn',
	 error: 'error',
 };
 
 export const PLUGIN = 'wpe-rest-block';
 
 /**
  * Array of page event tuples of [ eventName, handler ].
  *
  * @type {Array}
  */
 export const pageEvents = [];
 
 // The Jest timeout is increased because these tests are a bit slow
 jest.setTimeout(PUPPETEER_TIMEOUT || 100000);
 
 export async function setupBrowser() {
	 console.log("Seting up Browser");
	 await setBrowserViewport('large');
 }
 
 /**
  * Navigates to the post listing screen and bulk-trashes any posts which exist.
  *
  * @param {string} postType - String slug for type of post to trash.
  *
  * @return {Promise} Promise resolving once posts have been trashed.
  */
 export async function trashExistingPosts(postType = 'post') {
	 console.log('Trashing Existing Posts');
	 await switchUserToAdmin();
	 // Visit `/wp-admin/edit.php` so we can see a list of posts and delete them.
	 const query = addQueryArgs('', {
		 post_type: postType,
	 }).slice(1);
	 await visitAdminPage('edit.php', query);
 
	 // If this selector doesn't exist there are no posts for us to delete.
	 const bulkSelector = await page.$('#bulk-action-selector-top');
	 if (!bulkSelector) {
		 return;
	 }
 
	 // Select all posts.
	 await page.waitForSelector('[id^=cb-select-all-]');
	 await page.click('[id^=cb-select-all-]');
	 // Select the "bulk actions" > "trash" option.
	 await page.select('#bulk-action-selector-bottom', 'trash');
	 // Submit the form to send all draft/scheduled/published posts to the trash.
	 await page.click('#doaction2');
	 await page.waitForXPath(
		 '//*[contains(@class, "updated notice")]/p[contains(text(), "moved to the Trash.")]'
	 );
	 await switchUserToTest();
 }
 
 /**
  * Adds an event listener to the page to handle additions of page event
  * handlers, to assure that they are removed at test teardown.
  */
 export function capturePageEventsForTearDown(pageEvents) {
	 console.log('Capturing Page Events')
	 page.on('newListener', (eventName, listener) => {
		 pageEvents.push([eventName, listener]);
	 });
 }
 
 /**
  * Removes all bound page event handlers.
  */
 export function removePageEvents(pageEvents) {
	 pageEvents.forEach(([eventName, handler]) => {
		 page.removeListener(eventName, handler);
	 });
 }