<?php
/**
 * Utilities class
 */
class DCG {
	/**
	 * Print a warning to the error log
	 */
	public static function warning( $string ){
		error_log( '[WARNING] ' . $string );
	}

	/**
	 * Create the post given an article title
	 */
	public static function get_post_from_article_title( $post_type, $title ) {
		$api = Demo_Gen_API::get_instance();

		$post = array(
			'post_content'   => '',
			'post_title'     => '',
			'post_status'    => 'publish',
			'post_type'      => 'post',
			'post_author'    => WP_CLI ? get_current_blog_id(): 'demouser',
			'ping_status'    => 'closed',
			'comment_status' => 'closed',
			'post_excerpt'   => '',
			'post_parent'    => 0,
			'menu_order'     => 0,
			'post_password'  => '',
		);
		$post = apply_filters( 'demo_gen_default_post', $post );

		$response = $api->get_article_response( $title );
		if ( is_wp_error( $response ) ) {
			DCG::warning( $response->get_error_message() );
			return;
		}

		$text = $api->get_article_text( $response );
		if ( is_wp_error( $text ) ) {
			DCG::warning( $text->get_error_message() );
			return;
		}

		$post['guid']          = $api->get_article_url( $title );
		$post['post_content']  = $text;
		$post['post_title']    = $title;
		$post['post_name']     = sanitize_title( $title );
		$post['post_type']     = $post_type;
		$post['post_date_gmt'] = $api->random_date();
		$post['post_date']     = get_date_from_gmt( $post['post_date_gmt'] );

		// Check that we're handling a post type that supports categories.
		if ( isset( $wp_taxonomies['category'] ) && in_array( $post_type, $wp_taxonomies['category']->object_type ) ) {
			$categories = $api->get_article_cats( $response );
			if ( is_wp_error( $categories ) ) {
				DCG::warning( $categories->get_error_message() );
				return;
			}
			$post['post_category'] = $categories;
		}

		// $post['post_excerpt'] = '';

		return $post;
	}
}
