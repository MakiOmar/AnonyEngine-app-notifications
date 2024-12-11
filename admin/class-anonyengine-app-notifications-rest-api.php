<?php if ( ! defined( 'WPINC' ) ) {
	die( "Don't mess with us." ); }

if ( ! class_exists( 'ANOAPP_REST' ) ) :
	class ANOAPP_REST {

		const NAMESPACE = 'anoapp/v1';

		/*
		* Main construct
		*/
		private static $run;

		private function __construct() {
			add_action( 'rest_api_init', array( &$this, 'rest_api_init' ) );
		}

		public function rest_api_init() {

			// Subscribe
			register_rest_route(
				self::NAMESPACE,
				'/subscribe',
				array(
					'methods'             => array( 'POST', 'GET' ),
					'permission_callback' => '__return_true',
					'callback'            => array( &$this, '__callback_subscribe' ),
					'args'                => array(
						'rest_api_key' => array(
							'validate_callback' => function ( $param, $request, $key ) {
								return ! empty( $param );
							},
							'required'          => true,
						),
						'device_uuid'  => array(
							'validate_callback' => function ( $param, $request, $key ) {
								return ! empty( $param );
							},
							'required'          => true,
						),
						'device_token' => array(
							'validate_callback' => function ( $param, $request, $key ) {
								return ! empty( $param );
							},
							'required'          => true,
						),
						'subscription' => array(
							'validate_callback' => function ( $param, $request, $key ) {
								return ! empty( $param );
							},
							'required'          => true,
						),
						'device_name'  => array(
							'required' => false,
						),
						'os_version'   => array(
							'required' => false,
						),
					),
				),
				array(),
				true
			);

			// Unsubscribe
			register_rest_route(
				self::NAMESPACE,
				'/unsubscribe',
				array(
					'methods'             => array( 'POST', 'GET' ),
					'permission_callback' => '__return_true',
					'callback'            => array( &$this, '__callback_unsubscribe' ),
					'args'                => array(
						'rest_api_key' => array(
							'validate_callback' => function ( $param, $request, $key ) {
								$options = ANONY_Options_Model::get_instance( ANONYENGINE_APP_NOTIFICATIONS_OPTIONS );
								return $options->rest_api_key === $param;
							},
							'required'          => true,
						),
						'device_uuid'  => array(
							'validate_callback' => function ( $param, $request, $key ) {
								return ! empty( $param );
							},
							'required'          => true,
						),
					),
				),
				array(),
				true
			);
		}

		/*
		* Subscribe
		*/
		public function __callback_subscribe( WP_REST_Request $request ) {
			$options = ANONY_Options_Model::get_instance( ANONYENGINE_APP_NOTIFICATIONS_OPTIONS );
			// Validate API KEY
			if ( $options->rest_api_key !== $request->get_param( 'rest_api_key' ) ) {
				return new WP_REST_Response(
					array(
						'error'           => true,
						'message'         => __( 'REST API key is not valid', 'fcmpn' ),
						'subscription_id' => null,
					)
				);
			}

			// Validate token
			$device_token = sanitize_text_field( $request->get_param( 'device_token' ) ?? '' );
			if ( strlen( $device_token ) < 10 ) {
				return new WP_REST_Response(
					array(
						'error'           => true,
						'message'         => __( 'There is an error in the token you sent', 'fcmpn' ),
						'subscription_id' => null,
					)
				);
			}

			// Validate device-uuid
			$device_uuid = sanitize_text_field( $request->get_param( 'device_uuid' ) ?? '' );
			if ( strlen( $device_uuid ) < 10 ) {
				return new WP_REST_Response(
					array(
						'error'           => true,
						'message'         => __( 'There is an error in the device-uuid you sent', 'fcmpn' ),
						'subscription_id' => null,
					)
				);
			}

			// Is token exists
			if ( $device = get_page_by_title( $device_uuid, OBJECT, 'anoapp_devices' ) ) {

				if ( $device->post_excerpt !== $device_token ) {
					$update = wp_update_post(
						array(
							'ID'           => $device->ID,
							'post_excerpt' => $device_token,
							'post_type'    => 'anoapp_devices',
							'meta_input'   => array(
								'_device_token' => $device_token,
								'_device_name'  => sanitize_text_field( $request->get_param( 'device_name' ) ?? '' ),
								'_os_version'   => sanitize_text_field( $request->get_param( 'os_version' ) ?? '' ),
							),
						)
					);

					if ( is_wp_error( $update ) ) {
						return new WP_REST_Response(
							array(
								'error'           => true,
								'message'         => __( 'Device token is not changed', 'fcmpn' ),
								'subscription_id' => null,
							)
						);
					}
				}

				return new WP_REST_Response(
					array(
						'error'           => false,
						'message'         => __( 'Device token registered', 'fcmpn' ),
						'subscription_id' => absint( $device->ID ),
					)
				);
			}

			// Validate subscription
			$subscription = sanitize_text_field( $request->get_param( 'subscription' ) ?? '' );
			$term_id      = null;
			if ( $term = get_term_by( 'name', $subscription, 'anoapp_subscriptions' ) ) {
				$term_id = $term->term_id;
			} elseif ( $term = get_term_by( 'slug', $subscription, 'anoapp_subscriptions' ) ) {
					$term_id = $term->term_id;
			}

			// Add new term
			if ( ! $term_id ) {
				$term = wp_insert_term( $subscription, 'anoapp_subscriptions' );
				if ( ! is_wp_error( $term ) ) {
					$term_id = $term['term_id'];
				}
			}

			// Validate term
			if ( ! $term_id ) {
				return new WP_REST_Response(
					array(
						'error'           => true,
						'message'         => __( 'Not able to subscribe', 'fcmpn' ),
						'subscription_id' => null,
					)
				);
			}

			// Save device
			$post_id = wp_insert_post(
				array(
					'post_title'   => $device_uuid,
					'post_excerpt' => $device_token,
					'post_status'  => 'private',
					'post_type'    => 'anoapp_devices',
					'meta_input'   => array(
						'_device_token' => $device_token,
						'_device_name'  => sanitize_text_field( $request->get_param( 'device_name' ) ?? '' ),
						'_os_version'   => sanitize_text_field( $request->get_param( 'os_version' ) ?? '' ),
					),
				)
			);

			// Validate device
			if ( is_wp_error( $post_id ) ) {
				return new WP_REST_Response(
					array(
						'error'           => true,
						'message'         => __( 'Device is not saved', 'fcmpn' ),
						'subscription_id' => null,
					)
				);
			}

			// Assign terms
			wp_set_object_terms( $post_id, $term_id, 'anoapp_subscriptions' );

			// Return
			return new WP_REST_Response(
				array(
					'error'           => false,
					'message'         => __( 'Device token registered', 'fcmpn' ),
					'subscription_id' => absint( $post_id ),
				)
			);
		}

		/*
		* Unsubscribe
		*/
		public function __callback_unsubscribe( WP_REST_Request $request ) {
			$options = ANONY_Options_Model::get_instance( ANONYENGINE_APP_NOTIFICATIONS_OPTIONS );
			// Validate API KEY
			if ( $options->rest_api_key !== $request->get_param( 'rest_api_key' ) ) {
				return new WP_REST_Response(
					array(
						'error'           => true,
						'message'         => __( 'REST API key is not valid', 'fcmpn' ),
						'subscription_id' => null,
					)
				);
			}

			// Validate token
			$device_uuid = sanitize_text_field( $request->get_param( 'device_uuid' ) ?? '' );
			if ( strlen( $device_uuid ) < 10 ) {
				return new WP_REST_Response(
					array(
						'error'           => true,
						'message'         => __( 'There is an error in the token you sent', 'fcmpn' ),
						'subscription_id' => null,
					)
				);
			}

			if ( $device = get_page_by_title( $device_uuid, OBJECT, 'anoapp_devices' ) ) {

				wp_delete_post( $device->ID, true );

				// Return
				return new WP_REST_Response(
					array(
						'error'   => false,
						'message' => __( 'The device token was successfully removed', 'fcmpn' ),
					)
				);
			}

			return new WP_REST_Response(
				array(
					'error'           => true,
					'message'         => __( 'No device token found', 'fcmpn' ),
					'subscription_id' => null,
				)
			);
		}

		/*
		* Run the plugin
		*/
		public static function instance() {
			if ( ! self::$run ) {
				self::$run = new self();
			}

			return self::$run;
		}
	} endif;
