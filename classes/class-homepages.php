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
		add_action( 'wp', [ $this, 'update_is_home_conditional' ] );

		add_action( 'save_post', [ $this, 'clear_homepage_cache' ] );

		add_action( 'init', [ $this, 'has_published_homepage_filter' ] );

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'transition_post_status', [ $this, 'add_has_published_homepage_option' ], 10, 3 );
	}

	/**
	 * Only change the main query if we have at least one published homepage.
	 */
	public function has_published_homepage_filter() {
		if ( $this->has_homepage() ) {
			add_action( 'parse_query', [ $this, 'update_main_query' ] );
		}
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
		/**
		 * Filter whether or not this plugin will modify the main query on the
		 * homepage.
		 *
		 * @param bool Disable homepage from modifying the query.
		 */
		if ( ! apply_filters( 'homepages_modify_main_query', true ) ) {
			return;
		}

		if (
			! is_admin()
			&& $wp_query->is_main_query()
			&& $wp_query->is_home
		) {
			$wp_query->set( 'post_type', $this->post_type );
			$wp_query->set( 'posts_per_page', 1 );
			$wp_query->set( 'post_status', 'publish' );
		}
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
			'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions' ],
		];

		register_post_type( $this->post_type, $args );
	}

	/**
	 * When viewing a homepage, update the query to have `is_home` set to true.
	 */
	public function update_is_home_conditional() {
		global $wp_query;

		if ( is_singular( $this->post_type ) ) {

			if ( ! is_user_logged_in() ) {
				$wp_query->set_404();
			} else {
				$wp_query->is_home = true;
			}
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
				'post_status'    => 'publish',
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
		if ( ( $new === 'publish' ) && ( $old !== 'publish' ) && ( $post->post_type === $this->post_type ) ) {
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
