<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://github.com/MakiOmar
 * @since             1.0.0
 * @package           Anonyengine_App_Notifications
 *
 * @wordpress-plugin
 * Plugin Name:       AnonyEngine app notifications
 * Plugin URI:        https://https://github.com/MakiOmar/AnonyEngine-app-notifications
 * Description:       Send real time notifications to mobile apps
 * Version:           1.0.0
 * Author:            Makiomar
 * Author URI:        https://https://github.com/MakiOmar
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       anonyengine-app-notifications
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ANONYENGINE_APP_NOTIFICATIONS_VERSION', '1.0.0' );

/**
 * Currently plugin options slug.
 */
define( 'ANONYENGINE_APP_NOTIFICATIONS_OPTIONS', 'Anoapp_Options' );

/**
 * Holds plugin PATH
 *
 * @const
 */
define( 'ANOTF_DIR', wp_normalize_path( plugin_dir_path( __FILE__ ) ) );

require_once ANOTF_DIR . 'vendor/autoload.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-anonyengine-app-notifications-activator.php
 */
function activate_anonyengine_app_notifications() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-anonyengine-app-notifications-activator.php';
	Anonyengine_App_Notifications_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-anonyengine-app-notifications-deactivator.php
 */
function deactivate_anonyengine_app_notifications() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-anonyengine-app-notifications-deactivator.php';
	Anonyengine_App_Notifications_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_anonyengine_app_notifications' );
register_deactivation_hook( __FILE__, 'deactivate_anonyengine_app_notifications' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-anonyengine-app-notifications.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_anonyengine_app_notifications() {

	$plugin = new Anonyengine_App_Notifications();
	$plugin->run();
}
run_anonyengine_app_notifications();
