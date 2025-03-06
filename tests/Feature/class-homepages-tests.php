<?php
/**
 * Test file for Homepages class.
 *
 * @package Homepages
 */

namespace Homepages;

use Mantle\Testkit\Test_Case;
use Mantle\Testing\Concerns\Makes_Http_Requests;

/**
 * Test suite for the Homepages functionality.
 */
class Homepages_Tests extends Test_Case {
	use Makes_Http_Requests;

	/**
	 * Set up the test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Make sure the REST API is initialized.
		do_action( 'homepages_before_test_rest_api_init' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core hook.
		do_action( 'rest_api_init' );
		do_action( 'homepages_after_test_rest_api_init' );
	}

	/**
	 * Test get_latest_homepage_id function.
	 *
	 * @return void
	 */
	public function test_get_latest_homepage_id(): void {
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

	/**
	 * Test update_homepage_query_conditionals function.
	 *
	 * @return void
	 */
	public function test_update_homepage_query_conditionals(): void {
		// Create a new homepage.
		$homepage_id = self::factory()->post->create(
			[
				'post_type' => 'homepage',
			]
		);

		// Visit this page as a logged out user using Mantle's request method.
		$this->get( get_permalink( $homepage_id ) );

		// Ensure we have the proper conditionals set.
		$this->assertFalse( is_home() );

		// Login a user with Mantle's actingAs method.
		$admin = $this->factory()->user->create_and_get(
			[
				'role' => 'administrator',
			]
		);

		$this->actingAs( $admin );

		// Visit the page again.
		$this->get( get_permalink( $homepage_id ) );

		// Ensure we have the proper conditionals set.
		$this->assertTrue( is_home() );
	}

	/**
	 * Test not_set_404_on_pagination functionality.
	 *
	 * @return void
	 */
	public function test_not_set_404_on_pagination(): void {
		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		// Use Mantle's request method instead of go_to.
		$this->get( '/' );

		// Ensure we have the proper conditionals set.
		$this->assertTrue( is_home() );
		$this->assertFalse( is_404() );

		// Get the latest homepage post.
		$latest_homepage = get_posts( [
			'post_type'      => 'homepage',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		// Verify it's the expected post.
		$this->assertCount( 1, $latest_homepage );
		$this->assertEquals( $homepage_ids[9], $latest_homepage[0]->ID );
	}

	/**
	 * Test set_404_on_pagination functionality.
	 *
	 * @return void
	 */
	public function test_set_404_on_pagination(): void {
		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		// No pagination - use Mantle's request method.
		$this->get( '/' );
		$this->assertFalse( is_404() );

		// Pagination - use Mantle's request method.
		$this->get( '/?paged=2' );
		$this->assertTrue( is_404() );
	}

	/**
	 * Test redirect_to_404 functionality.
	 *
	 * @return void
	 */
	public function test_redirect_to_404(): void {
		// Create a new homepage.
		$homepage_id = self::factory()->post->create(
			[
				'post_type' => 'homepage',
			]
		);

		// Use Mantle's request method.
		$this->get( get_permalink( $homepage_id ) );

		// Ensure we have the proper conditionals set.
		$this->assertTrue( is_404() );
	}

	/**
	 * Test REST API latest homepage retrieval.
	 *
	 * @return void
	 */
	public function test_rest_api_latest_homepage(): void {
		global $wp_rest_server;

		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		// Create a REST request.
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/homepage' );
		$response = $wp_rest_server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( $homepage_ids[9], $data[0]['id'] );
	}

	/**
	 * Test REST API pagination restriction.
	 *
	 * @return void
	 */
	public function test_rest_api_prevent_paginated_requests(): void {
		global $wp_rest_server;

		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		// Create a REST request with pagination.
		$request = new \WP_REST_Request( 'GET', '/wp/v2/homepage' );
		$request->set_param( 'page', 2 );
		$response = $wp_rest_server->dispatch( $request );

		// Get the response status.
		$status = $response->get_status();

		// Check that it returns the expected status code.
		$this->assertEquals( 401, $status );

		// Verify the response data has the expected error code.
		$data = $response->get_data();
		$this->assertEquals( 'rest_forbidden', $data['code'] ?? '' );
	}
}
