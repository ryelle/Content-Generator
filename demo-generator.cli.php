<?php

/**
 * The demo-generator command, a container for site creation & population.
 */
class Demo_Generator extends WP_CLI_Command {
	/**
	 * Populate a site in a network with content from wikipedia and images from pexels.
	 *
	 * ## OPTIONS
	 *
	 * --<field>=<value>
	 * : Number of posts, pages, etc to create, in format: --<post_type>=10
	 *
	 * --from=<category>
	 * : Wikipedia category to pull content from
	 *
	 * [--with-images=<all|most|some|few|none>]
	 * : How many posts should have featured images.
	 * Determined by the following chances:
	 *    all:  100%
	 *    most: 75%
	 *    some: 50%
	 *    few:  25%
	 *    none: 0%
	 *
	 * [--images-from=<category>]
	 * : Category on pexels used to pull images, if not defined this'll default to
	 * the Wiki category.
	 *
	 * [--replace]
	 * : If used, the command will wipe the site first
	 */
	function populate( $args, $assoc_args ) {
		global $wp_taxonomies;

		$api = Demo_Gen_API::get_instance();

		$wiki_cat = $assoc_args['from'];
		$pexel_cat = isset( $assoc_args['images-from'] ) ? $assoc_args['images-from']: $wiki_cat;
		$add_image = $api->parse_image_chance( $assoc_args );

		// Collect our post type counts
		$post_types = array();
		$supports_images = get_theme_support( 'post-thumbnails' );
		if ( is_array( $supports_images ) ) {
			$supports_images = $supports_images[0];
		}

		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ){
			if ( isset( $assoc_args[ $post_type ] ) ) {
				$post_types[ $post_type ] = $assoc_args[ $post_type ];

				// Add a warning if we're trying to add images to a type that doesn't support them.
				if ( true === $supports_images || ! $add_image ) { // All types support images, or not adding images.
					continue;
				}
				if ( ! in_array( $post_type, $supports_images ) ) {
					WP_CLI::warning( "Your current theme does not support featured images on post type '$post_type'." );
				}
			}
		}

		if ( isset( $assoc_args['replace'] ) && $assoc_args['replace'] ) {
			foreach ($post_types as $post_type => $num ) {
				$ids = WP_CLI::launch_self( "post list", array(), array( 'post_type' => $post_type, 'format' => 'ids' ), false, true );
				if ( 0 == $ids->return_code && $ids->stdout ) {
					$delete = WP_CLI::launch_self( "post delete", explode( ' ', $ids->stdout ), array( 'force' => true ), false );
					WP_CLI::line( "Deleted existing {$post_type}s.");
				}
			}
		}

		// How many articles should we request from Wikipedia?
		$total_articles = array_sum( $post_types );

		// Set up default post
		$post = array(
			'post_content'   => '',
			'post_title'     => '',
			'post_status'    => 'publish',
			'post_type'      => 'post',
			'post_author'    => get_current_user_id(),
			'ping_status'    => 'closed',
		);
		$post = apply_filters( 'demo_gen_default_post', $post );

		// Pull articles
		$article_list = $api->get_article_list( $wiki_cat, $total_articles );
		if ( is_wp_error( $article_list ) ) {
			WP_CLI::error( $article_list->get_error_message() );
		}

		// Un-alphabetize the list
		shuffle( $article_list );

		if ( count( $article_list ) < $total_articles ) {
			WP_CLI::warning( sprintf( __( "Only %s articles were found in this category.", 'demo-gen' ), count( $article_list ) ) );
		}

		// Pull images
		$image_list = array();
		if ( 0 !== $add_image ) {
			$image_list = $api->get_image_list( $pexel_cat, $total_articles );
			if ( is_wp_error( $image_list ) ){
				WP_CLI::warning( $image_list->get_error_message() );
			}
		}

		foreach ( $article_list as $title ) {
			foreach ( $post_types as $post_type => $count ) {
				if ( $count > 0 ) {
					$post_types[ $post_type ]--;
					break;
				}
			}

			$response = $api->get_article_response( $title );
			if ( is_wp_error( $response ) ) {
				WP_CLI::warning( $response->get_error_message() );
				continue;
			}

			$text = $api->get_article_text( $response );
			if ( is_wp_error( $text ) ) {
				WP_CLI::warning( $text->get_error_message() );
				continue;
			}

			$post['post_content']  = $text;
			$post['post_title']    = $title;
			$post['post_type']     = $post_type;
			$post['post_date']     = $api->random_date();

			// Check that we're handling a post type that supports categories.
			if ( isset( $wp_taxonomies['category'] ) && in_array( $post_type, $wp_taxonomies['category']->object_type ) ) {
				$categories = $api->get_article_cats( $response );
				if ( is_wp_error( $categories ) ) {
					WP_CLI::warning( $categories->get_error_message() );
					continue;
				}
				$post['post_category'] = $categories;
			}

			// $post['post_excerpt'] = '';

			// Can be ID or WP_Error.
			$post_id = wp_insert_post( $post, true );

			if ( is_wp_error( $post_id ) ) {
				// This shouldn't error, we should break and investigate.
				WP_CLI::error( $post_id->get_error_message() );
			}

			// If the random number is less than the threshold, add an image.
			if ( ( ! is_wp_error( $image_list ) ) && ( mt_rand( 0, 100 ) <= $add_image ) ) {
				$key = array_rand( $image_list );
				$attachment = $api->set_image( $image_list[ $key ], $post_id );
				set_post_thumbnail( $post_id, $attachment );
				unset( $image_list[ $key ] );
			}

			WP_CLI::line( sprintf( "Created %s [%s], from %s", $post_type, $post_id, $title ) );
		}
	}
}

WP_CLI::add_command( 'demo', 'Demo_Generator' );
