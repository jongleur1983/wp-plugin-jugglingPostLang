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
		
		// add a language column to pages and posts:
		add_filter(
			'manage_posts_columns', 
			array($this, 'add_language_column'));
		add_filter(
			'manage_pages_columns', 
			array($this, 'add_language_column'));
		// define the content of the column:
		add_action(
			'manage_posts_custom_column', 
			array($this, 'language_column_content'),
			10, 2);
		add_action(
			'manage_pages_custom_column', 
			array($this, 'language_column_content'),
			10, 2);
		// make the column sortable:
		// compare http://scribu.net/wordpress/custom-sortable-columns.html
		add_filter(
			'manage_edit_sortable_columns', //'manage_edit-posts_sortable_columns', 
			array($this, 'language_column_sortable'));
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
				'query_var' => true
			)
	    );
		register_taxonomy_for_object_type($this->TAXONOMY_NAME, 'post');
	
		// initial values for the taxonomy:
		wp_insert_term('de', 'juggling_post_language');
		wp_insert_term('en', 'juggling_post_language');
	}
	
	public function add_language_column($columns) {
		return array_merge(
			$columns,
			array($this->TAXONOMY_NAME => __('Language', $this->TEXT_DOMAIN))
			);
	}
	
	public function language_column_content($column, $post_id) {
		switch ($column) {
			case $this->TAXONOMY_NAME:
				$field_values = wp_get_object_terms(
									$post_id, 
									$this->TAXONOMY_NAME, 
									array('fields' => 'names'));
				$language = (empty($field_values) ? '' : $field_values[0]);
				echo $language; // TODO: #4 add the flag here
				break;
		}
	}
	
	public function language_column_sortable($columns) {
		/*if (isset($columns[$this->TAXONOMY_NAME]) 
			&& ($this->TAXONOMY_NAME == $columns[$this->TAXONOMY_NAME])) {
			$columns = array_merge(
				$columns, 
				array(
					'meta_key' => $this->TAXONOMY_NAME,
					'orderby' => 'meta_value'
				));
			return $columns;
		}
		*/
		echo '<!-- FOO BAR BLUBB -->';
		$columns[$this->TAXONOMY_NAME] = $this->TAXONOMY_NAME;
		return $columns;
	}

	public function languageSelectorContent($post, $metabox) {
		// generate the html for the box !!!
		wp_nonce_field(basename(__FILE__), $this->METABOX_NONCE);
		
		$old_terms = wp_get_object_terms(
						$post->ID, 
						$this->TAXONOMY_NAME, 
						array('fields' => 'names') );
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
			// get the language:
			$language_terms = wp_get_object_terms($post->ID, $this->TAXONOMY_NAME, array('fields' => 'names') );
			$language = (empty($language_terms) ? null : $language_terms[0]);

			// determine div or span: what do we have to use to validly wrap $content?
			$wrapInfo = $this->getSurroundingElement($content);
			$nodeName = 'span';
			if (!$wrapInfo['errorOccurred']) {
				$nodeName = $wrapInfo['nodeName'];
			}

			$result = '<'.$nodeName.' class="'. $this->LANGUAGE_MARKER_CLASS .'" ';
		  
			if ($language !== null) {
				$result .= 'lang="'.$language.'"';
			}
			$result .= '>'.$content.'</'.$nodeName.'>';
		}
		
		return $result;
	}

	/**
	 * This function should determine the correct way to wrap a given html snipped to produce valid html.
	 * - for a transparent content model element T the function is called recursively to determine the content model of T.
	 * - phrasing content is a subset of flow content.
	 * - div may contain any flow content and is allowed wherever flow content is allowed.
	 * - span may contain phrasing content only and is only allowed where phrasing content is expected.
	 *
	 * Thus as soon as we find any flow content element that is not phrasing content, we use div;
	 * else we use span.
	 *
	 * @param $content string: the html code we want to wrap in a semantically meaningless element
	 * @return string the tag name to wrap the content in a valid way.
	 */
	public function getSurroundingElement($content) {
		$xml = new XMLReader();
		$wrappedContent = "<root>$content</root>";
		$xml->XML($wrappedContent);

		$debugTrace = '';
//    $phrasingContentElements = [
//        'a', 'abbr', 'area', 'audio',
//        'b', 'bdi', 'bdo', 'br', 'button',
//        'canvas', 'cite', 'code',
//        'data', 'datalist', 'del', 'dfn',
//        'em', 'embed',
//        'i', 'iframe', 'img', 'input', 'ins',
//        'kbd', 'keygen',
//        'label', 'link', //(if it is allowed in the body)
//        'map', 'mark', 'math', 'meta' /* (if the itemprop attribute is present) */, 'meter',
//        'noscript',
//        'object', 'output',
//        'picture', 'progress',
//        'q',
//        'ruby',
//        's', 'samp', 'script', 'select', 'small', 'span', 'strong', 'sub', 'sup', 'svg',
//        'template', 'textarea', 'time',
//        'u',
//        'var', 'video',
//        'wbr'
//        // text elements
//    ];

		$notPhrasingFlowContentElements = [
			'address', 'article', 'aside',
			'blockquote',
			'details', 'dialog', 'div', 'dl',
			'fieldset', 'figure', 'footer', 'form',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr',
			'main', 'mark', 'menu', 'meter',
			'nav',
			'p', 'pre',
			'section', 'style',
			'table',
			'ul'
		];

		$transparentElements = [
			'a',
			'ins',
			'del',
			'object',
			// <video>, <audio> is transparent but does not have any non-transparent content in the context we need here, TODO: check it!
			'map',
			'noscript', // is transparent when scripting is disabled, else it is ignored. Thus we use it as transparent, as that ensures validity.
			'canvas'
		];

		ob_start(); //"warningCallback");

		$xml->read(); // go to the root node
		$xml->read(); // skip the root node
		do {
			$debugTrace .= $xml->name . '('; //.$read.'#';
			if ($xml->nodeType == XMLReader::ELEMENT) {
				// if element name is a notPhrasingFlowContentElement, we can return div:
				$normalizedName = strtolower($xml->name);
				if (in_array($normalizedName, $transparentElements)) {
					$contentModelFromRecursion = $this->getSurroundingElement($xml->readInnerXml());
					if ($contentModelFromRecursion['errorOccurred']) {
						return array(
							'nodeName' => 'span',
							'trace' => $debugTrace,
							'errorOccurred' => true);
					} elseif ($contentModelFromRecursion['nodeName'] == 'div') {
						$debugTrace = $debugTrace . $contentModelFromRecursion['trace'] . ')';
						return array('nodeName' => 'div',
							'trace' => $debugTrace);
					}
				} elseif (in_array($normalizedName, $notPhrasingFlowContentElements)) {
					$debugTrace = $debugTrace . ')';
					return array('nodeName' => 'div',
						'trace' => $debugTrace);
				}
			}
		} while ($read = $xml->next());

		$buffer = ob_get_clean();
		$anyErrorOccurred = !empty($buffer);

		$debugTrace = $debugTrace .')';

		return array(
			'nodeName' => 'span',
			'trace' => $debugTrace,
			'errorOccurred' => $anyErrorOccurred);
	}
}
  
$jugglingPostLangPlugin = new JugglingPostLang();
?>