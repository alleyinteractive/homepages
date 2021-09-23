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

		// Modifications for static homepages.
		if ( $this->static_front_page_enabled() ) {
			add_filter( 'posts_results', [ $this, 'shortcircuit_posts_results' ], 10, 2 );
			add_filter( 'pre_get_document_title', [ $this, 'update_doc_title' ] );
		} else {
			add_action( 'parse_query', [ $this, 'update_main_query' ] );
		}

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
	 * Determine if the static homepage setting is enabled.
	 *
	 * @return boolean True if the static homepage setting is enabled, otherwise false.
	 */
	public function static_front_page_enabled() : bool {
		return 'page' === get_option( 'show_on_front' );
	}

	/**
	 * Check if the site admin has set a static homepage.
	 *
	 * @param \WP_Query $wp_query The query object.
	 * @return boolean True if this is the static homepage, otherwise false.
	 */
	public function is_static_front_page( $wp_query ) : bool {
		if (
			! is_admin()
			&& $this->static_front_page_enabled()
			&& absint( $wp_query->get( 'page_id' ) ) === absint( get_option( 'page_on_front' ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Filters the posts results array to ensure that the latest homepage is used
	 * when the static homepage option is enabled.
	 * 
	 * @param array     $post_results The posts results.
	 * @param \WP_Query $wp_query The query object.
	 * @return array $posts_resuts The posts results.
	 */
	public function shortcircuit_posts_results( $post_results, $wp_query ) {
		if ( ! $this->is_static_front_page( $wp_query ) ) {
			return $post_results;
		}

		/**
		 * Filter whether or not this plugin will modify the posts results.
		 *
		 * @param bool Disable homepage from modifying the posts results.
		 */
		if ( ! apply_filters( 'homepages_modify_post_results', true ) ) {
			return $post_results;
		}

		// Ensure is_home is set.
		$wp_query->is_home     = true;
		$wp_query->is_page     = false;
		$wp_query->is_singular = false;

		// Get the latest homepage ID.
		remove_filter( 'posts_results', [ $this, 'shortcircuit_posts_results' ], 10, 2 );
		$latest_homepage_id = $this->get_latest_homepage_id();
		add_filter( 'posts_results', [ $this, 'shortcircuit_posts_results' ], 10, 2 );

		// Force the post results to be the latest homepage.
		if ( ! empty( $latest_homepage_id ) ) {
			return [ get_post( $latest_homepage_id ) ];
		}

		return $post_results;
	}

	/**
	 * Fitlers the document title on the static front page.
	 * 
	 * @param string $title The document title.
	 * @return string The document title.
	 */
	public function update_doc_title( $title ) {
		global $wp_query;
		if ( ! $this->is_static_front_page( $wp_query ) ) {
			return $title;
		}

		/**
		 * Filter whether or not this plugin will modify the document title.
		 *
		 * @param bool Disable homepage from modifying the document title.
		 */
		if ( ! apply_filters( 'homepages_modify_document_title', true ) ) {
			return $title;
		}

		// Get the front page.
		$front_page = get_post( get_option( 'page_on_front', 0 ) );

		if ( $front_page instanceof \WP_Post ) {
			return $front_page->post_title;
		}

		return $title;
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
			&& $wp_query->is_home()
		) {
			$wp_query->set( 'post_type', $this->post_type );
			$wp_query->set( 'posts_per_page', 1 );
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
			'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
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
				$wp_query->is_home = ( 'posts' === get_option( 'show_on_front' ) );
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
}
