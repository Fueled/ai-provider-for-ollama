<?php
/**
 * Bootstrap the PHPUnit tests.
 *
 * @package WordPress\AiClientProviderOllama\Tests
 */

define( 'TESTS_REPO_ROOT_DIR', dirname( __DIR__ ) );

// Load Composer dependencies if applicable.
if ( file_exists( TESTS_REPO_ROOT_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_REPO_ROOT_DIR . '/vendor/autoload.php';
}

$_test_root = getenv( 'WP_TESTS_DIR' );

// Give access to tests_add_filter() function.
require_once $_test_root . '/includes/functions.php';

// Activate the plugin.
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/plugin.php';
	}
);

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';
