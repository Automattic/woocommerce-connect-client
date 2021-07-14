<?php

use Automattic\Jetpack\Connection\Manager;

if ( ! class_exists( 'WC_Connect_Jetpack' ) ) {
	class WC_Connect_Jetpack {
		const JETPACK_PLUGIN_SLUG = 'woocommerce-services';

		public static function get_connection_manager() {
			return new Manager( self::JETPACK_PLUGIN_SLUG );
		}

		/**
		 * @param boolean $user_id false: Returns the Blog Token. true: Return the master user's User Token.
		 *
		 * @return stdClass|WP_Error
		 */
		public static function get_access_token( $user_id = false ) {
			// Standalone Jetpack 9.5+ installation
			if ( class_exists( '\Automattic\Jetpack\Connection\Tokens' ) ) {
				return self::get_connection_manager()->get_tokens()->get_access_token( $user_id );
			}

			return self::get_connection_manager()->get_access_token( $user_id );
		}

		/**
		 * Helper method to get if Jetpack is in development mode
		 *
		 * @return bool
		 */
		public static function is_development_mode() {
			if ( method_exists( '\\Automattic\\Jetpack\\Status', 'is_offline_mode' ) ) {
				$status = new \Automattic\Jetpack\Status();

				return $status->is_offline_mode();
			}

			return false;
		}

		/**
		 * Helper method to get if Jetpack is connected (aka active)
		 *
		 * @return bool
		 */
		public static function is_active() {
			return ! empty ( self::get_access_token( true ) );
		}

		/**
		 * Helper method to get if the current Jetpack website is marked as staging
		 *
		 * @return bool
		 */
		public static function is_staging_site() {
			$jetpack_status = new \Automattic\Jetpack\Status();

			return $jetpack_status->is_staging_site();
		}

		/**
		 * Helper method to get whether the current site is an Atomic site
		 *
		 * @return bool
		 */
		public static function is_atomic_site() {
			if ( function_exists( 'jetpack_is_atomic_site' ) ) {
				return jetpack_is_atomic_site();
			}

			if ( function_exists( 'jetpack_is_automated_transfer_site' ) ) {
				return jetpack_is_automated_transfer_site();
			}

			return false;
		}

		public static function get_connected_user_data( $user_id ) {
			return self::get_connection_manager()->get_connected_user_data( $user_id );
		}

		/**
		 * Helper method to get the Jetpack master user, IF we are connected
		 *
		 * @return WP_User | false
		 */
		public static function get_master_user() {
			if ( self::is_active() && method_exists( 'Jetpack_Options', 'get_option' ) ) {
				$master_user_id = Jetpack_Options::get_option( 'master_user' );

				return get_userdata( $master_user_id );
			}

			return false;

		}

		/**
		 * Records a Tracks event
		 *
		 * @param $user
		 * @param $event_type
		 * @param
		 */
		public static function tracks_record_event( $user, $event_type, $data ) {
			$tracking = new Automattic\Jetpack\Tracking();

			return $tracking->tracks_record_event( $user, $event_type, $data );
		}

		/**
		 * Determines if the current user is connected to Jetpack
		 *
		 * @return bool Whether or nor the current user is connected to Jetpack
		 */
		public static function is_current_user_connected() {
			return self::get_connection_manager()->is_user_connected();
		}

		/**
		 * Determines if Jetpack is connected for the plugin
		 *
		 * @return bool Whether or nor Jetpack is connected
		 */
		public static function is_connected() {
			$manager = self::get_connection_manager();

			return $manager->is_plugin_enabled() && self::is_active() && ! $manager->is_missing_connection_owner();
		}

		/**
		 * Connects the site to Jetpack.
		 * This code performs a redirection, so anything executed after it will be ignored.
		 *
		 * @param $redirect_url
		 */
		public static function connect_site( $redirect_url ) {
			// Mark the plugin as enabled in case it had been soft-disconnected.
			$jetpack_connection_manager = self::get_connection_manager();
			$jetpack_connection_manager->enable_plugin();

			// Register the site to wp.com.
			// standalone JP 9.2+ uses `is_connected`
			$is_registered = false;
			if ( method_exists( $jetpack_connection_manager, 'is_connected' ) ) {
				$is_registered = $jetpack_connection_manager->is_connected();
			} else {
				$is_registered = $jetpack_connection_manager->is_registered();
			}

			if ( ! $is_registered ) {
				$result = $jetpack_connection_manager->register();
				if ( is_wp_error( $result ) ) {
					wp_die( $result->get_error_message(), 'wc_services_jetpack_register_site_failed', 500 );
				}
			}

			// Redirect the user to the Jetpack user connection flow.
			add_filter( 'jetpack_use_iframe_authorization_flow', '__return_false' );

			wp_redirect(
				add_query_arg(
					[ 'from' => WC_Connect_Jetpack::JETPACK_PLUGIN_SLUG ],
					$jetpack_connection_manager->get_authorization_url( null, $redirect_url )
				)
			);
			exit;
		}
	}
}
