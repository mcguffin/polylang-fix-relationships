<?php


if ( ! class_exists( 'PolylangPostClonerWatchMeta' ) ) :
class PolylangPostClonerWatchMeta {
	/**
	 *	Will hold field types for all relational ACF fields
	 */
	private $acf_types_to_watch = array( 'gallery' , 'file' , 'image' , 'page_link' , 'post_object' , 'relationship' );
	
	/**
	 *	Will hold meta_key names for all relational meta fields
	 */
	private $watch_meta_keys = array( '_thumbnail_id' );

	/**
	 *	Holding the singleton instance
	 */
	private static $_instance = null;

	/**
	 *	@return PolylangPostClonerWatchMeta
	 */
	public static function instance() {
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
		add_action( 'init' , array( &$this , 'acf_init' ) );
		add_action( 'pll_save_post' ,  array( &$this , 'handle_meta' ) , 20 , 3 );
	}
	
	/**
	 *	Setup relational ACF fields.
	 *	
	 *	@action 'init'
	 */
	function acf_init() {
		if ( class_exists( 'acf' ) ) {
			// Get all ACF fields...
			$all_acf_fields = get_posts(array(
				'post_type' => 'acf-field',
				'posts_per_page' => -1,
			));

			// ... and add the relational ones to our watchlist.
			foreach( $all_acf_fields as $field ) {
				$field_settings = unserialize( $field->post_content );
				if ( in_array($field_settings['type'] , $this->acf_types_to_watch ) )
					$this->watch_meta_keys[] = $field->post_excerpt; // add meta key name
			}
		}
	}
	
	
	/**
	 *	Handle meta keys of a translation group.
	 *	Resolve Relational meta keys. Tries to change post relations to their corresponding translated post.
	 *
	 *	@param $source_post_id int source Post ID
	 *	@param $source_post object Post 
	 *	@param $translation_group 	array like it is returned by $polylang->model->get_translations( 'post' , $post_id ), 
	 *								having the language slug as key and the post id as value.
	 *	@action 'pll_save_post'
	 */
	function handle_meta( $source_post_id , $source_post , $translation_group ) {
		$watch_meta_keys = apply_filters( 'polylang_watch_meta_keys' , $this->watch_meta_keys );
		foreach ( $translation_group as $lang => $new_post_id ) {
			if ( $new_post_id != $source_post_id ) { // YES, we do this chack!
				foreach ( $watch_meta_keys as $meta_key ) {
					$old_meta_value = $meta_value = maybe_unserialize( get_post_meta( $new_post_id , $meta_key , true ) );
					
					if ( is_array( $meta_value ) ) {
						foreach ( $meta_value as $k => $v )
							$meta_value[$k] = pll_get_post( $v , $lang );
					} else if ( is_numeric( $meta_value ) ) {
						$meta_value = pll_get_post( $meta_value , $lang );
					}
					if ( $old_meta_value != $meta_value ) {
						update_post_meta( $new_post_id , $meta_key , $meta_value );
					}
				}
			}
		}
		
	}
	
}
endif;

