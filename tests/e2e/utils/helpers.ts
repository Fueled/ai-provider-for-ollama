/**
 * WordPress dependencies
 */
import { type Admin } from '@wordpress/e2e-test-utils-playwright';

/**
 * Visits a specific admin page.
 *
 * @param admin The admin fixture from the test context.
 * @param path  The path to the admin page.
 */
export const visitAdminPage = async ( admin: Admin, path: string ) => {
	await admin.visitAdminPage( path );
};

/**
 * Visits the settings page.
 *
 * @param admin The admin fixture from the test context.
 */
export const visitSettingsPage = async ( admin: Admin ) => {
	await admin.visitAdminPage( 'options-general.php?page=wp-ai-client-ollama' );
};
