<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://github.com/aradiquez
 * @since      1.0.0
 *
 * @package    Cotizaciones_xbanana
 * @subpackage Cotizaciones_xbanana/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Cotizaciones_xbanana
 * @subpackage Cotizaciones_xbanana/includes
 * @author     Esteban Cordero <shagy.gnx@gmail.com>
 */
class Cotizaciones_xbanana_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'cotizaciones_xbanana',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
