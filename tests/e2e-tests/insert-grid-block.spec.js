import { createNewPost, enablePageDialogAccept, insertBlock } from '@wordpress/e2e-test-utils';

describe('WPE Blocks E2E', () => {
	beforeAll(async () => {
		await enablePageDialogAccept();

		jest.setTimeout(process.env.PUPPETEER_TIMEOUT || 1000000);
	});

	beforeEach(async () => {
		await createNewPost()
		await insertBlock("WP Engine Dynamic Post Grid");
	})

	it("should add the block to the page", async () => {
		const el = await page.$('[data-type="wpe-rest-block/wpe-dynamic-post-grid"]');
		expect(el).not.toBeNull();
	});

	it('should display multiple posts', async () => {
		const el = await page.$$('.wpe-dynamic-post-grid-block__list article');
		expect(el.length).toBeGreaterThan(0)
	})
});