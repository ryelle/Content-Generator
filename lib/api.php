<?php

class Demo_Gen_API {
	const API_URL = 'https://en.wikipedia.org/w/api.php';
	const IMAGE_URL = 'http://www.pexels.com/search/';

	public static $_instance;

	public static function get_instance() {
		if ( ! ( self::$_instance instanceof self ) ) {
			self::$_instance = new self;
		}
		return self::$_instance;
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
	public function get_article_list( $wiki_cat, $total_articles = 10 ) {
		$list_request = add_query_arg( array(
			'action'   => 'query',
			'cmlimit'  => $total_articles,
			'list'     => 'categorymembers',
			'cmtitle'  => 'Category:'.urlencode( $wiki_cat ),
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
	public function get_article_response( $title ) {
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
	public function get_article_text( $body ) {
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
	public function get_article_cats( $body ) {
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
					// Let's not import the really long categories...
					if ( str_word_count( $cat ) > 4 ) {
						continue;
					}
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
	public function random_date(){
		$min_date = strtotime( '3 months ago' );
		$max_date = time();

		// Generate random time
		$timestamp = rand( $min_date, $max_date );

		// Format the timestamp
		return date( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Get the image chance.
	 */
	public function parse_image_chance( $assoc_args ){
		$add_image = isset( $assoc_args['with-images'] ) ? $assoc_args['with-images']: 'most';
		switch ( $add_image ) {
			case 'all':
				$add_image = 100;
				break;
			case 'some':
				$add_image = 50;
				break;
			case 'few':
				$add_image = 25;
				break;
			case 'none':
				$add_image = 0;
				break;
			case 'most':
			default:
				$add_image = 75;
				break;
		}
		return $add_image;
	}

	/**
	 * Get a list of image URLs.
	 * Return a WP_Error if we don't get any images.
	 *
	 * @param  object  $body    Response object from API
	 * @return string|WP_Error  Image? text, or error
	 */
	public function get_image_list( $category, $max_images = 10 ){
		$image_list = false; //get_transient( $category . ':' . $max_images );

		if ( false == $image_list ) {
			$image_request = self::IMAGE_URL . urlencode( $category ) . '/feed/';
			$rss = fetch_feed( $image_request ); // By default, caches 12hrs.

			if ( is_wp_error( $rss ) ) {
				return $rss;
			}

			// Figure out how many total items there are, but limit it.
			$maxitems = $rss->get_item_quantity( $max_images );
			if ( $maxitems < $max_images ) {
				return new WP_Error( 'short-list', __( "Not enough images returned.", 'demo-gen' ) );
			}
			$rss_items = $rss->get_items( 0, $maxitems );

			$image_list = array();
			foreach ( $rss_items as $item ) {
				if ( $url = $item->get_permalink() ) {
					$image_list[] = self::get_image_from_url( $url );
				}
			}

			set_transient( $category . ':' . $max_images, $image_list );
		}

		return $image_list;
	}

	/**
	 * Get a specific image URL from a given post.
	 * Return a WP_Error if we don't get an image.
	 *
	 * @param  string  $url     Image post URL
	 * @return string|WP_Error  Image URL, or error
	 */
	public function get_image_from_url( $url ) {
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return new WP_Error( 'empty-body', __( "The response body is empty.", 'demo-gen' ) );
		}

		$image_found = preg_match_all( '/<meta itemprop="image"[^>]*content="([^"]*)"[^>]*>/', $body, $matches );
		// $image_found = preg_match_all( '/<a class="js-download"[^>]*href="([^"]*)"[^>]*>/', $body, $matches );
		if ( $image_found && isset( $matches[1] ) && isset( $matches[1][0] ) ) {
			return $matches[1][0];
		}

		return new WP_Error( 'empty-image', __( "No image was found.", 'demo-gen' ) );
	}

	/**
	 * Download an image from the specified URL and attach it to a post.
	 *
	 * @param  string  $file     The URL of the image to download
	 * @param  int     $post_id  The post ID the media is to be associated with
	 * @return string|WP_Error   Populated HTML img tag on success
	 */
	public function set_image( $file, $post_id = -1 ) {
		if ( empty( $file ) ) {
			return new WP_Error( 'empty-file', __( "No file was specified.", 'demo-gen' ) );
		}

		$id = false;
		$already_downloaded = get_transient( 'dg-already-downloaded' );
		if ( $already_downloaded && is_array( $already_downloaded ) ){
			if ( isset( $already_downloaded[ basename( $file ) ] ) ) {
				WP_CLI::line( sprintf( "Already downloaded %s", basename( $file ) ) );
				$id = $already_downloaded[ basename( $file ) ];
			}
		} else {
			$already_downloaded = array();
		}

		if ( ! $id ) {
			WP_CLI::line( sprintf( "Downloading & attaching %s", basename( $file ) ) );
			// Set variables for storage, fix file filename for query strings.
			preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
			$file_array = array();
			$file_array['name'] = basename( $matches[0] );

			// Download file to temp location.
			$file_array['tmp_name'] = download_url( $file );

			// If error storing temporarily, return the error.
			if ( is_wp_error( $file_array['tmp_name'] ) ) {
				return $file_array['tmp_name'];
			}

			// Do the validation and storage stuff.
			$id = media_handle_sideload( $file_array, $post_id );

			// If error storing permanently, unlink.
			if ( is_wp_error( $id ) ) {
				@unlink( $file_array['tmp_name'] );
				return $id;
			}

			$already_downloaded[ basename( $file ) ] = $id;
			set_transient( 'dg-already-downloaded', $already_downloaded );
		}

		return $id;
	}
}
