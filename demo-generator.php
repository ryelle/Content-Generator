<?php
/**
 * Plugin Name: Content Generator
 * Description: Pull content from wikipedia & photos from pexels for populating a demo or stage site.
 */

require_once( __DIR__ . '/lib/api.php' );

if ( defined('WP_CLI') && WP_CLI ) {
	require_once( __DIR__ . '/lib/cli.php' );
} else {
	require_once( __DIR__ . '/lib/wxr.php' );
}

class Demo_Generator_UI {
	public static $_instance;

	public static function get_instance() {
		if ( ! ( self::$_instance instanceof self ) ) {
			self::$_instance = new self;
			self::$_instance->setup();
		}
		return self::$_instance;
	}

	/**
	 * Add actions & filters
	 */
	public function setup(){
		add_action( 'init', array( $this, 'rewrites' ) );
		add_action( 'template_include', array( $this, 'get_template' ) );
	}

	/**
	 * Create our rewrite endpoint `/generator`
	 */
	public function rewrites() {
		add_rewrite_tag( '%generator%', '([^&]+)' );
		add_rewrite_rule( '^generator/?', 'index.php?generator=api', 'top' );
	}

	/**
	 * Load the API view file, which calls the internal API and creates an XML file on the fly
	 */
	public function get_template( $original_template ){
		global $wp_query;
		if ( isset( $wp_query->query_vars['generator'] ) &&  ( 'api' == $wp_query->query_vars['generator'] ) ) {
			return __DIR__ . '/view/api.php';
		}
		return $original_template;
	}

}
// Initialize
Demo_Generator_UI::get_instance();
