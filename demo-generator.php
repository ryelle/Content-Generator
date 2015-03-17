<?php
/**
 * Plugin Name: Content Generator
 * Description: Pull content from wikipedia & photos from pexels for populating a demo or stage site.
 */

require_once( __DIR__ . '/lib/api.php' );

if ( defined('WP_CLI') && WP_CLI ) {
	require_once( __DIR__ . '/demo-generator.cli.php' );
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
	}

}
// Initialize
Demo_Generator_UI::get_instance();
