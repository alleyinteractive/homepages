<?php
/**
 * Homepages Tests: Bootstrap File
 *
 * @package Homepages
 * @subpackage Tests
 */

/**
 * Visit {@see https://mantle.alley.com/testing/test-framework.html} to learn more.
 */
\Mantle\Testing\manager()
	// Rsync the plugin to plugins/homepages when testing.
	->maybe_rsync_plugin()
	// Set up custom filters and actions for theme testing.
	->loaded(
		function () {
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
		}
	)
	// Load the main file of the plugin.
	->loaded( fn() => require_once __DIR__ . '/../homepages.php' )
	->install();
