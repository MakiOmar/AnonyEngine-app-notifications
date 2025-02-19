<?php //phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Send Firebase web notifications
 *
 * @link       https://github.com/MakiOmar
 * @since      1.0.0
 *
 * @package    Anonyengine_App_Notifications
 * @subpackage Anonyengine_App_Notifications/public
 */

namespace FbCloudMessaging;

use FbCloudMessaging\AnotfFirebaseCloudMessaging as fbcm;

defined( 'ABSPATH' ) || die();

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.DB.DirectDatabaseQuery.DirectQuery

/**
 * Firebase class for handling notifications.
 */
class AnonyengineFirebase {

	/**
	 * Firebase instance.
	 *
	 * @var object
	 */
	protected $fb = null;

	/**
	 * Service worker file name.
	 *
	 * @var string
	 */
	protected $service_worker_file = 'firebase-messaging-sw.js';

	/**
	 * Web push key pair.
	 *
	 * @var string
	 */
	protected $key_pair;

	/**
	 * Firebase configuration.
	 *
	 * @var string
	 */
	protected $fb_config;

	/**
	 * Firebase client.
	 *
	 * @var object
	 */
	protected $client;

	/**
	 * Settings.
	 *
	 * @var mixed
	 */
	protected $settings;

	/**
	 * Firebase messaging object.
	 *
	 * @var object
	 */
	protected $message;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->settings  = get_option( 'Anoapp_Options', array() );
		$settings        = $this->settings;
		$this->key_pair  = isset( $this->settings['settings_key_pair'] ) ? sanitize_text_field( $this->settings['settings_key_pair'] ) : false;
		$this->fb_config = isset( $this->settings['firebase_config'] ) ? $this->settings['firebase_config'] : false;

		if ( ! $this->key_pair || ! $this->fb_config ) {
			$this->admin_notice();
			return;
		}
		add_action(
			'admin_init',
			function () use ( $settings ) {
				// Check if the service account JSON setting is provided.
				if ( ! empty( $settings['service_account_json'] ) ) {

					// Define a private directory path within wp-content.
					$private_dir = WP_CONTENT_DIR . '/private-files';

					// Ensure the directory exists or create it.
					if ( ! is_dir( $private_dir ) ) {
						wp_mkdir_p( $private_dir );
					}

					// Protect the directory with an .htaccess file (Apache servers).
					$htaccess_path = trailingslashit( $private_dir ) . '.htaccess';
					if ( ! file_exists( $htaccess_path ) ) {
						file_put_contents( $htaccess_path, "Order Allow,Deny\nDeny from all" );
					}

					// Define the file path in the private directory.
					$file_path = trailingslashit( $private_dir ) . 'anotf_service_account.json';

					// Initialize the WordPress Filesystem API.
					if ( ! function_exists( 'WP_Filesystem' ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
					}
					WP_Filesystem();

					global $wp_filesystem;

					// Check if the file already exists.
					if ( ! $wp_filesystem->exists( $file_path ) ) {
						// Write the JSON string directly to the file.
						if ( $wp_filesystem->put_contents( $file_path, $settings['service_account_json'], FS_CHMOD_FILE ) ) {
							// Store the file path in an option.
							update_option( 'anotf_service_account_json', $file_path );
						} else {
							// Handle error if the file could not be created.
							error_log( 'Failed to create service account JSON file at ' . $file_path );
						}
					} elseif ( get_option( 'anotf_service_account_json' ) !== $file_path ) {
						update_option( 'anotf_service_account_json', $file_path );
					}
				}
			}
		);

		if ( get_option( 'anotf_service_account_json' ) ) {
			$this->fb = new fbcm( $this->settings['firebase_project_id'], get_option( 'anotf_service_account_json' ) );
		}
		$fb = $this->fb;
		add_action( 'rest_api_init', array( $this, 'register_set_device_token_endpoint' ) );
		add_action( 'wp_ajax_anotf_set_device_token', array( $this, 'set_device_token_cb' ) );
		add_action( 'admin_footer', array( $this, 'create_service_worker' ) );
		add_action( 'wp_footer', array( $this, 'inline_scripts' ) );
	}
	/**
	 * Sends a push notification to a device or topic.
	 *
	 * @param string $title Notification title.
	 * @param string $body Notification body.
	 * @param string $target Target device token or topic (e.g., /topics/myTopic).
	 * @param string $click_action Click action for the notification.
	 *
	 * @return \Psr\Http\Message\ResponseInterface The response from Firebase.
	 * @throws \Exception On request failure or invalid response.
	 */
	public function send_notification( $title, $body, $target, $click_action = '' ) {
		if ( ! is_null( $this->fb ) ) {
			return $this->fb->send_notification( $title, $body, $target, $click_action );
		}
	}
	/**
	 * Create the service worker file.
	 *
	 * @return void
	 */
	public function create_service_worker() {
		// Set the path to the root directory of your WordPress installation.
		$sw_path = ABSPATH . sanitize_file_name( $this->service_worker_file );

		// Initialize the WP Filesystem API.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;
		WP_Filesystem();

		// Check if the service worker file already exists in the root.
		if ( ! $wp_filesystem->exists( $sw_path ) ) {
			// Generate the service worker content using the full Firebase config.
			$sw_content = <<<EOT
				importScripts('https://www.gstatic.com/firebasejs/9.14.0/firebase-app-compat.js');
				importScripts('https://www.gstatic.com/firebasejs/9.14.0/firebase-messaging-compat.js');
				{$this->fb_config}
				const app = firebase.initializeApp(firebaseConfig);
				const messaging = firebase.messaging();

				messaging.onBackgroundMessage(function (payload) {
					if (!payload.hasOwnProperty('notification')) {
						const notificationTitle = payload.data.title;
						const notificationOptions = {
							body: payload.data.body,
							icon: payload.data.icon,
							image: payload.data.image
						};
						self.registration.showNotification(notificationTitle, notificationOptions);
						self.addEventListener('notificationclick', function (event) {
							const clickedNotification = event.notification;
							clickedNotification.close();
							event.waitUntil(
								clients.openWindow(payload.data.click_action)
							);
						});
					}
				});
				EOT;

			// Create the service worker file in the root directory.
			$wp_filesystem->put_contents( $sw_path, $sw_content, FS_CHMOD_FILE );
		}
	}

	/**
	 * Admin notice for missing server key.
	 *
	 * @return void
	 */
	protected function admin_notice() {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e( 'App notifications Firebase is not configured or misconfigured!', 'anonyengine-app-notifications' ); ?></p>
				</div>
				<?php
			}
		);
	}

	/**
	 * Trigger a notification for a specific user or all users.
	 *
	 * @param string $title        Notification title.
	 * @param string $body         Notification body.
	 * @param mixed  $user_id      User ID or false for all users.
	 * @param string $click_action Click action for the notification.
	 * @return mixed
	 */
	public function trigger_notifier( $title, $body, $user_id = false, $click_action = '' ) {
		$tokens   = $this->set_devices( $user_id );
		$response = $this->notify( $tokens, $title, $body, $click_action );
		return $response;
	}

	/**
	 * Get devices and set them for the notification.
	 *
	 * @param mixed $user_id User ID or false for all users.
	 * @return array
	 */
	public function set_devices( $user_id = false ) {
		$args = array(
			'post_type'      => 'anoapp_devices',
			'posts_per_page' => -1,
		);
		if ( $user_id ) {
			$args['author'] = absint( $user_id );
		}
		$devices = get_posts( $args );
		if ( is_array( $devices ) ) {
			$tokens = wp_list_pluck( $devices, 'post_excerpt' );
			if ( ! empty( $tokens ) ) {
				// No need because devices already added using ajax
				// $this->add_devices( $tokens );
			}
		}
		return $tokens;
	}

	/**
	 * Add devices to the notification.
	 *
	 * @param array $devices Devices tokens.
	 * @return void
	 */
	public function add_devices( array $devices ) {
		// Call the add_devices method to register the devices.
		try {
			$response = $this->fb->add_devices( $devices );

			// You can log or handle the response as needed.
			if ( isset( $response['success'] ) ) {
				// Handle success, e.g., log or notify the user.
				error_log( sprintf( 'Successfully added devices: %s', implode( ', ', $devices ) ) );
			} else {
				// Handle failure response.
				error_log( sprintf( 'Failed to add devices: %s', print_r( $response, true ) ) );
			}
		} catch ( \Exception $e ) {
			// Handle exception, e.g., log the error message.
			error_log( 'Error adding devices: ' . esc_html( $e->getMessage() ) );
		}
	}


	/**
	 * Send the notification.
	 *
	 * @param array  $tokens       Devices tokens.
	 * @param string $title        Notification title.
	 * @param string $body         Notification body.
	 * @param string $click_action Click action for the notification.
	 * @return mixed
	 */
	public function notify( $tokens, $title, $body, $click_action = '' ) {
		if ( is_array( $tokens ) ) {
			foreach ( $tokens as $token ) {
				$this->fb->send_notification( $title, $body, $token, $click_action );
			}
		}
	}
	/**
	 * Returns arguments for the subscribe route.
	 *
	 * @return array Route arguments.
	 */
	private function get_subscribe_args() {
		return array(
			'rest_api_key' => array(
				'validate_callback' => function ( $param ) {
					return ! empty( $param );
				},
				'required'          => true,
			),
			'device_token' => array(
				'validate_callback' => function ( $param ) {
					return ! empty( $param );
				},
				'required'          => true,
			),
			'user_id'      => array(
				'validate_callback' => function ( $param ) {
					return ! empty( $param );
				},
				'required'          => true,
			),
			'device_uuid'  => array(
				'validate_callback' => function ( $param ) {
					return ! empty( $param );
				},
				'required'          => false,
			),
			'subscription' => array(
				'validate_callback' => function ( $param ) {
					return ! empty( $param );
				},
				'required'          => false,
			),
			'device_name'  => array(
				'required' => false,
			),
			'os_version'   => array(
				'required' => false,
			),
		);
	}
	/**
	 * Register the REST API endpoint.
	 *
	 * @return void
	 */
	public function register_set_device_token_endpoint() {
		register_rest_route(
			'anotf/v1',
			'/set-device-token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_api_set_token' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_subscribe_args(),
			)
		);
	}

	/**
	 * REST API callback for setting device token.
	 *
	 * @param object $request Request object.
	 * @return mixed
	 */
	public function check_permission( $request ) {
		$options     = get_option( ANONYENGINE_APP_NOTIFICATIONS_OPTIONS );
		$json_params = $request->get_json_params();
		if ( ! $json_params || empty( $json_params ) ) {
			$json_params = $request->get_params();
		}
		if ( $options['rest_api_key'] !== $json_params['rest_api_key'] ) {
			$response = array(
				'status'  => 'error',
				'message' => 'Unauthorized',
			);
			return rest_ensure_response( $response )->set_status( 401 );
		}

		return true; // Allow the request if the key matches.
	}
	/**
	 * Check if a device token exists.
	 *
	 * @param string $token Device token.
	 * @return bool
	 */
	public function token_exists( $token ) {
		global $wpdb;

		$post_type = 'anoapp_devices';
		$results   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}posts WHERE post_type = %s AND post_excerpt = %s AND post_status = 'publish'",
				$post_type,
				sanitize_text_field( $token )
			)
		);

		return $results && is_array( $results ) && ! empty( $results );
	}
	/**
	 * REST API callback for setting device token.
	 *
	 * @param object $request Request object.
	 * @return mixed
	 */
	public function rest_api_set_token( $request ) {
		$parameters = $request->get_params();

		$user_id = intval( $parameters['user_id'] );
		// Check if the user exists.
		if ( ! get_userdata( $user_id ) ) {
			$response = new \WP_Error(
				'unauthorized',
				esc_html__( 'Unauthorized', 'anotf' ),
				array( 'status' => 401 )
			);
			return rest_ensure_response( $response );
		}

		// Check if the required parameters are present.
		$device_token = sanitize_text_field( $parameters['device_token'] );

		if ( $this->token_exists( $device_token ) ) {
			$response = new \WP_Error(
				'error',
				esc_html__( 'Token already exists', 'anotf' ),
				array( 'status' => 401 )
			);
			return rest_ensure_response( $response );
		}

		$insert = wp_insert_post(
			array(
				'post_title'   => 'Device #' . uniqid(),
				'post_type'    => 'anoapp_devices',
				'post_excerpt' => $device_token,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
			)
		);
		if ( $insert ) {
			$response = array( 'id' => $insert );
		} else {
			$response = new \WP_Error(
				'error',
				esc_html__( 'Something wrong happend.', 'anotf' ),
				array( 'status' => 401 )
			);
		}
		return rest_ensure_response( $response );
	}

	/**
	 * Handle AJAX callback for setting device token.
	 *
	 * @return void
	 */
	public function set_device_token_cb() {
		$_req = isset( $_POST ) ? wp_unslash( $_POST ) : array();
		// Verify the nonce.
		if ( isset( $_req['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( $_req['nonce'] ), 'anotf-ajax-nonce' ) ) {
			wp_send_json_error( 'Invalid nonce.' );
		}
		global $wpdb;
		$uuid = sanitize_text_field( $_req['uuid'] );
		if ( 'updating' === $_req['status'] ) {
			// Directly delete posts where post_content matches the given UUID.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->posts} WHERE post_type = %s AND post_content = %s",
					'anoapp_devices',
					$uuid
				)
			);
		}

		$resp = false;
		if ( ! empty( $_req['token'] ) && ! $this->token_exists( sanitize_text_field( $_req['token'] ) ) ) {
			$insert = wp_insert_post(
				array(
					'post_title'   => 'Device #' . uniqid(),
					'post_type'    => 'anoapp_devices',
					'post_excerpt' => sanitize_text_field( $_req['token'] ),
					'post_content' => sanitize_text_field( $_req['uuid'] ),
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
				)
			);
			if ( ! $insert ) {
				wp_send_json_error( 'Failed to insert device token.', 500 );
			}

			$resp = $insert;
		}

		wp_send_json( array( 'resp' => $resp ) );
		die();
	}

	/**
	 * Enqueue Firebase and FCM scripts.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'firebase-app-compat', 'https://www.gstatic.com/firebasejs/9.14.0/firebase-app-compat.js', array(), '0.1', false );
		wp_enqueue_script( 'firebase-messaging-compat', 'https://www.gstatic.com/firebasejs/9.14.0/firebase-messaging-compat.js', array(), '0.1', false );
	}

	/**
	 * Inline Firebase and FCM Scripts.
	 *
	 * This function initializes Firebase, handles FCM token registration, and uses a service worker for background notifications.
	 *
	 * @return void
	 */
	public function inline_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		?>
		<script delay-exclude>
			jQuery(document).ready(function($) {

				if (typeof firebase === 'undefined' || typeof firebase.messaging === 'undefined') {
					return;
				}

				<?php
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wp_strip_all_tags( $this->fb_config );
				?>

				// Initialize Firebase
				const app = firebase.initializeApp(firebaseConfig);
				const messaging = firebase.messaging();
				// Check if Notification permissions are already granted
				if (Notification.permission === 'granted') {
					initializeFirebaseMessaging();
				} else if (Notification.permission !== 'denied') {
					// Request permission for notifications using the browser's native API
					Notification.requestPermission().then(permission => {
						if (permission === 'granted') {
							console.log('Notification permission granted.');
							initializeFirebaseMessaging();
						} else {
							console.log('Notification permission denied.');
						}
					}).catch(err => {
						console.log('Unable to get permission to notify.', err);
					});
				}

				function initializeFirebaseMessaging() {
					// Register service worker for background notifications
					if ('serviceWorker' in navigator) {
						navigator.serviceWorker.register('/' + '<?php echo $this->service_worker_file; ?>')
							.then((registration) => {
								// Pass the registration to Firebase Messaging automatically handled in newer versions
								return messaging.getToken({ vapidKey: "<?php echo $this->key_pair; ?>" });
							})
							.then((currentToken) => {
								if (currentToken) {
									sendTokenToServer(currentToken);
								} else {
									console.log('No registration token available. Request permission to generate one.');
								}
							}).catch((err) => {
								console.log('Error getting token', err);
								setTokenSentToServer(false);
							});
					}

				}

				// Handle incoming messages in the foreground
				messaging.onMessage((payload) => {
					console.log('Message received. ', payload);
					// Display notification or handle the payload here
				});

				// Send token to server for saving
				function sendTokenToServer(currentToken) {
					if (window.localStorage.getItem('fcmDeviceToken-<?php echo get_current_user_id(); ?>') !== currentToken) {
						console.log('Updating token to server...');
						setTokenAjax(currentToken, 'updating');
					} else if (!isTokenSentToServer()) {
						console.log('Sending token to server...');
						setTokenAjax(currentToken);
					} else {
						console.log('Token already sent to server.');
					}
				}
				function generateUUID() {
					return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
						var r = Math.random() * 16 | 0,
							v = c == 'x' ? r : (r & 0x3 | 0x8);
						return v.toString(16);
					});
				}

				function getStoredUUID() {
					let storedUUID = window.localStorage.getItem('fcmUUID');
					if (!storedUUID) {
						storedUUID = generateUUID();
						window.localStorage.setItem('fcmUUID', storedUUID);
					}
					return storedUUID;
				}

				// Set token in the server
				function setTokenAjax(currentToken, status = 'inserting') {
					let uuid = getStoredUUID();
					$.ajax({
						url: anotf_ajax_object.ajaxUrl,
						type: 'POST',
						data: {
							action: 'anotf_set_device_token',
							token: currentToken,
							uuid: uuid,
							status: status,
							_ajax_nonce: anotf_ajax_object.nonce // Include the nonce for security
						},
						success: function(response) {
							if (response.resp) {
								setTokenSentToServer(true);
								window.localStorage.setItem('fcmDeviceToken-<?php echo get_current_user_id(); ?>', currentToken);
							}
						},
						error: function(error) {
							console.log('Error:', error);
						}
					});
				}

				// Check if token is already sent to the server
				function isTokenSentToServer() {
					return window.localStorage.getItem('sentToServer-<?php echo get_current_user_id(); ?>') === '1';
				}

				// Set token sent flag
				function setTokenSentToServer(sent) {
					window.localStorage.setItem('sentToServer-<?php echo get_current_user_id(); ?>', sent ? '1' : '0');
				}
			});
		</script>
		<?php
	}
}