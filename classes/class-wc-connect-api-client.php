<?php

// No direct access please
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Connect_API_Client' ) ) {

	abstract class WC_Connect_API_Client {
		const API_VERSION = WOOCOMMERCE_CONNECT_SERVER_API_VERSION;

		/**
		 * @var WC_Connect_Services_Validator
		 */
		protected $validator;

		/**
		 * @var WC_Connect_Loader
		 */
		protected $wc_connect_loader;

		public function __construct(
			WC_Connect_Service_Schemas_Validator $validator,
			WC_Connect_Loader $wc_connect_loader
		) {

			$this->validator         = $validator;
			$this->wc_connect_loader = $wc_connect_loader;

		}

		/**
		 * Requests the available services for this site from the WooCommerce Shipping & Tax Server
		 *
		 * @return array|WP_Error
		 */
		public function get_service_schemas() {
			$response_body = $this->request( 'POST', '/services' );

			if ( is_wp_error( $response_body ) ) {
				return $response_body;
			}

			$result = $this->validator->validate_service_schemas( $response_body );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return $response_body;
		}

		/**
		 * Validates the settings for a given service with the WooCommerce Shipping & Tax Server
		 *
		 * @param $service_slug
		 * @param $service_settings
		 *
		 * @return bool|WP_Error
		 */
		public function validate_service_settings( $service_slug, $service_settings ) {
			// Make sure the service slug only contains dashes, underscores or letters
			if ( 1 === preg_match( '/[^a-z_\-]/i', $service_slug ) ) {
				return new WP_Error( 'invalid_service_slug', __( 'Invalid WooCommerce Shipping & Tax service slug provided', 'woocommerce-services' ) );
			}

			return $this->request( 'POST', "/services/{$service_slug}/settings", array( 'service_settings' => $service_settings ) );
		}

		/**
		 * Build the server's expected contents array, for rates requests.
		 *
		 * @param $package Package provided to WC_Shipping_Method::calculate_shipping()
		 *
		 * @return array|WP_Error {
		 *      @type float $height Product height.
		 *      @type float $width Product width.
		 *      @type float $length Product length.
		 *      @type int $product_id Product ID (or Variation ID).
		 *      @type int $quantity Quantity of product in shipment.
		 *      @type float $weight Product weight.
		 * }
		 */
		public function build_shipment_contents( $package ) {
			$contents = array();

			foreach ( $package['contents'] as $package_item ) {
				$product  = $package_item['data'];
				$quantity = $package_item['quantity'];

				if ( ( $quantity > 0 ) && $product->needs_shipping() ) {

					if ( ! $product->has_weight() ) {
						return new WP_Error(
							'product_missing_weight',
							sprintf(
								__( 'Product ( ID: %d ) did not include a weight. Shipping rates cannot be calculated.', 'woocommerce-services' ),
								$product->get_id()
							),
							array( 'product_id' => $product->get_id() )
						);
					}

					if (
						! $product->get_length() ||
						! $product->get_height() ||
						! $product->get_width()
					) {
						return new WP_Error(
							'product_missing_dimension',
							sprintf(
								__( 'Product ( ID: %d ) is missing a dimension value. Shipping rates cannot be calculated.', 'woocommerce-services' ),
								$product->get_id()
							),
							array( 'product_id' => $product->get_id() )
						);
					}

					$weight = $product->get_weight();
					$height = $product->get_height();
					$length = $product->get_length();
					$width  = $product->get_width();

					$contents[] = array(
						'height'     => (float) $height,
						'product_id' => $product->get_id(),
						'length'     => (float) $length,
						'quantity'   => $package_item['quantity'],
						'weight'     => (float) $weight,
						'width'      => (float) $width,
					);
				}
			}

			return $contents;
		}

		/**
		 * Gets shipping rates (for checkout) from the WooCommerce Shipping & Tax Server
		 *
		 * @param $services All settings for all services we want rates for
		 * @param $package Package provided to WC_Shipping_Method::calculate_shipping()
		 * @param $custom_boxes array of custom boxes definitions (objects)
		 * @param $predefined_boxes array of enabled predefined box IDs (strings)
		 *
		 * @return object|WP_Error
		 */
		public function get_shipping_rates( $services, $package, $custom_boxes, $predefined_boxes ) {
			// First, build the contents array
			// each item needs to specify quantity, weight, length, width and height
			$contents = $this->build_shipment_contents( $package );

			if ( is_wp_error( $contents ) ) {
				return $contents;
			}

			if ( empty( $contents ) ) {
				return new WP_Error(
					'nothing_to_ship',
					__( 'No shipping rate could be calculated. No items in the package are shippable.', 'woocommerce-services' )
				);
			}

			// Then, make the request
			$body = array(
				'contents'         => $contents,
				'destination'      => $package['destination'],
				'services'         => $services,
				'boxes'            => $custom_boxes,
				'predefined_boxes' => $predefined_boxes,
			);

			return $this->request( 'POST', '/shipping/rates', $body );
		}

		/**
		 * Send rates request information to track subscription events
		 *
		 * @param array $services Array of service settings for shipping methods.
		 *
		 * @return object|WP_Error
		 */
		public function track_subscription_event( $services ) {
			return $this->request( 'POST', '/subscriptions/checkout', array( 'services' => $services ) );
		}

		public function send_shipping_label_request( $body ) {
			return $this->request( 'POST', '/shipping/label', $body );
		}

		public function send_address_normalization_request( $body ) {
			return $this->request( 'POST', '/shipping/address/normalize', $body );
		}

		/**
		 * Asks the WooCommerce Shipping & Tax server for an array of payment methods
		 *
		 * @return mixed|WP_Error
		 */
		public function get_payment_methods() {
			return $this->request( 'POST', '/payment/methods' );
		}

		/**
		 * Gets shipping rates (for labels) from the WooCommerce Shipping & Tax Server
		 *
		 * @param array $request - array(
		 *  'packages' => array(
		 *      array(
		 *          'id' => 'box_1',
		 *          'height' => 10,
		 *          'length' => 10,
		 *          'width' => 10,
		 *          'weight' => 10,
		 *      ),
		 *      array(
		 *          'id' => 'box_2',
		 *          'box_id' => 'medium_flat_box_top',
		 *          'weight' => 5,
		 *      ),
		 *      ...
		 *  ),
		 *  'carrier' => 'usps',
		 *  'origin' => array(
		 *      'address' => '132 Hawthorne St',
		 *      'address_2' => '',
		 *      'city' => 'San Francisco',
		 *      'state' => 'CA',
		 *      'postcode' => '94107',
		 *      'country' => 'US',
		 *  ),
		 *  'destination' => array(
		 *      'address' => '1550 Snow Creek Dr',
		 *      'address_2' => '',
		 *      'city' => 'Park City',
		 *      'state' => 'UT',
		 *      'postcode' => '84060',
		 *      'country' => 'US',
		 *  ),
		 * )
		 * @return object|WP_Error
		 */
		public function get_label_rates( $request ) {
			return $this->request( 'POST', '/shipping/label/rates', $request );
		}

		/**
		 * Gets a PDF with the set of dummy labels specified in the request
		 *
		 * @param $request
		 * @return object|WP_Error
		 */
		public function get_labels_preview_pdf( $request ) {
			return $this->request( 'POST', 'shipping/labels/preview', $request );
		}

		/**
		 * Gets a PDF with the requested shipping labels in it
		 *
		 * @param $request
		 * @return object|WP_Error
		 */
		public function get_labels_print_pdf( $request ) {
			return $this->request( 'POST', 'shipping/labels/print', $request );
		}

		/**
		 * Gets the shipping label status (refund status, tracking code, etc)
		 *
		 * @param $label_id integer
		 * @return object|WP_Error
		 */
		public function get_label_status( $label_id ) {
			return $this->request( 'GET', '/shipping/label/' . $label_id . '?get_refund=true' );
		}

		/**
		 * Gets the shipping label status (refund status, tracking code, etc)
		 *
		 * @param $order_id integer
		 * @return object|WP_Error
		 */
		public function anonymize_order( $order_id ) {
			return $this->request( 'POST', '/privacy/order/' . $order_id . '/anonymize' );
		}

		/**
		 * Request a refund for a given shipping label
		 *
		 * @param $label_id integer
		 * @return object|WP_Error
		 */
		public function send_shipping_label_refund_request( $label_id ) {
			return $this->request( 'POST', '/shipping/label/' . $label_id . '/refund' );
		}

		/**
		 * Gets the configured carrier accounts
		 *
		 * @param $request
		 * @return object|WP_Error
		 */
		public function get_carrier_accounts() {
			return $this->request( 'GET', '/shipping/carriers' );
		}
		/**
		 * Disconnects the provided carrier account
		 *
		 * @param $carrier_id
		 * @return object|WP_Error
		 */
		public function disconnect_carrier_account( $carrier_id ) {
			return $this->request( 'DELETE', '/shipping/carrier/' . $carrier_id );
		}
		/**
		 * Register a new carrier account
		 *
		 * @param $body
		 * @return object|WP_Error
		 */
		public function create_shipping_carrier_account( $body ) {
			return $this->request( 'POST', '/shipping/carrier', $body );
		}

		/**
		 * Get a list of the subscriptions for WooCommerce.com linked account.
		 *
		 * @param $body
		 * @param object|WP_Error
		 */
		public function get_wccom_subscriptions() {
			return $this->request( 'POST', '/subscriptions' );
		}

		/**
		 * Get all carriers we support for registration. This end point
		 * returns a list of "fields" that we use to register the carrier
		 * account.
		 *
		 * @return object|WP_Error
		 */
		public function get_carrier_types() {
			return $this->request( 'GET', '/shipping/carrier-types' );
		}

		/**
		 * Tests the connection to the WooCommerce Shipping & Tax Server
		 *
		 * @return true|WP_Error
		 */
		public function auth_test() {
			return $this->request( 'GET', '/connection/test' );
		}

		/** Heartbeat test.
		 *
		 * @return true|WP_Error
		 */
		public function is_alive() {
			return $this->request( 'GET', '' );
		}

		/** Heartbeat test with a transient cache.
		 *
		 * @return true|WP_Error
		 */
		public function is_alive_cached() {
			$connect_server_is_alive_transient = get_transient( 'connect_server_is_alive_transient' );
			if ( false !== $connect_server_is_alive_transient ) {
				return true;
			}

			$is_alive_request = $this->is_alive();
			$new_is_alive     = ! is_wp_error( $is_alive_request );
			if ( $new_is_alive ) {
				set_transient( 'connect_server_is_alive_transient', true, MINUTE_IN_SECONDS );
			}
			return $new_is_alive;
		}

		/**
		 * Activate a subscrption with WCCOM API.
		 *
		 * @param  string $subscription_key Product Key on WCCOM.
		 * @return WP_Error|Array  API Response.
		 */
		public function activate_subscription( $subscription_key ) {
			$activation_response = WC_Helper_API::post(
				'activate',
				array(
					'authenticated' => true,
					'body'          => wp_json_encode(
						array(
							'product_key' => $subscription_key,
						)
					),
				)
			);

			return $activation_response;
		}

		/**
		 * Sends a request to the WooCommerce Shipping & Tax Server
		 *
		 * @param $method
		 * @param $path
		 * @param $body
		 * @return mixed|WP_Error
		 */
		abstract protected function request( $method, $path, $body = array() );

		/**
		 * Proxy an HTTP request through the WCS Server
		 *
		 * @param $path Path of proxy route
		 * @param $args WP_Http request args
		 *
		 * @return array|WP_Error
		 */
		public function proxy_request( $path, $args ) {
			$proxy_url  = trailingslashit( WOOCOMMERCE_CONNECT_SERVER_URL );
			$proxy_url .= ltrim( $path, '/' );

			$authorization = $this->authorization_header();
			if ( is_wp_error( $authorization ) ) {
				return $authorization;
			}
			$args['headers']['Authorization'] = $authorization;

			$http_timeout = 60; // 1 minute

			if ( function_exists( 'wc_set_time_limit' ) ) {
				wc_set_time_limit( $http_timeout + 10 );
			}

			$args['timeout'] = $http_timeout;

			$response = wp_remote_request( $proxy_url, $args );

			return $response;
		}

		/**
		 * Adds useful WP/WC/WCC information to request bodies
		 *
		 * @param array $initial_body
		 * @return array
		 */
		protected function request_body( $initial_body = array() ) {
			$default_body = array(
				'settings' => array(),
			);
			$body         = array_merge( $default_body, $initial_body );

			// Add interesting fields to the body of each request
			$body['settings'] = wp_parse_args(
				$body['settings'],
				array(
					'store_guid'           => $this->get_guid(),
					'base_city'            => WC()->countries->get_base_city(),
					'base_country'         => WC()->countries->get_base_country(),
					'base_state'           => WC()->countries->get_base_state(),
					'base_postcode'        => WC()->countries->get_base_postcode(),
					'currency'             => get_woocommerce_currency(),
					'dimension_unit'       => strtolower( get_option( 'woocommerce_dimension_unit' ) ),
					'weight_unit'          => strtolower( get_option( 'woocommerce_weight_unit' ) ),
					'wcs_version'          => WC_Connect_Loader::get_wcs_version(),
					'jetpack_version'      => defined( 'JETPACK__VERSION' ) ? JETPACK__VERSION : 'embed',
					'is_atomic'            => WC_Connect_Jetpack::is_atomic_site(),
					'wc_version'           => WC()->version,
					'wp_version'           => get_bloginfo( 'version' ),
					'last_services_update' => WC_Connect_Options::get_option( 'services_last_update', 0 ),
					'last_heartbeat'       => WC_Connect_Options::get_option( 'last_heartbeat', 0 ),
					'last_rate_request'    => WC_Connect_Options::get_option( 'last_rate_request', 0 ),
					'active_services'      => $this->wc_connect_loader->get_active_services(),
					'disable_stats'        => WC_Connect_Jetpack::is_staging_site(),
					'taxes_enabled'        => wc_tax_enabled() && 'yes' === get_option( 'wc_connect_taxes_enabled' ),
				)
			);

			return $body;
		}

		/**
		 * Generates headers for our request to the WooCommerce Shipping & Tax Server
		 *
		 * @return array|WP_Error
		 */
		protected function request_headers() {
			$authorization = $this->authorization_header();
			if ( is_wp_error( $authorization ) ) {
				return $authorization;
			}

			$headers                    = array();
			$locale                     = strtolower( str_replace( '_', '-', get_locale() ) );
			$locale_elements            = explode( '-', $locale );
			$lang                       = $locale_elements[0];
			$headers['Accept-Language'] = $locale . ',' . $lang;
			$headers['Content-Type']    = 'application/json; charset=utf-8';
			$headers['Accept']          = 'application/vnd.woocommerce-connect.v' . static::API_VERSION;
			$headers['Authorization']   = $authorization;

			$wc_helper_auth_info = WC_Connect_Functions::get_wc_helper_auth_info();
			if ( ! is_wp_error( $wc_helper_auth_info ) ) {
				$headers['X-Woo-Signature']    = $this->request_signature_wccom( $wc_helper_auth_info['access_token_secret'], 'subscriptions', 'GET', array() );
				$headers['X-Woo-Access-Token'] = $wc_helper_auth_info['access_token'];
				$headers['X-Woo-Site-Id']      = $wc_helper_auth_info['site_id'];
			}
			return $headers;
		}

		protected function authorization_header() {
			$token = WC_Connect_Jetpack::get_access_token();
			$token = apply_filters( 'wc_connect_jetpack_access_token', $token );
			if ( ! $token || empty( $token->secret ) ) {
				return new WP_Error(
					'missing_token',
					__( 'Unable to send request to WooCommerce Shipping & Tax server. Jetpack Token is missing', 'woocommerce-services' )
				);
			}

			if ( false === strpos( $token->secret, '.' ) ) {
				return new WP_Error(
					'invalid_token',
					__( 'Unable to send request to WooCommerce Shipping & Tax server. Jetpack Token is malformed.', 'woocommerce-services' )
				);
			}

			list( $token_key, $token_secret ) = explode( '.', $token->secret );
			$token_key                        = sprintf( '%s:%d:%d', $token_key, defined( 'JETPACK__API_VERSION' ) ? JETPACK__API_VERSION : 'embed', $token->external_user_id );
			$time_diff                        = (int) Jetpack_Options::get_option( 'time_diff' );
			$timestamp                        = time() + $time_diff;
			$nonce                            = wp_generate_password( 10, false );

			$signature = $this->request_signature( $token_key, $token_secret, $timestamp, $nonce, $time_diff );
			if ( is_wp_error( $signature ) ) {
				return $signature;
			}

			$auth = array(
				'token'     => $token_key,
				'timestamp' => $timestamp,
				'nonce'     => $nonce,
				'signature' => $signature,
			);

			$header_pieces = array();
			foreach ( $auth as $key => $value ) {
				$header_pieces[] = sprintf( '%s="%s"', $key, $value );
			}

			$authorization = 'X_JP_Auth ' . join( ' ', $header_pieces );
			return $authorization;
		}

		/**
		 * Generate a signature for WCCOM API request validation.
		 *
		 * @param string $token_secret
		 * @param string $endpoint
		 * @param string $method
		 * @param array  $body
		 * @return string
		 */
		protected function request_signature_wccom( $token_secret, $endpoint, $method, $body = array() ) {
			$request_url = WC_Helper_API::url( $endpoint );

			$data = array(
				'host'        => parse_url( $request_url, PHP_URL_HOST ), // host URL.
				'request_uri' => parse_url( $request_url, PHP_URL_PATH ), // endpoint URL.
				'method'      => $method,
			);

			if ( ! empty( $body ) ) {
				$data['body'] = $body;
			}

			return hash_hmac( 'sha256', wp_json_encode( $data ), $token_secret );
		}

		protected function request_signature( $token_key, $token_secret, $timestamp, $nonce, $time_diff ) {
			$local_time = $timestamp - $time_diff;
			if ( $local_time < time() - 600 || $local_time > time() + 300 ) {
				return new WP_Error(
					'invalid_signature',
					__( 'Unable to send request to WooCommerce Shipping & Tax server. The timestamp generated for the signature is too old.', 'woocommerce-services' )
				);
			}

			$normalized_request_string = join(
				"\n",
				array(
					$token_key,
					$timestamp,
					$nonce,
				)
			) . "\n";

			return base64_encode( hash_hmac( 'sha1', $normalized_request_string, $token_secret, true ) );
		}

		private function get_guid() {
			$guid = WC_Connect_Options::get_option( 'store_guid', false );
			if ( false === $guid ) {
				$guid = $this->generate_guid();
				WC_Connect_Options::update_option( 'store_guid', $guid );
			}

			return $guid;
		}

		/**
		 * Generates a GUID.
		 * This code is based of a snippet found in https://github.com/alixaxel/phunction,
		 * which was referenced in http://php.net/manual/en/function.com-create-guid.php
		 *
		 * @return string
		 */
		private function generate_guid() {
			return strtolower(
				sprintf(
					'%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
					mt_rand( 0, 65535 ),
					mt_rand( 0, 65535 ),
					mt_rand( 0, 65535 ),
					mt_rand( 16384, 20479 ),
					mt_rand( 32768, 49151 ),
					mt_rand( 0, 65535 ),
					mt_rand( 0, 65535 ),
					mt_rand( 0, 65535 )
				)
			);
		}
	}

}
