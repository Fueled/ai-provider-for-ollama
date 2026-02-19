/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
const { visitAdminPage } = require( '../../utils/helpers' );

test.describe( 'Plugin activation', () => {
	test( 'Can deactivate the plugin', async ( { admin, page } ) => {
		await visitAdminPage( admin, 'plugins.php' );
		await page
			.locator( '#deactivate-wordpress-ai-client-provider-for-ollama' )
			.click();
		await expect( page.getByText( 'Plugin deactivated.' ) ).toHaveCount(
			1
		);
	} );

	test( 'Can activate the plugin', async ( { admin, page } ) => {
		await visitAdminPage( admin, 'plugins.php' );
		await page
			.locator( '#activate-wordpress-ai-client-provider-for-ollama' )
			.click();
		await expect( page.getByText( 'Plugin activated.' ) ).toHaveCount( 1 );
	} );

	test( 'Can see Settings links in the plugin action links', async ( {
		admin,
		page,
	} ) => {
		// Visit the plugins page.
		await visitAdminPage( admin, 'plugins.php' );

		// Ensure the Settings link is visible.
		await expect(
			page.locator(
				'tr[data-slug="wordpress-ai-client-provider-for-ollama"] .row-actions a',
				{
					hasText: 'Settings',
				}
			)
		).toHaveCount( 1 );
	} );
} );
