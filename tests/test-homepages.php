<?php
/**
 * Test file for Homepages class.
 *
 * @package Homepages
 */

namespace Homepages;

class Homepages_Tests extends \WP_UnitTestCase {
	function test_get_latest_homepage_id() {
		// Without a homepage created we will expect nothing to be returned.
		$this->assertEquals( get_latest_homepage_id(), 0 );

		// Create a new homepage.
		$homepage_id = self::factory()->post->create(
			[
				'post_type' => 'homepage',
			]
		);
		$this->assertEquals( get_latest_homepage_id(), $homepage_id );

		// Create another homepage.
		$another_homepage_id = self::factory()->post->create(
			[
				'post_type' => 'homepage',
			]
		);
		$this->assertEquals( get_latest_homepage_id(), $another_homepage_id );

		// Create draft homepage and ensure it is not used.
		$draft_homepage_id = self::factory()->post->create(
			[
				'post_type'   => 'homepage',
				'post_status' => 'draft',
			]
		);
		$this->assertEquals( get_latest_homepage_id(), $another_homepage_id );
	}

	function test_update_is_home_conditional() {
		// Create a new homepage.
		$homepage_id = self::factory()->post->create(
			[
				'post_type' => 'homepage',
			]
		);

		// Go to this page as a logged out user.
		$this->go_to( get_permalink( $homepage_id ) );

		// Ensure we have the proper conditionals set.
		$this->assertFalse( is_home() );

		// Login a user.
		$admin_id = self::factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		wp_set_current_user( $admin_id );

		$this->go_to( get_permalink( $homepage_id ) );

		// Ensure we have the proper conditionals set.
		$this->assertTrue( is_home() );
	}

	function test_redirect_to_404() {
		// Create a new homepage.
		$homepage_id = self::factory()->post->create(
			[
				'post_type' => 'homepage',
			]
		);

		$this->go_to( get_permalink( $homepage_id ) );

		// Ensure we have the proper conditionals set.
		$this->assertTrue( is_404() );
	}
}
