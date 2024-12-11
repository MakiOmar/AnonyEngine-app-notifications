<?php //phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Firebase cloud messaging
 *
 * @package firebase cloud messaging
 */

namespace FbCloudMessaging;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

defined( 'ABSPATH' ) || die;
/**
 * Class FirebaseCloudMessaging
 *
 * A class to handle Firebase Cloud Messaging using HTTP v1 API.
 */
class AnotfFirebaseCloudMessaging {

	/**
	 * Firebase project ID.
	 *
	 * @var string
	 */
	protected $project_id;

	/**
	 * OAuth 2.0 Bearer Token for Firebase API.
	 *
	 * @var string
	 */
	protected $auth_token;

	/**
	 * Path to the Firebase Service Account JSON.
	 *
	 * @var string
	 */
	protected $service_account_json_path;

	/**
	 * Guzzle HTTP client.
	 *
	 * @var \GuzzleHttp\Client
	 */
	protected $guzzle_client;
	/**
	 * Firebase API URL for sending notifications.
	 */
	const DEFAULT_API_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

	/**
	 * Firebase API URL for batch adding topic subscriptions.
	 */
	const DEFAULT_TOPIC_ADD_SUBSCRIPTION_API_URL = 'https://iid.googleapis.com/iid/v1:batchAdd';

	/**
	 * Firebase API URL for batch removing topic subscriptions.
	 */
	const DEFAULT_TOPIC_REMOVE_SUBSCRIPTION_API_URL = 'https://iid.googleapis.com/iid/v1:batchRemove';

	/**
	 * FirebaseCloudMessaging constructor.
	 *
	 * @param string $project_id Firebase project ID.
	 * @param string $service_account_json_path Path to the service account JSON file.
	 */
	public function __construct( $project_id, $service_account_json_path ) {
		$this->project_id                = sanitize_text_field( $project_id );
		$this->service_account_json_path = $service_account_json_path;
		$this->guzzle_client             = new GuzzleClient();
	}

	/**
	 * Fetches the OAuth 2.0 Bearer token using a Service Account.
	 *
	 * @return string The OAuth 2.0 Bearer Token.
	 * @throws \Exception If authentication fails.
	 */
	public function get_auth_token() {
		$scopes      = array( 'https://www.googleapis.com/auth/firebase.messaging' );
		$credentials = new ServiceAccountCredentials( $scopes, $this->service_account_json_path );
		$auth_token  = $credentials->fetchAuthToken();

		if ( ! isset( $auth_token['access_token'] ) ) {
			throw new \Exception( 'Failed to retrieve Firebase Auth Token.' );
		}

		$this->auth_token = $auth_token['access_token'];
		return $this->auth_token;
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
		// Ensure we have the auth token.
		if ( ! $this->auth_token ) {
			$this->get_auth_token();
		}

		// Define the notification payload.
		$payload = array(
			'message' => array(
				'token'        => sanitize_text_field( $target ), // Ensure this is the web FCM token.
				'notification' => array(
					'title' => sanitize_text_field( $title ),
					'body'  => sanitize_text_field( $body ),
				),
				'webpush'      => array(
					'notification' => array(
						'title'        => sanitize_text_field( $title ),
						'body'         => sanitize_text_field( $body ),
						'click_action' => sanitize_text_field( $click_action ), // URL or action on click.
					),
				),
			),
		);
		try {
			$response = $this->guzzle_client->post(
				$this->get_api_url(),
				array(
					'headers' => array(
						'Authorization' => sprintf( 'Bearer %s', $this->auth_token ),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
				)
			);
			return $response;
		} catch ( RequestException $e ) {
			throw new \Exception( 'Failed to send Firebase notification: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Subscribes a device to a topic.
	 *
	 * @param string       $topic The topic to subscribe to.
	 * @param string|array $device_tokens The device token(s) to subscribe.
	 *
	 * @return \Psr\Http\Message\ResponseInterface The response from Firebase.
	 * @throws \Exception On request failure.
	 */
	public function subscribe_to_topic( $topic, $device_tokens ) {
		if ( ! is_array( $device_tokens ) ) {
			$device_tokens = array( sanitize_text_field( $device_tokens ) );
		} else {
			$device_tokens = array_map( 'sanitize_text_field', $device_tokens );
		}

		$payload = array(
			'to'                  => '/topics/' . sanitize_text_field( $topic ),
			'registration_tokens' => $device_tokens,
		);

		try {
			$response = $this->guzzle_client->post(
				self::DEFAULT_TOPIC_ADD_SUBSCRIPTION_API_URL,
				array(
					'headers' => array(
						'Authorization' => sprintf( 'Bearer %s', $this->auth_token ),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
				)
			);
			return $response;
		} catch ( RequestException $e ) {
			throw new \Exception( 'Failed to subscribe to topic: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Unsubscribes a device from a topic.
	 *
	 * @param string       $topic The topic to unsubscribe from.
	 * @param string|array $device_tokens The device token(s) to unsubscribe.
	 *
	 * @return \Psr\Http\Message\ResponseInterface The response from Firebase.
	 * @throws \Exception On request failure.
	 */
	public function unsubscribe_from_topic( $topic, $device_tokens ) {
		if ( ! is_array( $device_tokens ) ) {
			$device_tokens = array( sanitize_text_field( $device_tokens ) );
		} else {
			$device_tokens = array_map( 'sanitize_text_field', $device_tokens );
		}

		$payload = array(
			'to'                  => '/topics/' . sanitize_text_field( $topic ),
			'registration_tokens' => $device_tokens,
		);

		try {
			$response = $this->guzzle_client->post(
				self::DEFAULT_TOPIC_REMOVE_SUBSCRIPTION_API_URL,
				array(
					'headers' => array(
						'Authorization' => sprintf( 'Bearer %s', $this->auth_token ),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
				)
			);
			return $response;
		} catch ( RequestException $e ) {
			throw new \Exception( 'Failed to unsubscribe from topic: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Returns the full API URL for Firebase HTTP v1.
	 *
	 * @return string The API URL.
	 */
	private function get_api_url() {
		return sprintf( self::DEFAULT_API_URL, $this->project_id );
	}
	/**
	 * Adds devices for notifications to Firebase.
	 *
	 * @param array $devices Array of device tokens to be added.
	 *
	 * @return mixed Response from Firebase.
	 * @throws \Exception On request failure.
	 */
	public function add_devices( array $devices ) {
		// Ensure we have the auth token.
		if ( ! $this->auth_token ) {
			$this->get_auth_token();
		}

		// Prepare the payload for adding devices to Firebase.
		$payload = array(
			'registration_tokens' => $devices,
		);

		try {
			// Send the request to the Firebase API to add the devices.
			$response = $this->guzzle_client->post(
				self::DEFAULT_TOPIC_ADD_SUBSCRIPTION_API_URL,
				array(
					'headers' => array(
						'Authorization' => sprintf( 'Bearer %s', $this->auth_token ),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
				)
			);

			// Return the response from Firebase.
			return json_decode( $response->getBody()->getContents(), true );
		} catch ( RequestException $e ) {
			throw new \Exception( 'Failed to add devices: ' . esc_html( $e->getMessage() ) );
		}
	}
}
