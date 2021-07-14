<?php

// No direct access please
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Connect_Tracks' ) ) {

	class WC_Connect_Tracks {
		static $product_name = 'woocommerceconnect';

		/**
		 * @var WC_Connect_Logger
		 */
		protected $logger;

		public function __construct( WC_Connect_Logger $logger, $plugin_file ) {
			$this->logger      = $logger;
			$this->plugin_file = $plugin_file;
		}

		public function init() {
			add_action( 'wc_connect_shipping_zone_method_added', array( $this, 'shipping_zone_method_added' ), 10, 3 );
			add_action( 'wc_connect_shipping_zone_method_deleted', array( $this, 'shipping_zone_method_deleted' ), 10, 3 );
			add_action( 'wc_connect_shipping_zone_method_status_toggled', array( $this, 'shipping_zone_method_status_toggled' ), 10, 4 );
			add_action( 'wc_connect_saved_service_settings', array( $this, 'saved_service_settings' ), 10, 3 );
			register_deactivation_hook( $this->plugin_file, array( $this, 'opted_out' ) );
		}

		public function opted_in( $source = null ) {
			if ( is_null( $source ) ) {
				$this->record_user_event( 'opted_in' );
			} else {
				$this->record_user_event( 'opted_in', compact( 'source' ) );
			}
		}

		public function opted_out() {
			$this->record_user_event( 'opted_out' );
		}

		public function shipping_zone_method_added( $instance_id, $service_id ) {
			$this->record_user_event( 'shipping_zone_method_added' );
			$this->record_user_event( 'shipping_zone_' . $service_id . '_added' );
		}

		public function shipping_zone_method_deleted( $instance_id, $service_id ) {
			$this->record_user_event( 'shipping_zone_method_deleted' );
			$this->record_user_event( 'shipping_zone_' . $service_id . '_deleted' );
		}

		public function shipping_zone_method_status_toggled( $instance_id, $service_id, $zone_id, $enabled ) {
			if ( $enabled ) {
				$this->record_user_event( 'shipping_zone_method_enabled' );
				$this->record_user_event( 'shipping_zone_' . $service_id . '_enabled' );
			} else {
				$this->record_user_event( 'shipping_zone_method_disabled' );
				$this->record_user_event( 'shipping_zone_' . $service_id . '_disabled' );
			}
		}

		public function saved_service_settings( $service_id ) {
			$this->record_user_event( 'saved_service_settings' );
			$this->record_user_event( 'saved_' . $service_id . '_settings' );
		}

		public function record_user_event( $event_type, $data = array() ) {
			$user     = wp_get_current_user();
			$site_url = get_option( 'siteurl' );

			$wcs_version = WC_Connect_Loader::get_wcs_version();

			// Check for WooCommerce
			$wc_version = 'unavailable';
			if ( function_exists( 'WC' ) ) {
				$wc_version = WC()->version;
			}

			// Check for Jetpack
			$jp_version = 'embed';
			if ( defined( 'JETPACK__VERSION' ) ) {
				$jp_version = JETPACK__VERSION;
			}
			$is_atomic = WC_Connect_Jetpack::is_atomic_site();

			$jetpack_blog_id = -1;
			if ( class_exists( 'Jetpack_Options' ) && method_exists( 'Jetpack_Options', 'get_option' ) ) {
				$jetpack_blog_id = Jetpack_Options::get_option( 'id' );
			}

			if ( ! is_array( $data ) ) {
				$data = array();
			}

			$data['_via_ua']         = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
			$data['_via_ip']         = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
			$data['_lg']             = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
			$data['blog_url']        = $site_url;
			$data['blog_id']         = $jetpack_blog_id;
			$data['wcs_version']     = $wcs_version;
			$data['jetpack_version'] = $jp_version;
			$data['is_atomic']       = $is_atomic;
			$data['wc_version']      = $wc_version;
			$data['wp_version']      = get_bloginfo( 'version' );

			$event_type = self::$product_name . '_' . $event_type;

			$this->debug( 'Tracked the following event: ' . $event_type );
			WC_Connect_Jetpack::tracks_record_event( $user, $event_type, $data );
		}

		protected function debug( $message ) {
			if ( ! is_null( $this->logger ) ) {
				$this->logger->log( $message );
			}
		}

	}

}
