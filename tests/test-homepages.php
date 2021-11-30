<?php
/**
 * Test file for Homepages class.
 *
 * @package Homepages
 */

namespace Homepages;

class Homepages_Tests extends \WP_UnitTestCase {
	/**
	 * Holds REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $wp_rest_server;

	/**
	 * Maps REST server, builds subject class and hooks init.
	 */
	public function setUp() {
		parent::setUp();

		if ( ! $this->wp_rest_server ) {
			$this->wp_rest_server = rest_get_server();
		}

		do_action( 'rest_api_init' );
	}

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

	function test_update_homepage_query_conditionals() {
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

	function test_not_set_404_on_pagination() {
		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		// Go to the homepage.
		$this->go_to( home_url() );

		global $wp_query;

		// Ensure we have the proper conditionals set.
		$this->assertTrue( is_home() );
		$this->assertFalse( is_404() );
		$this->assertEquals( $homepage_ids[9], $wp_query->posts[0]->ID );
	}

	function test_set_404_on_pagination() {
		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		// No pagination.
		$this->go_to( home_url() );
		$this->assertFalse( is_404() );

		// Pagination.
		$this->go_to( home_url( '?paged=2' ) );
		$this->assertTrue( is_404() );
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

	function test_rest_api_latest_homepage() {
		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		$request = new \WP_REST_Request( 'GET', "/wp/v2/homepage" );
		$response = $this->wp_rest_server->dispatch( $request );

		$this->assertEquals( 1, count( $response->data ) );
		$this->assertEquals( $homepage_ids[9], $response->data[0]['id'] );
	}

	function test_rest_api_prevent_paginated_requests() {
		// Create homepages.
		$homepage_ids = self::factory()->post->create_many(
			10,
			[
				'post_type' => 'homepage',
			]
		);

		$request = new \WP_REST_Request( 'GET', "/wp/v2/homepage" );
		$request->set_param( 'page', 2 );
		$response = $this->wp_rest_server->dispatch( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'rest_forbidden', $response->get_error_code() );
		
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertEquals( 401, $data['status'] );
	}
}
