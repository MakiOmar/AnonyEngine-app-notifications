<?php
/**
 * Fired during plugin activation
 *
 * @link       https://https://github.com/MakiOmar
 * @since      1.0.0
 *
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/includes
 * @author     Makiomar <maki3omar@gmail.com>
 */
class Anonyengine_App_Notifications_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::notifications_table();
	}

	/**
	 * Create the custom table.
	 *
	 * @return void
	 */
	public static function notifications_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'anony_notifications';
		$collate    = $wpdb->get_charset_collate();

		// SQL statement to create the table.
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id INT(11) UNSIGNED AUTO_INCREMENT,
			user_id INT(11) UNSIGNED,
			message TEXT,
			link TEXT,
			created_at DATETIME,
			PRIMARY KEY (id)
		) $collate";
		// Execute the SQL statement.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
