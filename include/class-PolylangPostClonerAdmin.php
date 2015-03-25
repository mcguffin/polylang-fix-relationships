<?php


if ( ! class_exists( 'PolylangPostClonerAdmin' ) ) :
class PolylangPostClonerAdmin {
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
		add_action('admin_init',array(&$this,'admin_init'));
		add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
		add_action( 'load-edit.php' , array( &$this , 'do_clone_action' ) );
		add_action( 'load-upload.php' , array( &$this , 'do_clone_action' ) );
		if ( get_option( 'polylang_clone_attachments' ) )
			add_action( 'pll_save_post' ,  array( &$this , 'handle_attachments' ) , 15 , 3 );
	}
	
	/**
	 *	Load css
	 *	@action 'admin_enqueue_scripts'
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'polylang-cloner-admin' , plugins_url('css/admin.css', dirname(__FILE__)) );
	}

	/**
	 *	Setup columns
	 *	@action 'admin_init'
	 */
	function admin_init() {
		add_filter('post_row_actions',array(&$this,'row_actions') , 10 , 2 );
		add_filter('page_row_actions',array(&$this,'row_actions') , 10 , 2 );

		// add_filter('page_row_actions',array(&$this,'row_actions') , 10 , 2 );
	}
	
	/**
	 *	Add row actions
	 *
	 *	@filter 'post_row_actions', 'page_row_actions'
	 */
	function row_actions( $actions , $post ) {
		if ( pll_is_translated_post_type( $post->post_type ) ) {
			global $polylang;
			$translations = $polylang->model->get_translations( 'post' , $post->ID );
			$languages = pll_languages_list();
			$current_language = pll_get_post_language($post->ID);

			if (count($translations) != count( $languages ) ) {
				$missing_translations = array();
				foreach ( $languages as $lang_slug )
					if ( ! isset( $translations[$lang_slug] ) && $lang_slug != $current_language)
						$missing_translations[] = $lang_slug;
				
				$action = 'polylang_get_translation';
				$url_params = array(
					'polylang_action' => $action,
					'new_langs'	=> $missing_translations,
					'from_post'	=> $post->ID,
				);
				
				$href = wp_nonce_url( add_query_arg( $url_params ) , $action );
				
				$actions['clone_for_translation'] = sprintf( '<a href="%s">%s</a>',
						$href, _n('Create Translation','Create Translations',count($missing_translations),'polylang-fix-relationships')
					);
			}

			if ( count($translations) ) {
				$action = 'polylang_fix_relations';
				$url_params = array(
					'polylang_action' => $action,
					'from_post' => $post->ID,
				);
				$href = wp_nonce_url( add_query_arg( $url_params ) , $action );
				$actions['fix_relations'] = sprintf( '<a href="%s">%s</a>',
						$href, __( 'Fix Relations' , 'polylang-fix-relationships' )
					);
			}
		}
		return $actions;
	}
	
	
	/**
	 *	Do Post cloning
	 *
	 *	@action 'load-edit.php', 'load-upload.php'
	 */
	function do_clone_action() {
		if ( isset( $_REQUEST['new_langs'] , $_REQUEST['from_post'] , $_REQUEST['polylang_action'] ) && current_user_can( 'edit_post' , (int) $_REQUEST['from_post'] ) ) {
			check_admin_referer( $_REQUEST['polylang_action'] );
			$source_post_ids = (array) $_REQUEST['from_post'];
			$source_post_ids = array_filter($source_post_ids , 'intval' );
			foreach ( $source_post_ids as $source_post_id ) {
				$source_post_lang = pll_get_post_language( $source_post_id );
			
				if ( $source_post = get_post( $source_post_id ) ) {
					switch ( $_REQUEST['polylang_action'] ) {	
						case 'polylang_get_translation':
							$langs = (array) $_REQUEST['new_langs'];
							$translation_group = $this->create_translation_group( $source_post , $langs );
							break;
						case 'polylang_fix_relations':
							$translation_group = $polylang->model->get_translations( 'post' ,  $source_post_id );
							break;
					}
					// trigger save action.
					unset($translation_group[$source_post_lang]);
					do_action( 'pll_save_post' , $source_post_id , get_post( $source_post_id ) , $translation_group );
				}
			}
			$redirect = remove_query_arg( array('new_langs','from_post','_wpnonce','polylang_action'));
			wp_redirect($redirect);
			exit();
		}
	}
	
	/**
	 *	Clone post.
	 *	Creates translations out of $source_post where necessary.
	 *
	 *	@param $source_post object Master Post
	 *	@param $langs array holding the target languages
	 *	@return array translation group with language slugs as keys and post ids as values
	 */
	function create_translation_group( $source_post , $langs )  {
		global $polylang;
	
		$source_post_lang = pll_get_post_language( $source_post->ID );
		$translation_group = array(
				$source_post_lang => $source_post->ID,
			) + $polylang->model->get_translations( 'post' ,  $source_post->ID );
		
		foreach ( $langs as $lang ) {
			// check if is language
			if ( ($lang = $polylang->model->get_language($lang)) && ! isset( $translation_group[$lang->slug] ) ) {
				$new_post_id = $this->make_post_translation( $source_post , $lang );
				$translation_group[$lang->slug] = $new_post_id;
			}
		}
		pll_save_post_translations($translation_group);
		return $translation_group;
	}
	
	
	/**
	 *	Set parent-child relations for attachments in source posts' translation group.
	 *
	 *	@param $source_post object Master Post holding the correct parent-child relations
	 *	@param $langs array holding the target languages
	 */
	function handle_attachments( $source_post_id , $source_post , $parent_translation_group ) {
		global $polylang;
		$attachments = get_children( array( 'post_parent' => $source_post_id , 'post_type'   => 'attachment' ) );
		foreach ( $attachments as $attachment ) {
			$translation_group = $this->create_translation_group( $attachment , array_keys($parent_translation_group ) );
			// all good here. Nothing to be done.
			if ( $attachment->post_parent == $source_post_id )
				continue;
			foreach ( $translation_group as $lang => $translated_id ) {
				if ( isset( $parent_translation_group[ $lang ] ) ) {
					$post_arr = array( 
						'ID' => $translated_id,
						'post_parent' => $parent_translation_group[ $lang ] 
					);
					wp_update_post($post_arr);
				}
			}
		}
	}
	
	/**
	 *	Retrieve or create post translation.
	 *	If a translation can not be found it will do a deep-clone of $source_post, 
	 *	including post meta but not comments and attachments.
	 *
	 *	@param $source_post int|object Master Post (ID) holding the correct parent-child relations
	 *	@param $lang mixed language as passed to $polylang->model->get_language($lang)
	 */
	function make_post_translation( $source_post , $lang ) {
		if ( is_numeric( $source_post ) )
			$source_post = get_post( $source_post );
		
		global $polylang;
		
		if ( $lang = $polylang->model->get_language($lang) ) {
			$source_post_lang = pll_get_post_language( $source_post->ID );
			// sourcelang is target lang, nothing to do!
			if ( $lang->slug == $source_post_lang )
				return new WP_Error('clone',__('Source language is target language','polylang-fix-relationships'));

			// translation exists. Go ahead!
			if ( $translation = pll_get_post($source_post->ID,$lang) )
				return $translation;

			$post_arr = get_object_vars( $source_post );
			$post_arr['ID'] = 0;
			$post_arr['comment_count'] = 0;
			$post_arr['post_status'] = apply_filters( 'polylang_cloned_post_status' , $post_arr['post_status'] );

			// set translated parent
			if ( $post_arr['post_parent'] && ($translated_parent = pll_get_post( $post_arr['post_parent'] , $lang ) ) ) {
				$post_arr['post_parent'] = $translated_parent;
			}
			
			// prepare taxonomies
			if ( $cloned_post_id = wp_insert_post( $post_arr ) ) {
				pll_set_post_language( $cloned_post_id , $lang );
				$polylang->model->clean_languages_cache();
				
				// clone postmeta
				$ignore_meta_keys = array( '_edit_lock' , '_edit_last' );
				$meta = get_post_meta( $source_post->ID );

				foreach ( $meta as $meta_key => $values ) {
					if ( in_array( $meta_key , $ignore_meta_keys ) )
						continue;
					foreach ( $values as $value ) {
						update_post_meta( $cloned_post_id , $meta_key , maybe_unserialize( $value ) );
					}
				}
				
				// done.
				return $cloned_post_id;
			}
			return new WP_Error('clone',__('No such Language','polylang-fix-relationships'));
		}
	}
	
}
endif;

