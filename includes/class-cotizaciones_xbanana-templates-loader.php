<?php
/**
 * Register all actions and filters for the plugin
 *
 * @link       https://github.com/aradiquez
 * @since      1.0.0
 *
 * @package    Cotizaciones_xbanana
 * @subpackage Cotizaciones_xbanana/includes
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Cotizaciones_xbanana
 * @subpackage Cotizaciones_xbanana/includes
 * @author     Esteban Cordero <shagy.gnx@gmail.com>
 */
class Cotizaciones_xbanana_Template_Loader  {

	/**
	 * The array of templates that this plugin tracks.
	 */
	protected $templates;

	/**
	 * Initializes the plugin by setting filters and administration functions.
	 */
	public function __construct() {

		// Add your templates to this array.
		$this->templates = array(
			'designer-flow-template.php' => 'Designer Flow',
			'non-designer-flow-template.php' => 'Non Designer Flow',
			'no-idea-flow-template.php' => 'No idea Flow',
		);
	}

	/**
	 * Adds our template to the page dropdown for v4.7+
	 *
	 */
	public function add_new_template( $posts_templates ) {
		$posts_templates = array_merge( $posts_templates, $this->templates );
		return $posts_templates;
	}

	/**
	 * Adds our template to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doens't really exist.
	 */
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list.
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		}

		// New cache, therefore remove the old one
		wp_cache_delete( $cache_key , 'themes');

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	}

	/**
	 * Checks if the template is assigned to the page
	 */
	public function view_project_template( $template ) {

		// Get global post
		global $post;

		// Return template if post is empty
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined
		if ( ! isset( $this->templates[get_post_meta(
			$post->ID, '_wp_page_template', true
		)] ) ) {
			return $template;
		}

		$file = plugin_dir_path( dirname( __FILE__ ) ) . "templates/" . get_post_meta(
			$post->ID, '_wp_page_template', true
		);

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} else {
			echo $file;
		}

		// Return template
		return $template;

	}

}