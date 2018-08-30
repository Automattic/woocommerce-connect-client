<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Connect_Stripe' ) ) {

	class WC_Connect_Stripe {

		/**
		 * @var WC_Connect_API_Client
		 */
		private $api;

		/**
		 * @var WC_Connect_Options
		 */
		private $options;

		/**
		 * @var WC_Connect_Logger
		 */
		private $logger;

		/**
		 * @var WC_Connect_Nux
		 */
		private $nux;

		const STATE_VAR_NAME = 'stripe_state';
		const SETTINGS_OPTION = 'woocommerce_stripe_settings';

		public function __construct( WC_Connect_API_Client $client, WC_Connect_Options $options, WC_Connect_Logger $logger, WC_Connect_Nux $nux ) {
			$this->api = $client;
			$this->options = $options;
			$this->logger = $logger;
			$this->nux = $nux;
		}

		public function is_stripe_plugin_enabled() {
			return class_exists( 'WC_Stripe' );
		}

		public function get_oauth_url( $return_url ) {
			$result = $this->api->get_stripe_oauth_init( $return_url );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$this->options->update_option( self::STATE_VAR_NAME, $result->state );

			return $result->oauthUrl;
		}

		public function create_account( $email, $country ) {
			$response = $this->api->create_stripe_account( $email, $country );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			return $this->save_stripe_keys( $response );
		}

		public function get_account_details() {
			$response = $this->api->get_stripe_account_details();
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return array(
				'account_id'      => $response->accountId,
				'display_name'    => $response->displayName,
				'email'           => $response->email,
				'business_logo'   => $response->businessLogo,
				'legal_entity'    => array(
					'first_name'      => $response->legalEntity->firstName,
					'last_name'       => $response->legalEntity->lastName
				),
				'payouts_enabled' => $response->payoutsEnabled,
			);
		}

		public function deauthorize_account() {
			$response = $this->api->deauthorize_stripe_account();
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$this->clear_stripe_keys();
			return $response;
		}

		public function connect_oauth( $state, $code ) {
			if ( $state !== $this->options->get_option( self::STATE_VAR_NAME, false ) ) {
				return new WP_Error( 'Invalid stripe state' );
			}

			$response = $this->api->get_stripe_oauth_keys( $code );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->save_stripe_keys( $response );
		}

		private function save_stripe_keys( $result ) {
			if ( ! isset( $result->publishableKey, $result->secretKey ) ) {
				return new WP_Error( 'Invalid credentials received from server' );
			}

			$is_test = false !== strpos( $result->publishableKey, '_test_' );
			$prefix = $is_test ? 'test_' : '';

			$default_options = $this->get_default_stripe_config();

			$options = array_merge( $default_options, get_option( self::SETTINGS_OPTION, array() ) );
			$options['enabled']                     = 'yes';
			$options['testmode']                    = $is_test ? 'yes' : 'no';
			$options[ $prefix . 'publishable_key' ] = $result->publishableKey;
			$options[ $prefix . 'secret_key' ]      = $result->secretKey;

			// While we are at it, let's also clear the account_id and
			// test_account_id if present

			// Those used to be stored by save_stripe_keys but should not have
			// been since they were not used by anyone

			unset( $options[ 'account_id' ] );
			unset( $options[ 'test_account_id' ] );

			update_option( self::SETTINGS_OPTION, $options );
			return $result;
		}

		/**
		 * Clears keys for test or production (whichever is presently enabled).
		 * Especially useful after Stripe Connect account deauthorization.
		 */
		private function clear_stripe_keys() {
			$default_options = $this->get_default_stripe_config();
			$options = array_merge( $default_options, get_option( self::SETTINGS_OPTION, array() ) );

			if ( 'yes' === $options['testmode'] ) {
				$options[ 'test_publishable_key' ] = '';
				$options[ 'test_secret_key' ] = '';
			} else {
				$options[ 'publishable_key' ] = '';
				$options[ 'secret_key' ] = '';
			}

			// While we are at it, let's also clear the account_id and
			// test_account_id if present

			// Those used to be stored by save_stripe_keys but should not have
			// been since they were not used by anyone

			unset( $options[ 'account_id' ] );
			unset( $options[ 'test_account_id' ] );

			update_option( self::SETTINGS_OPTION, $options );
		}

		private function get_default_stripe_config() {
			if ( ! class_exists( 'WC_Gateway_Stripe' ) ) {
				return array();
			}

			$result = array();
			$gateway = new WC_Gateway_Stripe();
			foreach ( $gateway->form_fields as $key => $value ) {
				if ( isset( $value['default'] ) ) {
					$result[ $key ] = $value['default'];
				}
			}

			return $result;
		}

		public function maybe_show_banner() {
			if ( 'yes' !== WC_Connect_Options::get_option( 'banner_stripe', null ) ) {
				return;
			}

			if ( isset( $_GET[ 'wcs_stripe_code' ] ) ) {
				$this->connect_oauth( $_GET[ 'wcs_stripe_state' ], $_GET[ 'wcs_stripe_code' ] );
				WC_Connect_Options::delete_option( 'banner_stripe' );
				wp_safe_redirect( remove_query_arg( array( 'wcs_stripe_state', 'wcs_stripe_code' ) ) );
				exit;
			}

			wp_enqueue_style( 'wc_connect_banner' );
			add_action( 'admin_notices', array( $this, 'banner' ) );
		}

		public function banner() {
			$this->nux->show_nux_banner( array(
				'title'          => __( 'Connect your account to activate Stripe', 'woocommerce-services' ),
				'description'    => esc_html( __( 'To start accepting payments with Stripe, you need to connect your site to a Stripe account.', 'woocommerce-services' ) ),
				'button_text'    => __( 'Connect', 'woocommerce-services' ),
				'button_link'    => $this->get_oauth_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) ),
				'image_url'      => plugins_url( 'images/cashier.svg', dirname( __FILE__ ) ),
				'should_show_jp' => false,
				'dismissible_id' => 'stripe_connect',
			) );
		}

		/**
		 * Add to settings page container for dynamically rendered connected account view.
		 */
		public function show_connected_account( $settings ) {
			ob_start();
			do_action( 'enqueue_wc_connect_script', 'wc-connect-stripe-connect-account' );

			$new_settings = array(
				'connection_status' => array(
					'type'        => 'title',
					'title'       => __( 'Connected Stripe account', 'woocommerce-services' ),
					'description' => ob_get_clean(),
				),
			);
			return array_merge( $new_settings, $settings );
		}
	}
}
