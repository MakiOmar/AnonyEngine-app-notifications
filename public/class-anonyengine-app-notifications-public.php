<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://https://github.com/MakiOmar
 * @since      1.0.0
 *
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/public
 */

use FbCloudMessaging\AnonyengineFirebase as fb_notf;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/public
 * @author     Makiomar <maki3omar@gmail.com>
 */
class Anonyengine_App_Notifications_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Firebase instance
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $firebase    Firebase instance.
	 */
	private $firebase;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->firebase    = new fb_notf();

		ANOAPP_REST::instance();
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Anonyengine_App_Notifications_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Anonyengine_App_Notifications_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/anonyengine-app-notifications-public.css', array(), time(), 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Anonyengine_App_Notifications_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Anonyengine_App_Notifications_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( is_user_logged_in() ) {
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/anonyengine-app-notifications-public.js', array( 'jquery' ), time(), false );
			// Localize the AJAX object with a nonce for security.
			wp_localize_script(
				$this->plugin_name,
				'anotf_ajax_object',
				array(
					'nonce'   => wp_create_nonce( 'anotf-ajax-nonce' ),
					'sound'   => esc_url( plugin_dir_url( __FILE__ ) . 'sounds/sound-1.mp3' ),
					'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
				)
			);
			$this->firebase->enqueue_scripts();
		}
	}
	/**
	 * Mark as read
	 *
	 * @return void
	 */
	public function mark_user_notification_read() {
		$_req = isset( $_POST ) ? wp_unslash( $_POST ) : array();
		// Verify the nonce.
		if ( isset( $_req['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( $_req['nonce'] ), 'anotf-ajax-nonce' ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}
		if ( is_user_logged_in() && isset( $_req['notification_id'] ) ) {
			$read_notifications = get_user_meta( get_current_user_id(), 'read_notifications', true );
			$read_notifications = $read_notifications ? explode( ',', $read_notifications ) : array();
			if ( ! in_array( $_req['notification_id'], $read_notifications, true ) ) {
				$read_notifications[] = $_req['notification_id'];
				update_user_meta( get_current_user_id(), 'read_notifications', implode( ',', $read_notifications ) );
			}

			// Return a success response.
			wp_send_json_success();
		} else {
			// Return an error response if not authenticated or notification_id is not provided.
			wp_send_json_error( 'Invalid request.' );
		}
	}
	/**
	 * Get notifications
	 *
	 * @return mixed
	 */
	public function get_user_notifications() {
		if ( is_user_logged_in() ) {
			$read_notifications = get_user_meta( get_current_user_id(), 'read_notifications', true );
			//phpcs:disable
			// Fetch unread notifications for the current user from the database.
			global $wpdb;
			$user_id             = get_current_user_id();
			$user_ids            = array( 0, $user_id );
			$placeholders        = array_fill( 0, count( $user_ids ), '%d' );
			$placeholders_string = implode( ', ', $placeholders );
			$cache_key           = 'current_user_notifications_' . $user_id;
			$results             = wp_cache_get( $cache_key );
			if ( ! $results ) {
				if ( empty( $read_notifications ) ) {
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}anony_notifications WHERE user_id IN ({$placeholders_string}) ORDER BY created_at DESC",
							$user_ids
						)
					);
				} else {
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}anony_notifications WHERE user_id IN ({$placeholders_string}) AND id NOT IN ({$read_notifications}) ORDER BY created_at DESC",
							$user_ids
						)
					);
				}
				
				wp_cache_set( $cache_key, $results, '', 5 );
			}
			return $results;
		}
		return false;
	}
	/**
	 * Display notifications
	 *
	 * @return void
	 */
	public function display_user_notifications() {
		$notifications = $this->get_user_notifications();

		return $this->render_notifications( $notifications );
	}
	/**
	 * Render notification items
	 *
	 * @param array $notifications Notifications.
	 * @return string
	 */
	function render_notification_items( $notifications ) {
		$read_notifications = get_user_meta( get_current_user_id(), 'read_notifications', true );
		$read_notifications = $read_notifications ? explode( ',', $read_notifications ) : array();

		$output = '';
		foreach ( $notifications as $index => $notification ) {
			if ( $index === 20 ) {
				return $output;
			}
			if ( in_array( $notification->id,  $read_notifications, true ) ) {
				$class = 'anotf-item anotf-notification-read';
			} else {
				$class = 'anotf-item';
			}
			if ( ! empty( $notification->link ) ) {
				$msg = '<a href="' . esc_url( $notification->link ) . '"> ' . wp_strip_all_tags( $notification->message ) . ' </a>';
			} else {
				$msg = wp_strip_all_tags( $notification->message );
			}
			$output .= '<li class="' . $class . '" data-notification-id="' . esc_attr( $notification->id ) . '">' . $msg . '</li>';
		}
		return $output;
	}
	/**
	 * Render notifications
	 *
	 * @param array $notifications Notifications.
	 * @return string
	 */
	public function render_notifications( $notifications ) {
		$has_new_notif = get_user_meta( get_current_user_id(), 'anotif-has-new', true );
		$class = 'anotf-notification-status';
		if ( $has_new_notif && ! empty( $has_new_notif ) ) {
			$class .= ' anotf-has-new';
		}
		$unread_count = 0;
		$output       = '<div class="anotf-notification-bell-container">';
		// Display the notification bell.
		
		$output      .= '<a href="#" class="anotf-notification-bell">';
		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		$output .= '<svg xmlns="http://www.w3.org/2000/svg" width="21" height="20" viewBox="0 0 21 20" fill="none"><path d="M10.5001 5.441V8.771M19.0901 14.17C19.8201 15.39 19.2401 16.97 17.8901 17.42C13.1088 19.01 7.94134 19.01 3.16005 17.42C1.72005 16.94 1.17005 15.48 1.96005 14.17L3.23005 12.05C3.58005 11.47 3.86005 10.44 3.86005 9.77V7.67C3.85874 6.79456 4.03004 5.92744 4.36414 5.11826C4.69825 4.30907 5.18862 3.5737 5.80718 2.9542C6.42575 2.33471 7.16039 1.84324 7.96907 1.50792C8.77775 1.1726 9.64461 0.999999 10.5201 1C14.1801 1 17.1801 4 17.1801 7.66V9.76C17.1801 9.94 17.2001 10.14 17.2301 10.35" stroke="black" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round"></path></svg>
		<span class="' . $class . '"></span>';
		//phpcs:enable
		$output .= '</a>';

		// Display the notifications list.
		$output .= '<div class="anotf-notifications-list">';
		$output .= '<ul>';
		if ( $notifications && is_array( $notifications ) ) {
			$unread_count = count( $notifications );
			$output      .= $this->render_notification_items( $notifications );
		}
		$output .= '</ul>';
		$output .= '</div>';
		$output .= '<input type="hidden" id="anotf-old-count" value="' . $unread_count . '"/>';
		$output .= '</div>';
		return $output;
	}
	/**
	 * Retrieve notifications
	 *
	 * @return void
	 */
	public function retrieve_notifications() {
		$_req = isset( $_POST ) ? wp_unslash( $_POST ) : array();
		// Verify the nonce.
		if ( isset( $_req['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( $_req['nonce'] ), 'anotf-ajax-nonce' ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}
		$notifications = $this->get_user_notifications();
		if ( $notifications && is_array( $notifications ) ) {
			// Prepare the notifications data to be sent as a response.
			$output = $this->render_notification_items( $notifications );
			if ( isset( $_req['oldNotfCount'] ) && count( $notifications ) > absint( $_req['oldNotfCount'] ) ) {
				update_user_meta( get_current_user_id(), 'anotif-has-new', 'yes' );
			}

			// Prepare the response data.
			$response = array(
				'count'         => count( $notifications ),
				'notifications' => $output,
			);

			// Send the JSON response.
			wp_send_json_success( $response );
		} else {
			// Return an error response if not authenticated.
			wp_send_json_error( 'Invalid request.' );
		}
	}
	/**
	 * Notifications status
	 *
	 * @return void
	 */
	public function notifications_status() {
		$_req = isset( $_POST ) ? wp_unslash( $_POST ) : array();
		// Verify the nonce.
		if ( isset( $_req['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( $_req['nonce'] ), 'anotf-ajax-nonce' ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}
		$del = delete_user_meta( get_current_user_id(), 'anotif-has-new' );
		wp_send_json(
			array(
				'resp' => $del,
			)
		);
		die();
	}
}
