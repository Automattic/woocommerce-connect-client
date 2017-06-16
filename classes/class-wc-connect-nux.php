<?php

if ( ! class_exists( 'WC_Connect_Nux' ) ) {

	class WC_Connect_Nux {
		/**
		 * Jetpack status constants.
		 */
		const JETPACK_UNINSTALLED = 'uninstalled';
		const JETPACK_INSTALLED = 'installed';
		const JETPACK_ACTIVATED = 'activated';
		const JETPACK_DEV = 'dev';
		const JETPACK_CONNECTED = 'connected';

		/**
		 * Option name for dismissing success banner
		 * after the JP connection flow
		 */
		const SUCCESS_BANNER_IS_DISMISSED = 'after_jp_cxn_nux_success_banner_dismissed';

		function __construct() {
			$this->init_pointers();
		}

		private function get_notice_states() {
			$states = get_user_meta( get_current_user_id(), 'wc_connect_nux_notices', true );

			if ( ! is_array( $states ) ) {
				return array();
			}

			return $states;
		}

		public function is_notice_dismissed( $notice ) {
			$notices = $this->get_notice_states();

			return isset( $notices[ $notice ] ) && $notices[ $notice ];
		}

		public function dismiss_notice( $notice ) {
			$notices = $this->get_notice_states();
			$notices[ $notice ] = true;
			update_user_meta( get_current_user_id(), 'wc_connect_nux_notices', $notices );
		}

		private function init_pointers() {
			add_filter( 'wc_services_pointer_woocommerce_page_wc-settings', array( $this, 'register_add_service_to_zone_pointer' ) );
		}

		public function show_pointers( $hook ) {
			/* Get admin pointers for the current admin page.
			 *
			 * @since 0.9.6
			 *
			 * @param array $pointers Array of pointers.
			 */
			$pointers = apply_filters( 'wc_services_pointer_' . $hook, array() );

			if ( ! $pointers || ! is_array( $pointers ) ) {
				return;
			}

			$dismissed_pointers = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
			$valid_pointers = array();

			if( isset( $dismissed_pointers ) ) {
				foreach ( $pointers as $pointer ) {
					if ( ! in_array( $pointer['id'], $dismissed_pointers ) ) {
						$valid_pointers[] =  $pointer;
					}
				}
			} else {
				$valid_pointers = $pointers;
			}

			if ( empty( $valid_pointers ) ) {
				return;
			}

			wp_enqueue_style( 'wp-pointer' );
			wp_localize_script( 'wc_services_admin_pointers', 'wcSevicesAdminPointers', $valid_pointers );
			wp_enqueue_script( 'wc_services_admin_pointers' );
		}

		public function register_add_service_to_zone_pointer( $pointers ) {
			$pointers[] = array(
				'id' => 'wc_services_add_service_to_zone',
				'target' => 'th.wc-shipping-zone-methods',
				'options' => array(
					'content' => sprintf( '<h3>%s</h3><p>%s</p>',
						__( 'Add a WooCommerce shipping service to a Zone' ,'woocommerce-services' ),
						__( 'To ship products to customers using USPS or Canada Post, you will need to add them as a shipping method to an applicable zone. If you don\'t have any zones, add one first.', 'woocommerce-services' )
					),
					'position' => array( 'edge' => 'right', 'align' => 'left' ),
				)
			);
			return $pointers;
		}

		public function get_jetpack_install_status() {
			// check if Jetpack is activated
			if ( ! class_exists( 'Jetpack_Data' ) ) {
				// not activated, check if installed
				if ( 0 === validate_plugin( 'jetpack/jetpack.php' ) ) {
					return self::JETPACK_INSTALLED;
				}
				return self::JETPACK_UNINSTALLED;
			} else if ( defined( 'JETPACK_DEV_DEBUG' ) && true === JETPACK_DEV_DEBUG ) {
				// installed, activated, and dev mode on
				return self::JETPACK_DEV;
			}

			// installed, activated, dev mode off
			// check if connected
			$user_token = Jetpack_Data::get_access_token( JETPACK_MASTER_USER );
			if ( isset( $user_token->external_user_id ) ) { // always an int
				return self::JETPACK_CONNECTED;
			}

			return self::JETPACK_ACTIVATED;
		}

		public function should_display_nux_notice_on_screen( $screen ) {
			if ( // Display if on any of these admin pages.
				( // Products list.
					'product' === $screen->post_type
					&& 'edit' === $screen->base
				)
				|| ( // Orders list.
					'shop_order' === $screen->post_type
					&& 'edit' === $screen->base
					)
				|| ( // Edit order page.
					'shop_order' === $screen->post_type
					&& 'post' === $screen->base
					)
				|| ( // WooCommerce settings.
					'woocommerce_page_wc-settings' === $screen->base
					)
				|| ( // WooCommerce featured extension page
					'woocommerce_page_wc-addons' === $screen->base
					&& isset( $_GET['section'] ) && 'featured' === $_GET['section']
					)
				|| ( // WooCommerce shipping extension page
					'woocommerce_page_wc-addons' === $screen->base
					&& isset( $_GET['section'] ) && 'shipping_methods' === $_GET['section']
					)
				|| 'plugins' === $screen->base
			) {
				return true;
			}
			return false;
		}

		public function should_display_nux_notice_for_current_store_locale() {
			$base_location = wc_get_base_location();
			$country = isset( $base_location['country'] )
				? $base_location['country']
				: '';
			// Do not display for non-US, non-CA stores.
			if ( 'CA' === $country || 'US' === $country ) {
				return true;
			}
			return false;
		}

		public function get_jetpack_redirect_url() {
			$full_path = add_query_arg( array() );
			// Remove [...]/wp-admin so we can use admin_url().
			$new_index = strpos( $full_path, '/wp-admin' ) + strlen( '/wp-admin' );
			$path = substr( $full_path, $new_index );
			return admin_url( $path );
		}

		public function set_up_nux_notices() {
			if ( ! current_user_can( 'manage_woocommerce' )
				|| ! current_user_can( 'install_plugins' )
				|| ! current_user_can( 'activate_plugins' )
			) {
				return;
			}

			if ( ! $this->should_display_nux_notice_for_current_store_locale() ) {
				return;
			}

			$jetpack_install_status = $this->get_jetpack_install_status();

			switch ( $jetpack_install_status ) {
				case self::JETPACK_UNINSTALLED:
				case self::JETPACK_INSTALLED:
				case self::JETPACK_ACTIVATED:
					$ajax_data = array(
						'nonce'                  => wp_create_nonce( 'wcs_nux_notice' ),
						'initial_install_status' => $jetpack_install_status,
						'redirect_url'           => $this->get_jetpack_redirect_url(),
						'translations'           => array(
							'activating'   => __( 'Activating...', 'woocommerce-services' ),
							'connecting'   => __( 'Connecting...', 'woocommerce-services' ),
							'installError' => __( 'There was an error installing Jetpack. Please try installing it manually.', 'woocommerce-services' ),
							'defaultError' => __( 'Something went wrong. Please try connecting to Jetpack manually, or contact support on the WordPress.org forums.', 'woocommerce-services' ),
						),
					);
					wp_enqueue_script( 'wc_connect_banner' );
					wp_localize_script( 'wc_connect_banner', 'wcs_nux_notice', $ajax_data );
					add_action( 'wp_ajax_woocommerce_services_activate_jetpack',
						array( $this, 'ajax_activate_jetpack' )
					);
					add_action( 'wp_ajax_woocommerce_services_get_jetpack_connect_url',
						array( $this, 'ajax_get_jetpack_connect_url' )
					);
					wp_enqueue_style( 'wc_connect_banner' );
					add_action( 'admin_notices', array( $this, 'show_banner_before_connection' ), 9 );
					break;
				case self::JETPACK_CONNECTED:
					// Has the after-connection notice been dismissed already?
					if ( WC_Connect_Options::get_option( self::SUCCESS_BANNER_IS_DISMISSED ) ) {
						break;
					}
					wp_enqueue_style( 'wc_connect_banner' );
					add_action( 'admin_notices', array( $this, 'show_banner_after_connection' ) );
					break;
			}
		}

		public function show_banner_before_connection() {
			if ( ! $this->should_display_nux_notice_on_screen( get_current_screen() ) ) {
				return;
			}

			// Remove Jetpack's connect banners since we're showing our own.
			if ( class_exists( 'Jetpack_Connection_Banner' ) ) {
				$jetpack_banner = Jetpack_Connection_Banner::init();

				remove_action( 'admin_notices', array( $jetpack_banner, 'render_banner' ) );
				remove_action( 'admin_notices', array( $jetpack_banner, 'render_connect_prompt_full_screen' ) );
			}

			// Make sure to show the after-connection success message even after Jetpack disconnect.
			WC_Connect_Options::delete_option( self::SUCCESS_BANNER_IS_DISMISSED );

			$jetpack_status = $this->get_jetpack_install_status();

			$button_text = __( 'CONNECT >', 'woocommerce-services' );

			$image_url = plugins_url( 'images/nux-printer-laptop-illustration.png', dirname( __FILE__ ) );

			switch ( $jetpack_status ) {
				case self::JETPACK_UNINSTALLED:
					$button_text = __( 'Install Jetpack and CONNECT >', 'woocommerce-services' );
					break;
				case self::JETPACK_INSTALLED:
					$button_text = __( 'Activate Jetpack and CONNECT >', 'woocommerce-services' );
					break;
			}

			$default_content = array(
				'title'           => __( 'Connect your store to activate WooCommerce Shipping', 'woocommerce-services' ),
				'description'     => __( "WooCommerce Shipping is almost ready to go! Once you connect your store you'll be able to access discounted rates and printing services for USPS and Canada Post from your dashboard (fewer trips to the post office, winning).", 'woocommerce-services' ),
				'button_text'     => $button_text,
				'image_url'       => $image_url,
				'should_show_jp'  => true,
			);

			$base_location = wc_get_base_location();
			$country = isset( $base_location['country'] )
				? $base_location['country']
				: '';
			switch ( $country ) {
				case 'CA':
					$localized_content = array(
						'description'     => __( "WooCommerce Shipping is almost ready to go! Once you connect your store you'll be able to show your customers live shipping rates when they check out.", 'woocommerce-services' ),
					);
					break;
			}

			$this->show_nux_banner( wp_parse_args( $localized_content, $default_content ) );
		}

		public function show_banner_after_connection() {
			if ( ! $this->should_display_nux_notice_on_screen( get_current_screen() ) ) {
				return;
			}

			// Did the user just dismiss?
			if ( isset( $_GET['wcs-nux-notice'] ) && 'dismiss' === $_GET['wcs-nux-notice'] ) {
				WC_Connect_Options::update_option( self::SUCCESS_BANNER_IS_DISMISSED, true );
				wp_safe_redirect( remove_query_arg( 'wcs-nux-notice' ) );
			}

			$this->show_nux_banner( array(
				'title'          => __( 'Setup complete! You can now access discounted shipping rates and printing services' ),
				'description'    => __( 'When you’re ready, you can purchase discounted labels from USPS, and print USPS labels at home.', 'woocommerce-services' ),
				'button_text'    => __( 'Got it, thanks!', 'woocommerce-services' ),
				'button_link'    => add_query_arg( array(
					'wcs-nux-notice' => 'dismiss',
				) ),
				'image_url'      => plugins_url(
					'images/nux-printer-laptop-illustration.png', dirname( __FILE__ )
				),
				'should_show_jp' => false,
			) );
		}

		public function show_nux_banner( $content ) {
			?>
			<div class="notice wcs-nux__notice">
				<div class="wcs-nux__notice-logo">
					<img src="<?php echo esc_url( $content['image_url'] );  ?>">
				</div>
				<div class="wcs-nux__notice-content">
					<h1><?php echo esc_html( $content['title'] ); ?></h1>
					<p class="wcs-nux__notice-content-text">
						<?php echo esc_html( $content['description'] ); ?>
					</p>
					<?php if ( $content['should_show_jp'] ) : ?>
						<p><?php printf( esc_html__( 'By clicking "%1$s", you agree to the %2$sTerms of Service%3$s and understand that some of your data will be passed to external servers. You can find more information about how your data is handled %4$shere%5$s.', 'woocommerce-services' ),
							esc_html( $content['button_text'] ),
							'<a href="https://woocommerce.com/terms-conditions/">',
							'</a>',
							'<a href="https://woocommerce.com/terms-conditions/services-privacy/"/>',
							'</a>'
						); ?></p>
					<?php endif; ?>
					<?php if ( isset( $content['button_link'] ) ) : ?>
						<a
							class="wcs-nux__notice-content-button button button-primary"
							href="<?php echo esc_url( $content['button_link'] ); ?>"
						>
							<?php echo esc_html( $content['button_text'] ); ?>
						</a>
					<?php else : ?>
						<button
							class="woocommerce-services__connect-jetpack wcs-nux__notice-content-button button button-primary"
						>
							<?php echo esc_html( $content['button_text'] ); ?>
						</button>
					<?php endif; ?>
				</div>
				<?php if ( $content['should_show_jp'] ) : ?>
					<div class="wcs-nux__notice-jetpack">
						<img src="<?php
						echo esc_url( plugins_url( 'images/jetpack-logo.png', dirname( __FILE__ ) ) );
						?>">
						<p class="wcs-nux__notice-jetpack-text"><?php echo esc_html( __( 'Powered by Jetpack', 'woocommerce-services' ) ); ?></p>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Activates Jetpack after an ajax request
		 */
		public function ajax_activate_jetpack() {
			check_ajax_referer( 'wcs_nux_notice' );

			$result = activate_plugin( 'jetpack/jetpack.php' );

			if ( is_null( $result ) ) {
				// The function activate_plugin() returns NULL on success.
				echo 'success';
			} else {
				if ( is_wp_error( $result ) ) {
					echo esc_html( $result->get_error_message() );
				} else {
					echo 'error';
				}
			}

			wp_die();
		}

		/**
		 * Get Jetpack connection URL.
		 *
		 */
		public function ajax_get_jetpack_connect_url() {
			check_ajax_referer( 'wcs_nux_notice' );

			$redirect_url = '';
			if ( isset( $_POST['redirect_url'] ) ) {
				$redirect_url = esc_url_raw( wp_unslash( $_POST['redirect_url'] ) );
			}

			$connect_url = Jetpack::init()->build_connect_url(
				true,
				$redirect_url,
				'woocommerce-services'
			);

			echo esc_url_raw( $connect_url );
			wp_die();
		}
	}
}
