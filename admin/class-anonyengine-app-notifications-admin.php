<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://github.com/MakiOmar
 * @since      1.0.0
 *
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/admin
 * @author     Makiomar <maki3omar@gmail.com>
 */
class Anonyengine_App_Notifications_Admin {

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'init', array( $this, 'plugin_options' ) );
		/**
		 * Extend custom post types
		 */
		add_filter( 'anony_post_types', array( $this, 'post_types' ) );

		/**
		 * Extend custom Taxonomies
		 */
		add_filter( 'anony_taxonomies', array( $this, 'taxonomies' ) );

		/**
		 * Extend taxonomies' posts
		 */
		add_filter( 'anony_taxonomy_posts', array( $this, 'post_taxonomy' ) );

		/**
		 * Alter device post type args
		 */
		add_filter( 'anony_anoapp_devices_args', array( $this, 'device_supports' ) );

		$this->notifications_actions();
	}
	/**
	 * Notifications actions
	 *
	 * @return void
	 */
	public function notifications_actions() {
		add_action(
			'transition_post_status',
			function ( $new_status, $old_status, $post ) {
				if ( 'post' === $post->post_type && 'publish' === $new_status ) {
					$this->insert_notification( $post->post_title, esc_url_raw( get_the_permalink( $post->ID ) ) );
				}
			},
			10,
			3
		);
		// An option page can be used to send notifications.
		$options = apply_filters( 'anotf_on_options_saved', array() );
		if ( is_array( $options ) && ! empty( $options ) ) {
			foreach ( $options as $_option ) {
				$option_name = $_option['name'];
				add_action(
					"add_option_{$option_name}",
					function ( $option, $value ) use ( $_option ) {
						call_user_func(
							$_option['callback'],
							array( $option, $value, $this )
						);
					},
					20,
					2
				);
			}
		}
	}
	/**
	 * Insert notification
	 *
	 * @param string  $notification_message Message.
	 * @param string  $link Link.
	 * @param integer $user_id User ID.
	 * @return mixed
	 */
	public function insert_notification( $notification_message, $link = '', $user_id = 0 ) {
		if ( current_user_can( 'manage_options' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'anony_notifications';
			// Prepare the data to be inserted.
			$data = array(
				'user_id'    => $user_id,
				'message'    => $notification_message,
				'link'       => $link,
				'created_at' => current_time( 'mysql' ),
			);
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $wpdb->insert( $table_name, $data );
		}
	}

	/**
	 * Create plugin's options' page
	 */
	public function plugin_options() {
		if ( ! class_exists( 'ANONY_Options_Model' ) ) {
			return;
		}
		if ( get_option( ANONYENGINE_APP_NOTIFICATIONS_OPTIONS ) ) {
			$options = ANONY_Options_Model::get_instance( ANONYENGINE_APP_NOTIFICATIONS_OPTIONS );
		}

		// Navigation elements.
		$options_nav = array(
			// General --------------------------------------------.
			'firebase' => array(
				'title' => esc_html__( 'Firebase', 'anonyengine-app-notifications' ),
			),

		);

		$sections['firebase'] = array(
			'title'  => esc_html__( 'Firebase Server (API)', 'anonyengine-app-notifications' ),
			'icon'   => 'x',
			'fields' => array(
				array(
					'id'       => 'settings_key_pair',
					'title'    => esc_html__( 'Key pair', 'anonyengine-app-notifications' ),
					'type'     => 'text',
					'validate' => 'no_html',
					'desc'     => esc_html__( 'The web push key pair', 'anonyengine-app-notifications' ) . ' <a href="https://console.firebase.google.com/">' . esc_html__( 'Firebase console', 'anonyengine-app-notifications' ) . '</a>',
				),

				array(
					'id'       => 'firebase_config',
					'title'    => esc_html__( 'Firebase config', 'anonyengine-app-notifications' ),
					'type'     => 'textarea',
					'validate' => 'no_html',
				),
				array(
					'id'       => 'service_account_json',
					'title'    => esc_html__( 'Service account json', 'anonyengine-app-notifications' ),
					'type'     => 'textarea',
					'validate' => 'no_html',
				),
				array(
					'id'       => 'firebase_project_id',
					'title'    => esc_html__( 'Project ID.', 'anonyengine-app-notifications' ),
					'type'     => 'text',
					'validate' => 'no_html',
				),
			),
		);

		$options_page['opt_name']      = ANONYENGINE_APP_NOTIFICATIONS_OPTIONS;
		$options_page['menu_title']    = esc_html__( 'App Notifications', 'anonyengine-app-notifications' );
		$options_page['page_title']    = esc_html__( 'AnonyEngine App Notifications Settings', 'anonyengine-app-notifications' );
		$options_page['menu_slug']     = ANONYENGINE_APP_NOTIFICATIONS_OPTIONS;
		$options_page['page_cap']      = 'manage_options';
		$options_page['icon_url']      = 'dashicons-bell';
		$options_page['page_position'] = 100;
		$options_page['page_type']     = 'menu';

		if ( class_exists( 'ANONY_Theme_Settings' ) ) {
			new ANONY_Theme_Settings( $options_nav, $sections, array(), $options_page );
		}
	}

	/**
	 * Extend custom post types
	 *
	 * @param array $custom_post_types Post types array.
	 *
	 * @return array of post types
	 */
	public function post_types( $custom_post_types ) {
		$custom_posts                    = array();
		$custom_posts ['anoapp_devices'] = array(
			esc_html__( 'Device', 'anonyengine-app-notifications' ),
			esc_html__( 'Devices', 'anonyengine-app-notifications' ),
		);

		return array_merge( $custom_post_types, $custom_posts );
	}

	/**
	 * Extend custom taxonomies
	 *
	 * @param array $anony_custom_taxs Taxonomies array.
	 *
	 * @return array of taxonomies
	 */
	public function taxonomies( $anony_custom_taxs ) {
		$custom_taxs =
		array(
			'anoapp_subscriptions' =>
				array(
					esc_html__( 'Subscription', 'anonyengine-app-notifications' ),
					esc_html__( 'Subscriptions', 'anonyengine-app-notifications' ),
				),

		);

		return array_merge( $anony_custom_taxs, $custom_taxs );
	}
	/**
	 * Extend taxonomies' posts
	 *
	 * @param array $anony_tax_posts of taxonomies' posts.
	 * @return array
	 */
	public function post_taxonomy( $anony_tax_posts ) {
		$tax_posts = array( 'anoapp_subscriptions' => array( 'anoapp_devices' ) );

		return array_merge( $anony_tax_posts, $tax_posts );
	}

	/**
	 * Alter device post type args
	 *
	 * @param array $args Support args.
	 * @return array
	 */
	public function device_supports( $args ) {
		$args['supports'] = array( 'title', 'excerpt' );

		return $args;
	}
	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/anonyengine-app-notifications-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/anonyengine-app-notifications-admin.js', array( 'jquery' ), $this->version, false );
	}
}
