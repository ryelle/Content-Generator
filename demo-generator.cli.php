<?php
if ( ! defined('WP_CLI') || ! WP_CLI ) {
	return;
}

/**
 * The demo-generator command, a container for site creation & population.
 */
class Demo_Generator extends WP_CLI_Command {
	const API_URL = 'https://en.wikipedia.org/w/api.php';

	/**
	 * Populate a site in a network with content from wikipedia and images from pexels.
	 *
	 * ## OPTIONS
	 *
	 * <site>
	 * : The url or ID of a site in the network to fill
	 *
	 * --<field>=<value>
	 * : Number of posts, pages, etc to create, in format: --post_type=10
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

		$site = $args[0];
		$wiki_cat = $assoc_args['from'];
		$pexel_cat = isset( $assoc_args['images-from'] ) ? $assoc_args['images-from']: $wiki_cat;

		// Collect our post type counts
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ){
			if ( isset( $assoc_args[ $post_type ] ) ) {
				$post_types[ $post_type ] = $assoc_args[ $post_type ];
			}
		}

		// How many articles should we request from Wikipedia?
		$total_articles = array_sum( $post_types );

		// Set up default post
		$post = array(
			'post_content'   => $text,
			'post_title'     => $title,
			'post_status'    => 'publish',
			'post_type'      => 'post',
			'post_author'    => get_current_user_id(),
			'ping_status'    => 'closed',
		);
		$post = apply_filters( 'demo_gen_default_post', $post );

		$article_list = self::get_article_list( $wiki_cat, $total_articles );
		if ( is_wp_error( $article_list ) ) {
			WP_CLI::error( $article_list->get_error_message() );
		}

		// Un-alphabetize the list
		shuffle( $article_list );

		foreach( $article_list as $title ){
			foreach ( $post_types as $post_type => $count ) {
				if ( $count > 0 ) {
					$post_types[ $post_type ]--;
					break;
				}
			}

			$response = self::get_article_response( $title );
			if ( is_wp_error( $response ) ) {
				WP_CLI::warning( $response->get_error_message() );
				continue;
			}

			$text = self::get_article_text( $response );
			if ( is_wp_error( $text ) ) {
				WP_CLI::warning( $text->get_error_message() );
				continue;
			}

			$post['post_content']  = $text;
			$post['post_title']    = $title;
			$post['post_type']     = $post_type;
			$post['post_date']     = self::random_date();

			// Check that we're handling a post type that supports categories.
			if ( isset( $wp_taxonomies['category'] ) && in_array( $post_type, $wp_taxonomies['category']->object_type ) ) {
				$categories = self::get_article_cats( $response );
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

			WP_CLI::line( sprintf( "Created %s [%s], from %s", $post_type, $post_id, $title ) );
		}
	}

	/**
	 * Get a list of articles based on a wikipedia category name.
	 * Return a WP_Error if we can't connect, can't find the category,
	 * or don't have enough articles.
	 *
	 * @param  string  $wiki_cat        Category name to search
	 * @param  int     $total_articles  Number of articles to return
	 * @return array|WP_Error  List of article titles, or error
	 */
	private function get_article_list( $wiki_cat, $total_articles = 10 ) {
		$list_request = add_query_arg( array(
			'action'   => 'query',
			'cmlimit'  => $total_articles,
			'list'     => 'categorymembers',
			'cmtitle'  => 'Category:'.$wiki_cat,
			'format'   => 'json',
			'continue'  => '', // prevents an error message
			'cmtype'   => 'page',
		), self::API_URL );

		$response = wp_remote_get( $list_request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $body ) {
			return new WP_Error( 'empty-body', __( "The response body is empty.", 'demo-gen' ) );
		}

		if ( isset( $body->query ) && isset( $body->query->categorymembers ) && is_array( $body->query->categorymembers ) ) {
			$titles = wp_list_pluck( $body->query->categorymembers, 'title' );
			if ( ! empty( $titles ) ) {
				return $titles;
			}
		}

		return new WP_Error( 'empty-list', sprintf( __( "No articles in %s returned.", 'demo-gen' ), $wiki_cat ) );
	}

	/**
	 * Get the server response for an article request, based on title.
	 * Return a WP_Error if we can't connect or can't find the article.
	 *
	 * @param  string  $title   Title of article to look up
	 * @return object|WP_Error  Article response object, or error
	 */
	private function get_article_response( $title ) {
		$page_request = add_query_arg( array(
			'action'    => 'query',
			'titles'    => urlencode( $title ),
			'format'    => 'json',
			'prop'      => 'extracts|categories',
			'redirects' => 'true',
			'exintro'   => 'true',
			'continue'  => '', // prevents an error message
		), self::API_URL );

		$response = wp_remote_get( $page_request, array( 'user-agent' => 'WP-CLI Generator/0.1.0; ' . get_bloginfo( 'url' ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $body ) {
			return new WP_Error( 'empty-body', __( "The response body is empty.", 'demo-gen' ) );
		}

		return $body;
	}


	/**
	 * Get the intro text of a specific article from the API response.
	 * Return a WP_Error we don't have any text.
	 *
	 * @see self::get_article_response
	 *
	 * @param  object  $body    Response object from API
	 * @return string|WP_Error  Article text, or error
	 */
	private function get_article_text( $body ) {
		if ( isset( $body->query ) && isset( $body->query->pages ) ) {
			if ( isset( $body->query->pages->{'-1'} ) ) {
				return new WP_Error( 'not-found', sprintf( __( "The article '%s' could not be found.", 'demo-gen' ), $title ) );
			}

			$article = (array) $body->query->pages;
			$article = array_pop( $article );
			if ( is_object( $article ) && isset( $article->extract ) ) {
				return $article->extract;
			}
		}

		return new WP_Error( 'empty-article', sprintf( __( "No text found for article '%s'.", 'demo-gen' ), $title ) );
	}

	/**
	 * Get the categories of a specific article from the API response.
	 * Return a WP_Error we don't have any categories.
	 *
	 * @see self::get_article_response
	 *
	 * @param  object  $body    Response object from API
	 * @return array|WP_Error  Article categories, or error
	 */
	private function get_article_cats( $body ) {
		if ( isset( $body->query ) && isset( $body->query->pages ) ) {
			if ( isset( $body->query->pages->{'-1'} ) ) {
				return new WP_Error( 'not-found', sprintf( __( "The article '%s' could not be found.", 'demo-gen' ), $body->query->pages->{'-1'}->title ) );
			}

			$article = (array) $body->query->pages;
			$article = array_pop( $article );
			$categories = array();
			if ( is_object( $article ) && isset( $article->categories ) ) {
				foreach ( $article->categories as $cat ) {
					$cat = str_replace( 'Category:', '', $cat->title );
					$term = term_exists( $cat, 'category' );
					if ( ! $term ) {
						$term = wp_insert_term( $cat, 'category' );
					}
					$categories[] = (int) $term['term_id'];
				}
				return $categories;
			}
		}

		return new WP_Error( 'empty-article', sprintf( __( "No categories found for article '%s'.", 'demo-gen' ), $article->title ) );
	}

	/**
	 * Generate a random date within 3 months of the current time.
	 *
	 * @return  string  A date, formatted as Y-m-d H:i:s
	 */
	private function random_date(){
		$min_date = strtotime( '3 months ago' );
		$max_date = time();

		// Generate random time
		$timestamp = rand( $min_date, $max_date );

		// Format the timestamp
		return date( 'Y-m-d H:i:s', $timestamp );
	}
}

WP_CLI::add_command( 'demo', 'Demo_Generator' );
