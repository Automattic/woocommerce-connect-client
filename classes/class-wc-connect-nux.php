<?php

if ( ! class_exists( 'WC_Connect_Nux' ) ) {

	class WC_Connect_Nux {
		/**
		 * Jetpack status constants.
		 */
		const JETPACK_NOT_CONNECTED = 'not-connected';
		const JETPACK_DEV = 'dev';
		const JETPACK_CONNECTED = 'connected';

		const IS_NEW_LABEL_USER = 'wcc_is_new_label_user';

		/**
		 * Option name for dismissing success banner
		 * after the JP connection flow
		 */
		const SHOULD_SHOW_AFTER_CXN_BANNER = 'should_display_nux_after_jp_cxn_banner';

		/**
		 * @var WC_Connect_Tracks
		 */
		protected $tracks;

		/**
		 * @var WC_Connect_Shipping_Label
		 */
		private $shipping_label;

		function __construct( WC_Connect_Tracks $tracks, WC_Connect_Shipping_Label $shipping_label ) {
			$this->tracks         = $tracks;
			$this->shipping_label = $shipping_label;

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
			$notices            = $this->get_notice_states();
			$notices[ $notice ] = true;
			update_user_meta( get_current_user_id(), 'wc_connect_nux_notices', $notices );
		}

		public function ajax_dismiss_notice() {
			if ( empty( $_POST['dismissible_id'] ) ) {
				return;
			}

			check_ajax_referer( 'wc_connect_dismiss_notice', 'nonce' );
			$this->dismiss_notice( sanitize_key( $_POST['dismissible_id'] ) );
			wp_die();
		}

		private function init_pointers() {
			add_filter( 'wc_services_pointer_post.php', array( $this, 'register_order_page_labels_pointer' ) );
			add_filter( 'wc_services_pointer_post.php', array( $this, 'register_new_carrier_dhl_pointer' ) );
		}

		public function show_pointers( $hook ) {
			/*
			 Get admin pointers for the current admin page.
			 *
			 * @since 0.9.6
			 *
			 * @param array $pointers Array of pointers.
			 */
			$pointers = apply_filters( 'wc_services_pointer_' . $hook, array() );

			if ( ! $pointers || ! is_array( $pointers ) ) {
				return;
			}

			$dismissed_pointers = $this->get_dismissed_pointers();
			$valid_pointers     = array();

			foreach ( $pointers as $pointer ) {
				if ( ! in_array( $pointer['id'], $dismissed_pointers, true ) ) {
					$valid_pointers[] = $pointer;
				}
			}

			if ( empty( $valid_pointers ) ) {
				return;
			}

			wp_enqueue_style( 'wp-pointer' );
			wp_localize_script( 'wc_services_admin_pointers', 'wcServicesAdminPointers', $valid_pointers );
			wp_enqueue_script( 'wc_services_admin_pointers' );
		}

		public function get_dismissed_pointers() {
			$data = get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );
			if ( is_string( $data ) && 0 < strlen( $data ) ) {
				return explode( ',', $data );
			}

			return array();
		}

		/**
		 * Dismiss a WP pointer for the current user.
		 *
		 * @param string $pointer_to_dismiss Pointer ID to dismiss for the current user
		 */
		public function dismiss_pointer( $pointer_to_dismiss ) {
			$dismissed_pointers = $this->get_dismissed_pointers();

			if ( in_array( $pointer_to_dismiss, $dismissed_pointers, true ) ) {
				return;
			}

			$dismissed_pointers[] = $pointer_to_dismiss;
			$dismissed_data       = implode( ',', $dismissed_pointers );
			update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', $dismissed_data );
		}

		public function is_new_labels_user() {
			$is_new_user = get_transient( self::IS_NEW_LABEL_USER );
			if ( false === $is_new_user ) {
				global $wpdb;
				$query       = "SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key = 'wc_connect_labels' LIMIT 1";
				$results     = $wpdb->get_results( $query );
				$is_new_user = 0 === count( $results ) ? 'yes' : 'no';
				set_transient( self::IS_NEW_LABEL_USER, $is_new_user );
			}

			return 'yes' === $is_new_user;
		}

		public function register_order_page_labels_pointer( $pointers ) {
			// If the user is not new to labels, we should just dismiss this pointer
			if ( ! $this->is_new_labels_user() ) {
				$this->dismiss_pointer( 'wc_services_labels_metabox' );

				return $pointers;
			}

			if ( ! $this->shipping_label->should_show_meta_box() ) {
				return $pointers;
			}

			$supported_carriers = array( 'USPS' );
			if ( $this->shipping_label->is_dhl_express_available() ) {
				$supported_carriers[] = 'DHL';
			}

			$pointers[] = array(
				'id'      => 'wc_services_labels_metabox',
				'target'  => '#woocommerce-order-label .button',
				'options' => array(
					'content'  => sprintf(
						'<h3>%s</h3><p>%s</p>',
						__( 'Discounted Shipping Labels', 'woocommerce-services' ),
						sprintf( __( "When you're ready, purchase and print discounted labels from %s right here.", 'woocommerce-services' ), implode( ' or ', $supported_carriers ) )
					),
					'position' => array(
						'edge'  => 'top',
						'align' => 'left',
					),
				),
				'dim'     => true,
			);

			return $pointers;
		}

		public function register_new_carrier_dhl_pointer( $pointers ) {
			// new user? no need to show this alert, `wc_services_labels_metabox` will take care of communicating about DHL
			if ( $this->is_new_labels_user() ) {
				$this->dismiss_pointer( 'wc_services_new_carrier_dhl_express' );

				return $pointers;
			}

			// existing user? figure out if the order supports DHL, then let them know DHL is a new carrier!
			if ( ! $this->shipping_label->is_order_dhl_express_eligible() ) {
				return $pointers;
			}

			$pointers[] = array(
				'id'      => 'wc_services_new_carrier_dhl_express',
				'target'  => '#woocommerce-order-label .button',
				'options' => array(
					'content'  => sprintf(
						'<h3>%s</h3><p>%s</p>',
						__( 'Discounted DHL Shipping Labels', 'woocommerce-services' ),
						__( 'WooCommerce Shipping now supports DHL labels for international shipments. Purchase and print discounted labels from DHL and USPS right here.', 'woocommerce-services' )
					),
					'position' => array(
						'edge'  => 'top',
						'align' => 'left',
					),
				),
				'dim'     => true,
			);

			return $pointers;
		}

		/**
		 * Check that the current user is the owner of the Jetpack connection
		 * - Only that person can accept the TOS
		 *
		 * @return bool
		 * @uses self::get_jetpack_install_status()
		 */
		public function can_accept_tos() {
			$jetpack_status = $this->get_jetpack_install_status();

			// Developer case
			if ( self::JETPACK_DEV === $jetpack_status ) {
				return true;
			}

			$can_accept = WC_Connect_Jetpack::is_current_user_connected();

			return $can_accept;
		}

		public static function get_banner_type_to_display( $status = array() ) {
			if ( ! isset( $status['jetpack_connection_status'] ) ) {
				return false;
			}

			/*
			 The NUX Flow:
			- Case 1: Jetpack not connected (with TOS or no TOS accepted):
				1. show_banner_before_connection()
				2. connect to JP
				3. show_banner_after_connection(), which sets the TOS acceptance in options
			- Case 2: Jetpack connected, no TOS
				1. show_tos_only_banner(), which accepts TOS on button click
			- Case 3: Jetpack connected, and TOS accepted
				This is an existing user. Do nothing.
			*/
			switch ( $status['jetpack_connection_status'] ) {
				case self::JETPACK_NOT_CONNECTED:
					return 'before_jetpack_connection';
				case self::JETPACK_CONNECTED:
				case self::JETPACK_DEV:
					// Has the user just gone through our NUX connection flow?
					if ( isset( $status['should_display_after_cxn_banner'] ) && $status['should_display_after_cxn_banner'] ) {
						return 'after_jetpack_connection';
					}

					// Has the user already accepted our TOS? Then do nothing.
					// Note: TOS is accepted during the after_connection banner
					if (
						isset( $status['tos_accepted'] )
						&& ! $status['tos_accepted']
						&& isset( $status['can_accept_tos'] )
						&& $status['can_accept_tos']
					) {
						return 'tos_only_banner';
					}

					return false;
				default:
					return false;
			}
		}

		public function get_jetpack_install_status() {
			if ( defined( 'JETPACK_DEV_DEBUG' ) && true === JETPACK_DEV_DEBUG ) {
				// activated, and dev mode on
				return self::JETPACK_DEV;
			}

			// dev mode off, check if connected
			if ( ! WC_Connect_Jetpack::is_connected() ) {
				return self::JETPACK_NOT_CONNECTED;
			}

			return self::JETPACK_CONNECTED;
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

		/**
		 * https://developers.taxjar.com/api/reference/#countries
		 */
		public function is_taxjar_supported_country( $country_code ) {
			$taxjar_supported_countries = array_merge(
				array(
					'US',
					'CA',
					'AU',
				),
				WC()->countries->get_european_union_countries()
			);

			return in_array( $country_code, $taxjar_supported_countries );
		}

		public function should_display_nux_notice_for_current_store_locale() {
			$store_country = WC()->countries->get_base_country();

			$supports_taxes    = $this->is_taxjar_supported_country( $store_country );
			$supports_shipping = in_array( $store_country, array( 'US', 'CA' ) );

			return $supports_shipping || $supports_taxes;
		}

		public function get_feature_list_for_country( $country ) {
			$feature_list    = false;
			$supports_taxes  = $this->is_taxjar_supported_country( $country );
			$supports_labels = ( 'US' === $country );

			$is_ppec_active    = is_plugin_active( 'woocommerce-gateway-paypal-express-checkout/woocommerce-gateway-paypal-express-checkout.php' );
			$ppec_settings     = get_option( 'woocommerce_ppec_paypal_settings', array() );
			$supports_payments = $is_ppec_active && ( ! isset( $ppec_settings['enabled'] ) || 'yes' === $ppec_settings['enabled'] );

			if ( $supports_payments && $supports_taxes && $supports_labels ) {
				$feature_list = __( 'automated tax calculation, shipping label printing, and smoother payment setup', 'woocommerce-services' );
			} elseif ( $supports_payments && $supports_taxes ) {
				$feature_list = __( 'automated tax calculation and smoother payment setup', 'woocommerce-services' );
			} elseif ( $supports_taxes && $supports_labels ) {
				$feature_list = __( 'automated tax calculation and shipping label printing', 'woocommerce-services' );
			} elseif ( $supports_payments && $supports_labels ) {
				$feature_list = __( 'shipping label printing and smoother payment setup', 'woocommerce-services' );
			} elseif ( $supports_payments ) {
				$feature_list = __( 'smoother payment setup', 'woocommerce-services' );
			} elseif ( $supports_taxes ) {
				$feature_list = __( 'automated tax calculation', 'woocommerce-services' );
			} elseif ( $supports_labels ) {
				$feature_list = __( 'shipping label printing', 'woocommerce-services' );
			}

			return $feature_list;
		}

		public function get_jetpack_redirect_url() {
			$full_path = add_query_arg( array() );
			// Remove [...]/wp-admin so we can use admin_url().
			$new_index = strpos( $full_path, '/wp-admin' ) + strlen( '/wp-admin' );
			$path      = substr( $full_path, $new_index );

			return admin_url( $path );
		}

		public function set_up_nux_notices() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			// Check for plugin install and activate permissions to handle Jetpack on multisites:
			// Admins might not be able to install or activate plugins, but Jetpack might already have been installed by a superadmin.
			// If this is the case, the admin can connect the site on their own, and should be able to use WCS as ususal
			$jetpack_install_status = $this->get_jetpack_install_status();

			$banner_to_display = self::get_banner_type_to_display(
				array(
					'jetpack_connection_status'       => $jetpack_install_status,
					'tos_accepted'                    => WC_Connect_Options::get_option( 'tos_accepted' ),
					'can_accept_tos'                  => $this->can_accept_tos(),
					'should_display_after_cxn_banner' => WC_Connect_Options::get_option( self::SHOULD_SHOW_AFTER_CXN_BANNER ),
				)
			);

			switch ( $banner_to_display ) {
				case 'before_jetpack_connection':
					wp_enqueue_script( 'wc_connect_banner' );
					add_action(
						'admin_post_register_woocommerce_services_jetpack',
						array( $this, 'register_woocommerce_services_jetpack' )
					);
					wp_enqueue_style( 'wc_connect_banner' );
					add_action( 'admin_notices', array( $this, 'show_banner_before_connection' ), 9 );
					break;
				case 'tos_only_banner':
					wp_enqueue_style( 'wc_connect_banner' );
					add_action( 'admin_notices', array( $this, 'show_tos_banner' ) );
					break;
				case 'after_jetpack_connection':
					wp_enqueue_style( 'wc_connect_banner' );
					add_action( 'admin_notices', array( $this, 'show_banner_after_connection' ) );
					break;
			}

			add_action( 'wp_ajax_wc_connect_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		}

		public function show_banner_before_connection() {
			if ( ! $this->should_display_nux_notice_for_current_store_locale() ) {
				return;
			}

			if ( ! $this->should_display_nux_notice_on_screen( get_current_screen() ) ) {
				return;
			}

			// Remove Jetpack's connect banners since we're showing our own.
			if ( class_exists( 'Jetpack_Connection_Banner' ) ) {
				$jetpack_banner = Jetpack_Connection_Banner::init();

				remove_action( 'admin_notices', array( $jetpack_banner, 'render_banner' ) );
				remove_action( 'admin_notices', array( $jetpack_banner, 'render_connect_prompt_full_screen' ) );
			}

			// Make sure that we wait until the button is clicked before displaying
			// the after_connection banner
			// so that we don't accept the TOS pre-maturely
			WC_Connect_Options::delete_option( self::SHOULD_SHOW_AFTER_CXN_BANNER );

			$country = WC()->countries->get_base_country();
			/* translators: %s: list of features, potentially comma separated */
			$description_base = __( "WooCommerce Shipping & Tax is almost ready to go! Once you connect your site you'll have access to %s.", 'woocommerce-services' );
			$feature_list     = $this->get_feature_list_for_country( $country );
			$banner_content   = array(
				'title'             => __( 'Connect your site to activate WooCommerce Shipping & Tax', 'woocommerce-services' ),
				'description'       => sprintf( $description_base, $feature_list ),
				'button_text'       => __( 'Connect', 'woocommerce-services' ),
				'image_url'         => plugins_url( 'images/wcs-notice.png', dirname( __FILE__ ) ),
				'should_show_terms' => true,
			);

			$this->show_nux_banner( $banner_content );
		}

		public function show_banner_after_connection() {
			if ( ! $this->should_display_nux_notice_for_current_store_locale() ) {
				return;
			}

			if ( ! $this->should_display_nux_notice_on_screen( get_current_screen() ) ) {
				return;
			}

			// Did the user just dismiss?
			if ( isset( $_GET['wcs-nux-notice'] ) && 'dismiss' === $_GET['wcs-nux-notice'] ) {
				// No longer need to keep track of whether the before connection banner was displayed.
				WC_Connect_Options::delete_option( self::SHOULD_SHOW_AFTER_CXN_BANNER );
				wp_safe_redirect( remove_query_arg( 'wcs-nux-notice' ) );
				exit;
			}

			// By going through the connection process, the user has accepted our TOS
			WC_Connect_Options::update_option( 'tos_accepted', true );

			$this->tracks->opted_in( 'connection_banner' );

			$country = WC()->countries->get_base_country();
			/* translators: %s: list of features, potentially comma separated */
			$description_base = __( 'You can now enjoy %s.', 'woocommerce-services' );
			$feature_list     = $this->get_feature_list_for_country( $country );

			$this->show_nux_banner(
				array(
					'title'             => __( 'Setup complete.', 'woocommerce-services' ),
					'description'       => esc_html( sprintf( $description_base, $feature_list ) ),
					'button_text'       => __( 'Got it, thanks!', 'woocommerce-services' ),
					'button_link'       => add_query_arg(
						array(
							'wcs-nux-notice' => 'dismiss',
						)
					),
					'image_url'         => plugins_url(
						'images/wcs-notice.png',
						dirname( __FILE__ )
					),
					'should_show_terms' => false,
				)
			);
		}

		public function show_tos_banner() {
			if ( ! $this->should_display_nux_notice_for_current_store_locale() ) {
				return;
			}

			if ( ! $this->should_display_nux_notice_on_screen( get_current_screen() ) ) {
				return;
			}

			if ( isset( $_GET['wcs-nux-tos'] ) && 'accept' === $_GET['wcs-nux-tos'] ) {
				WC_Connect_Options::update_option( 'tos_accepted', true );

				$this->tracks->opted_in( 'tos_banner' );

				wp_safe_redirect( remove_query_arg( 'wcs-nux-tos' ) );
				exit;
			}

			$country = WC()->countries->get_base_country();
			/* translators: %s: list of features, potentially comma separated */
			$description_base = __( "WooCommerce Shipping & Tax is almost ready to go! Once you connect your store you'll have access to %s.", 'woocommerce-services' );
			$feature_list     = $this->get_feature_list_for_country( $country );

			$this->show_nux_banner(
				array(
					'title'             => __( 'Connect your store to activate WooCommerce Shipping & Tax', 'woocommerce-services' ),
					'description'       => esc_html( sprintf( $description_base, $feature_list ) ),
					'button_text'       => __( 'Connect', 'woocommerce-services' ),
					'button_link'       => add_query_arg(
						array(
							'wcs-nux-tos' => 'accept',
						)
					),
					'image_url'         => plugins_url(
						'images/wcs-notice.png',
						dirname( __FILE__ )
					),
					'should_show_terms' => true,
				)
			);
		}

		public function show_nux_banner( $content ) {
			if ( isset( $content['dismissible_id'] ) && $this->is_notice_dismissed( sanitize_key( $content['dismissible_id'] ) ) ) {
				return;
			}

			?>
			<div class="notice wcs-nux__notice <?php echo isset( $content['dismissible_id'] ) ? 'is-dismissible' : ''; ?>">
				<div class="wcs-nux__notice-logo <?php echo isset( $content['compact_logo'] ) && $content['compact_logo'] ? 'is-compact' : ''; ?>">
					<img class="wcs-nux__notice-logo-graphic" src="<?php echo esc_url( $content['image_url'] ); ?>">
				</div>
				<div class="wcs-nux__notice-content">
					<h1 class="wcs-nux__notice-content-title">
						<?php echo esc_html( $content['title'] ); ?>
					</h1>
					<p class="wcs-nux__notice-content-text">
						<?php echo $content['description']; ?>
					</p>
					<?php if ( isset( $content['should_show_terms'] ) && $content['should_show_terms'] ) : ?>
						<p class="wcs-nux__notice-content-tos">
							<?php
							/* translators: %1$s example values include "Install Jetpack and CONNECT >", "Activate Jetpack and CONNECT >", "CONNECT >" */
							printf(
								wp_kses(
									__( 'By clicking "%1$s", you agree to the <a href="%2$s">Terms of Service</a> and to <a href="%3$s">share certain data and settings</a> with WordPress.com and/or third parties.', 'woocommerce-services' ),
									array(
										'a' => array(
											'href' => array(),
										),
									)
								),
								esc_html( $content['button_text'] ),
								'https://wordpress.com/tos/',
								'https://jetpack.com/support/what-data-does-jetpack-sync/'
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( isset( $content['button_link'] ) ) : ?>
						<a
							class="wcs-nux__notice-content-button button button-primary"
							href="<?php echo esc_url( $content['button_link'] ); ?>"
						>
							<?php echo esc_html( $content['button_text'] ); ?>
						</a>
					<?php else : ?>
						<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
							<input type="hidden" name="action" value="register_woocommerce_services_jetpack"/>
							<input type="hidden" name="redirect_url"
								   value="<?php echo esc_url( $this->get_jetpack_redirect_url() ); ?>"/>
							<?php wp_nonce_field( 'wcs_nux_notice' ); ?>
							<button
								type="submit"
								class="woocommerce-services__connect-jetpack wcs-nux__notice-content-button button button-primary"
							>
								<?php echo esc_html( $content['button_text'] ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
			<?php
			if ( isset( $content['dismissible_id'] ) ) :
				// Add handler for dismissing banner. Only supports a single banner at a time
				wp_enqueue_script( 'wp-util' );
				?>
				<script>
					(
						function ($) {
							$('.wcs-nux__notice').on('click', '.notice-dismiss', function () {
								wp.ajax.post({
									action: 'wc_connect_dismiss_notice',
									dismissible_id: "<?php echo esc_js( $content['dismissible_id'] ); ?>",
									nonce: "<?php echo esc_js( wp_create_nonce( 'wc_connect_dismiss_notice' ) ); ?>"
								})
							})
						}
					)(jQuery)
				</script>
			<?php
			endif;
		}

		/**
		 * Connects the site to Jetpack.
		 */
		public function register_woocommerce_services_jetpack() {
			check_admin_referer( 'wcs_nux_notice' );

			$redirect_url = '';
			if ( isset( $_POST['redirect_url'] ) ) {
				$redirect_url = esc_url_raw( wp_unslash( $_POST['redirect_url'] ) );
			}

			// Make sure we always display the after-connection banner
			// after the before_connection button is clicked
			WC_Connect_Options::update_option( self::SHOULD_SHOW_AFTER_CXN_BANNER, true );

			WC_Connect_Jetpack::connect_site( $redirect_url );
		}
	}
}
