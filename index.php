<?php

/*
Plugin Name: Polylang Fix Relationships 
Plugin URI: https://github.com/mcguffin/polylang-fix-relationships
Description: Manage post relationships like attachments or (ACF Relational fields) which are not covered by <a href="http://polylang.wordpress.com/">polylang</a> plugin.
Author: Jörn Lund
Author URI: https://github.com/mcguffin
Version: 0.0.4
License: GPLv3
*/

if ( ! class_exists( 'PolylangPostCloner' ) ) :
class PolylangPostCloner {
	/**
	 *	Holding the singleton instance
	 */
	private static $_instance = null;

	/**
	 *	@return WP_reCaptcha
	 */
	public static function instance(){
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 *	Prevent from creating more instances
	 */
	private function __clone() { }

	/**
	 *	Prevent from creating more than one instance
	 */
	private function __construct() {
		add_action( 'plugins_loaded' , array( &$this , 'plugins_loaded') );
		add_option( 'polylang_clone_attachments' , true );
	}

	/**
	 *	Setup plugin
	 *	
	 *	@action 'plugins_loaded'
	 */
	function plugins_loaded() {
		load_plugin_textdomain( 'polylang-fix-relationships' , false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		if ( is_admin() && class_exists( 'Polylang' ) ) {
			PolylangPostClonerAdmin::instance();
			PolylangPostClonerWatchMeta::instance();
		}
	}
}
endif;


/**
 * Autoload Classes
 *
 * @param string $classname
 */
function polylang_postcloner_autoload( $classname ) {
	$class_path = dirname(__FILE__). sprintf('/include/class-%s.php' , $classname ) ; 
	if ( file_exists($class_path) )
		require_once $class_path;
}
spl_autoload_register( 'polylang_postcloner_autoload' );

// init plugin
PolylangPostCloner::instance();