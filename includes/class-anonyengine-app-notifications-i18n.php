<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://https://github.com/MakiOmar
 * @since      1.0.0
 *
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/includes
 * @author     Makiomar <maki3omar@gmail.com>
 */
class Anonyengine_App_Notifications_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'anonyengine-app-notifications',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
