<?php
/**
 * Contains logic to create the homepages post type and setup queries.
 *
 * @package Homepages
 */

namespace Homepages;

/**
 * Homepages class.
 */
class Homepages {

	/**
	 * Homepage post type slug.
	 *
	 * @var string
	 */
	private $post_type = 'homepage';

	/**
	 * Setup the instance.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'create_post_type' ] );
		add_action( 'wp', [ $this, 'update_homepage_query_conditionals' ] );
		add_action( 'wp', [ $this, 'set_404_on_pagination' ] );

		add_action( 'save_post', [ $this, 'clear_homepage_cache' ] );

		add_action( 'parse_query', [ $this, 'update_main_query' ] );
		add_filter( "rest_{$this->post_type}_query", [ $this, 'rest_only_expose_latest_homepage' ] );
		add_filter( 'rest_pre_dispatch', [ $this, 'prevent_paginated_rest_requests' ], 10, 3 );

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'transition_post_status', [ $this, 'add_has_published_homepage_option' ], 10, 3 );
	}

	/**
	 * Check if we have at least one published homepage.
	 *
	 * @return bool
	 */
	public function has_homepage(): bool {
		return (bool) get_option( 'has_published_homepage', false );
	}

	/**
	 * Updates the main query object to use the custom homepage when available.
	 *
	 * @param \WP_Query $wp_query The query object.
	 */
	public function update_main_query( $wp_query ) {
		$modify_main_query = $this->has_homepage();

		/**
		 * Filter whether or not this plugin will modify the main query on the
		 * homepage.
		 *
		 * @param bool Disable homepage from modifying the query.
		 */
		if ( ! apply_filters( 'homepages_modify_main_query', $modify_main_query ) ) {
			return;
		}

		if (
			! is_admin()
			&& $wp_query->is_main_query()
			&& $wp_query->is_home
		) {
			$wp_query->set( 'post_type', $this->post_type );
			$wp_query->set( 'posts_per_page', 1 );
		}
	}

	/**
	 * Ensures that the REST API endpoint for homepages only returns the latest
	 * homepage.
	 * 
	 * @param array $args The query args.
	 * @return array $args The query args.
	 */
	public function rest_only_expose_latest_homepage( $args ) {
		// Always return the latest homepage.
		$args['posts_per_page'] = 1;

		return $args;
	}

	/**
	 * Prevent paginated requests to the API that can expose older homepages.
	 * 
	 * @param mixed            $result  Response to replace the requested version
	 *                                  with. Can be anything a normal endpoint
	 *                                  can return, or null to not hijack the request.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 */
	public function prevent_paginated_rest_requests( $result, $server, $request ) {
		// Bail if this is not a homepages endpoint.
		if ( $request->get_route() !== '/wp/v2/homepage' ) {
			return $result;
		}

		$params = $request->get_params();

		// Return nothing for paged requests.
		if ( ! empty( $params['page'] ) && absint( $params['page'] ) > 1 ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'homepages' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $result;
	}

	/**
	 * Create the custom post type.
	 */
	public function create_post_type() {

		$args = [
			'labels' => [
				'name'                  => __( 'Homepages', 'homepages' ),
				'singular_name'         => __( 'Homepage', 'homepages' ),
				'add_new'               => __( 'Add New Homepage', 'homepages' ),
				'add_new_item'          => __( 'Add New Homepage', 'homepages' ),
				'edit_item'             => __( 'Edit Homepage', 'homepages' ),
				'new_item'              => __( 'New Homepage', 'homepages' ),
				'view_item'             => __( 'View Homepage', 'homepages' ),
				'view_items'            => __( 'View Homepages', 'homepages' ),
				'search_items'          => __( 'Search Homepages', 'homepages' ),
				'not_found'             => __( 'No homepages found', 'homepages' ),
				'not_found_in_trash'    => __( 'No homepages found in Trash', 'homepages' ),
				'parent_item_colon'     => __( 'Parent Homepage:', 'homepages' ),
				'all_items'             => __( 'All Homepages', 'homepages' ),
				'archives'              => __( 'Homepage Archives', 'homepages' ),
				'attributes'            => __( 'Homepage Attributes', 'homepages' ),
				'insert_into_item'      => __( 'Insert into Homepage', 'homepages' ),
				'uploaded_to_this_item' => __( 'Uploaded to this Homepage', 'homepages' ),
				'filter_items_list'     => __( 'Filter Homepage list', 'homepages' ),
				'items_list_navigation' => __( 'Homepages list navigation', 'homepages' ),
				'items_list'            => __( 'Homepages list', 'homepages' ),
				'menu_name'             => __( 'Homepages', 'homepages' ),
			],
			'public'              => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_rest'        => true,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-admin-home',
			'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
		];

		register_post_type( $this->post_type, $args );
	}

	/**
	 * Updates the main \WP_Query object conditionals for single homepages.
	 */
	public function update_homepage_query_conditionals() {
		global $wp_query;

		if ( is_admin() || ! is_singular( $this->post_type ) ) {
			return;
		}

		// Ensure all single homeapges 404 for any user that is not logged in.
		if ( ! is_user_logged_in() ) {
			$wp_query->set_404();
		} else {
			$wp_query->is_home = true;
		}
	}

	/**
	 * Sets any paginated page to a 404.
	 * 
	 * Only the latest homepage should be public and any other homepages should 
	 * be private. This occurs when navigating to a URL like `/page/2` where the
	 * main query will attempt to get the second published homepage. 
	 */
	public function set_404_on_pagination() {
		global $wp_query;

		if (
			! is_admin()
			&& $wp_query->is_main_query() // Ensure we only alter the main query.
			&& is_home()
			&& is_paged()
		) {
			$wp_query->set_404();
		}
	}

	/**
	 * Get the latest homepage ID.
	 *
	 * @return int $homepage_id The latest homepage ID.
	 */
	public function get_latest_homepage_id() {
		// Get the previewed homepage.
		if ( is_preview() && isset( $_GET['p'] ) ) {
			return absint( $_GET['p'] );
		}

		$cache_key   = 'homepage_latest_id';
		$homepage_id = get_transient( $cache_key );

		// We have a cache hit, so lets use that.
		if ( false !== $homepage_id ) {
			return (int) $homepage_id;
		}

		$homepage_query = new \WP_Query(
			[
				'post_type'      => $this->post_type,
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);

		if ( ! empty( $homepage_query->posts[0] ) && $homepage_query->posts[0] instanceof \WP_Post ) {
			$homepage_id = $homepage_query->posts[0]->ID;
		}

		// Cast to an int.
		$homepage_id = absint( $homepage_id );

		// Save this to the cache.
		set_transient( $cache_key, $homepage_id, 15 * MINUTE_IN_SECONDS );

		return (int) $homepage_id;
	}

	/**
	 * Clear the homepage cache when a homepage is saved.
	 *
	 * @param int $post_id The current post ID.
	 */
	public function clear_homepage_cache( $post_id ) {
		if ( get_post_type( $post_id ) === $this->post_type ) {
			delete_transient( 'homepage_latest_id' );
		}
	}

	/**
	 * Save the `has_published_homepage` option after a homepage is published.
	 *
	 * @param string   $new  New Post Status.
	 * @param string   $old  Old Post Status.
	 * @param \WP_Post $post Post Object.
	 */
	public function add_has_published_homepage_option( $new, $old, $post ) {
		if (
			'publish' === $new
			&& 'publish' !== $old
			&& $post->post_type === $this->post_type
		) {
			\update_option( 'has_published_homepage', true );
		}
	}

	/**
	 * Display admin notices if the site is not configured properly.
	 */
	public function admin_notices() {
		// This plugin assumes that the homepage is set to display the latest posts.
		// If this is not set then the plugin will not work.
		if ( 'posts' !== get_option( 'show_on_front' ) ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Homepages will only work when the site is set to display the latest posts on the homepage. Please update this setting', 'homepages' ); ?> <a href="<?php echo esc_url( admin_url( '/options-reading.php' ) ); ?>"><?php esc_html_e( 'here', 'homepages' ); ?></a>.</p>
			</div>
			<?php
		}
	}
}
