<?php
/**
 * Homepages Tests: Bootstrap File
 *
 * @package Homepages
 * @subpackage Tests
 */

// Load Core's test suite.
$wp_starter_plugin_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_starter_plugin_tests_dir ) {
	$wp_starter_plugin_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $wp_starter_plugin_tests_dir . '/includes/functions.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

/**
 * Setup our environment.
 */
function homepages_manually_load_environment() {
	/*
	 * Tests won't start until the uploads directory is scanned, so use the
	 * lightweight directory from the test install.
	 *
	 * @see https://core.trac.wordpress.org/changeset/29120.
	 */
	add_filter(
		'pre_option_upload_path',
		function () {
			return ABSPATH . 'wp-content/uploads';
		}
	);

	// Load this plugin.
	require_once dirname( __DIR__ ) . '/homepages.php';
}
tests_add_filter( 'muplugins_loaded', 'homepages_manually_load_environment' );

// Include core's bootstrap.
require $wp_starter_plugin_tests_dir . '/includes/bootstrap.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
