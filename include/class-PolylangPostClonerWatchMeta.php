<?php


if ( ! class_exists( 'PolylangPostClonerWatchMeta' ) ) :
class PolylangPostClonerWatchMeta {
	/**
	 *	Will hold field types for all relational ACF fields
	 */
	private $acf_types_to_watch = array( 'page_link' , 'post_object' , 'relationship' );
	
	/**
	 *	Will hold meta_key names for all relational meta fields
	 */
	private $watch_meta_keys = array( '_thumbnail_id' );

	private $watch_meta_keys_repeater = array( );

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
		$pll_options = get_option( 'polylang' );

		if ( PLL()->options['media_support'] ) {
			$this->acf_types_to_watch[] = 'image';
			$this->acf_types_to_watch[] = 'gallery';
			$this->acf_types_to_watch[] = 'file';
		}
		
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
				if ( in_array($field_settings['type'] , $this->acf_types_to_watch ) ) {
					if ( $this->is_repeater_child( $field ) ) {
						$meta_key = $this->get_repeater_meta_key_parts( $field );
					} else {
						$meta_key = $field->post_excerpt;
					}
					$this->watch_meta_keys[] = $meta_key; // add meta key name
				}
			}
		}
	}
	
	/**
	 *	Return whether a field is part of a repeater field.
	 *
	 *	@param $field	object	ACF Field Post object
	 *
	 *	@return	bool
	 */
	function is_repeater_child( $field ) {
		return get_post_type($field->post_parent) === 'acf-field';
	}
	
	
	/**
	 *	Return meta key parts for an acf repeater child.
	 *
	 *	@param $field	object	ACF Field Post object which is part of a repeater (check with is_repeater_child first)
	 *
	 *	@return	array	holding the meta key parts
	 */
	function get_repeater_meta_key_parts( $field ) {
		$meta_key = array( $field->post_excerpt );
		while ( ($field = get_post($field->post_parent)) && $field->post_type == 'acf-field' ) {
			$meta_key[] = $field->post_excerpt;
		}
		return array_reverse($meta_key);
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
		global $wpdb;
		$watch_meta_keys = apply_filters( 'polylang_watch_meta_keys' , $this->watch_meta_keys );
		$force_update = false;
		foreach ( $translation_group as $lang => $new_post_id ) {
			// prepare meta keys
			$post_watch_meta_keys = array();
			foreach( $watch_meta_keys as $meta_key ) {
				if ( is_array($meta_key) ) {
					$query = $wpdb->prepare("SELECT meta_key FROM $wpdb->postmeta WHERE meta_key REGEXP %s AND post_id=%d",
							sprintf('^%s$',implode( '_[0-9]_' ,$meta_key ) ),
							$new_post_id
						);
					$meta_keys = $wpdb->get_col($query);
					$post_watch_meta_keys = array_merge($post_watch_meta_keys,$meta_keys);
				} else {
					$post_watch_meta_keys[] = $meta_key;
				}
			}
			if ( $new_post_id != $source_post_id ) { // YES, we do this chack!
				foreach ( $post_watch_meta_keys as $meta_key ) {
					$old_meta_value = $meta_value = maybe_unserialize( get_post_meta( $new_post_id , $meta_key , true ) );
					
					if ( is_array( $meta_value ) ) {
						foreach ( $meta_value as $k => $v )
							$meta_value[$k] = pll_get_post( $v , $lang );
					} else if ( is_numeric( $meta_value ) ) {
						
						if ( $post = get_post( $meta_value ) ) {
							if ( pll_is_translated_post_type( $post->post_type ) ) {
								$meta_value = pll_get_post( $meta_value , $lang );
							} else {
								$force_update = true;
							}
						}
					}
					
					if ( $force_update || ( $old_meta_value != $meta_value ) ) {
						update_post_meta( $new_post_id , $meta_key , $meta_value );
					}
				}
			}
		}
	}
	
}
endif;

