<?php
/**
 * Plugin Name: jugglingPostLang
 * Plugin URI:  http://github.com/jongleur1983/jugglingPostLang-wp
 * Description: Define language for a single post
 * Version:     0.1
 * Author:      Peter Wendorff (Peter.Wendorff@gmx.de)
 * Author URI:  http://jugglingsource.de
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jugglingPostLang
 * Domain Path: /languages
 */
class JugglingPostLang {
	
	var $PLUGIN_NAME			= 'jugglingPostLang';
	var $METABOX_NONCE 			= 'jugglingPostLang_meta_box_nonce';
	var $METABOX_ID				= 'jugglingPostLang_post_language_selector';
	var $METABOX_SELECT_ID		= 'jugglingPostLang_selector';
	var $LANGUAGE_MARKER_ID		= 'jugglingPostLang_languageMarker';
	var $LANGUAGE_MARKER_CLASS	= 'jugglingPostLang_markerDiv';
	var $TEXT_DOMAIN			= 'jugglingPostLang';
	
	var $TAXONOMY_NAME			= 'juggling_post_language';
	
	public function __construct() {
		add_action('init', array($this, 'init'));
		add_action('add_meta_boxes', array($this, 'adminBox'));
		add_action('save_post', array($this, 'saveLanguageSelection'), 10, 2);
		
		add_filter('the_content', array($this, 'wrapWithLanguagedDiv'));
		add_filter('the_title', array($this, 'wrapWithLanguagedDiv'));
	}
	
	public function init() {
		load_plugin_textdomain(
			$this->TEXT_DOMAIN, 
			false, 
			(dirname(plugin_basename(__FILE__)) . '/languages/' ));

		// create the taxonomy:
		register_taxonomy(
			$this->TAXONOMY_NAME, // taxonomy name
			'post', // register it for posts
			array(
				'labels' => array(
						'name' => __('Languages', 'jugglingPostLang'), 
						'singular_name' => __('Language', $this->TEXT_DOMAIN)
					),
				'rewrite' => false, // don't rewrite the url part to match the language
				'meta_box_cb' => false, // hide the default meta-box
			)
	    );
	
		// initial values for the taxonomy:
		wp_insert_term('de', 'juggling_post_language');
		wp_insert_term('en', 'juggling_post_language');
	}

	public function languageSelectorContent($post, $metabox) {
		// generate the html for the box !!!
		wp_nonce_field(basename(__FILE__), $this->METABOX_NONCE);
		
		$old_terms = wp_get_object_terms($post->ID, $this->TAXONOMY_NAME, array('fields' => 'ids') );
		$old_language = (empty($old_terms) ? -1 : $old_terms[0]);
		
		echo '<label for="'. $this->METABOX_SELECT_ID .'">';
		_e('Language', $this->TEXT_DOMAIN);
		echo '</label>';
		
		echo '<select id="'. $this->METABOX_SELECT_ID .'" name="'. $this->METABOX_SELECT_ID .'">';
		
		// iterate over all languages 
		$terms = get_terms(
			$this->TAXONOMY_NAME,
			array(
				'hide_empty' => false
			)
		);
		
		$options = '';
		$foundSelection = false;
		
		foreach ($terms as $term) {
			$selected = '';
			if ($old_language == $term->term_id) {
				$selected  = ' selected';
				$foundSelection = true;
			}
			
			$options .= '<option value="'.$term->term_id.'"'.$selected.'>'
							.$term->name 
						.'</option>';
		}
		
		// print default item:
		if (false == $foundSelection) {
			echo '<option value="-1" style="display:none;" default disabled selected>'
					.__(' - not specified - ', $this->TEXT_DOMAIN)
				.'</option>';
		}
		
		// print other items:
		echo $options;
		
		
		echo '</select>';
	}
	
	public function adminBox() {
		add_meta_box(
			$this->METABOX_ID, // html id of the meta box
			__('Language', 'jugglingPostLang'), // box title
			array( $this, 'languageSelectorContent'),
			'page', // show on page edit screens
			'side',
			'high'
		);
	  
		add_meta_box(
			$this->METABOX_ID, // html id of the meta box
			__('Language', 'jugglingPostLang'), // box title
			array( $this, 'languageSelectorContent'),
			'post', // show on page edit screens
			'side',
			'high' // priority
		);
	}

	public function saveLanguageSelection($post_id, $post) {
		
		// verify nonce:
		if (!isset($_POST[$this->METABOX_NONCE]) || +
			!wp_verify_nonce($_POST[$this->METABOX_NONCE], basename(__FILE__))) 
		{
			return;
		}
		
		// saving the content:
		
		// get the post type object:
		$post_type = get_post_type_object($post->post_type);
	  
		// check permissions:
		if (!current_user_can($post_type->cap->edit_post, $post_id)) {
			return;
		}
	  
	  	// default language:
		$default_language = get_bloginfo('language'); // that's the locale as de or de_DE
		$default_id_tmp = get_term_by('name', $default_language, $this->TAXONOMY_NAME);
		if ($default_id_tmp === false) {
		  $default_id = $default_id_tmp->term_id;
	  }
	  else {
		  $default_id = '-1';
	  }
	  
	  // get the old value:
	  $old_terms = wp_get_object_terms($post_id, $this->TAXONOMY_NAME, array('fields' => 'ids') );
	  $old_language = (empty($old_terms) ? $default_id : $old_terms[0]);
	  
	  // get the value from form and sanitize it:
	  $new_language = (isset($_POST[$this->METABOX_SELECT_ID]) ? $_POST[$this->METABOX_SELECT_ID] : $old_language );
	  
	  // if there is a value and there was no old value, or if new and old values differ we add it:
	  if ($new_language && ('' == $old_language || $new_language != $old_language)) {
		  // add or replace the value
		  wp_set_object_terms($post_id, (int)$new_language, $this->TAXONOMY_NAME, false);
	  }
	  else if (-1 == $new_language && old_language) {
		  // should never happen, but delete the language token:
		  wp_delete_object_term_relationships($post_id, $this->TAXONOMY_NAME);
	  }
	}

	public function wrapWithLanguagedDiv($content, $id = null) {
		// get posts language
		global $post;
		$result = $content;
		
		if (is_main_query() && !is_admin()) {
			$language_terms = wp_get_object_terms($post->ID, $this->TAXONOMY_NAME, array('fields' => 'names') );
			$language = (empty($language_terms) ? null : $language_terms[0]); 
			$result = '<span class="'. $this->LANGUAGE_MARKER_CLASS .'" ';
		  
			if ($language !== null) {
				$result .= 'lang="'.$language.'"';
			}
			$result .= '>'.$content.'</span>';
		}
		
		return $result;
	}
}
  
$jugglingPostLangPlugin = new JugglingPostLang();
?>