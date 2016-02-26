<?php

if ( ! class_exists( 'WC_Connect_Shipping_Method' ) ) {

	class WC_Connect_Shipping_Method extends WC_Shipping_Method {

		/**
		 * @var object A reference to a the fetched properties of the service
		 */
		protected $service = null;

		public function __construct( $id_or_instance_id = null ) {

			// If $arg looks like a number, treat it as an instance_id
			// Otherwise, treat it as a (method) id (e.g. wc_connect_usps)
			$this->instance_id = null;
			if ( is_numeric( $id_or_instance_id ) ) {
				$this->instance_id = absint( $id_or_instance_id );
				$this->service = WC_Connect_Services_Store::getInstance()->get_service_by_instance_id( $this->instance_id );
			} else if ( ! empty( $id_or_instance_id ) ) {
				$this->service = WC_Connect_Services_Store::getInstance()->get_service_by_id( $id_or_instance_id );
			} else {
				throw new Exception( 'Attempted to construct a default WC_Connect_Shipping_Method' );
			}

			$this->id = $this->service->id;
			$this->method_title = $this->service->method_title;
			$this->method_description = $this->service->method_description;

			$this->supports = array(
				'shipping-zones',
				'instance-settings'
			);

			$this->enabled = $this->get_option( 'enabled' );
			$this->title = $this->get_option( 'title' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

			// Note - we cannot hook admin_enqueue_scripts here because we need an instance id
			// and this constructor is not called with an instance id until after
			// admin_enqueue_scripts has already fired.  This is why WC_Connect_Loader
			// does it instead

		}

		/**
		 * Returns the JSON schema for the form from the settings for this service
		 *
		 * @return array
		 */
		public function get_form_schema() {
			return $this->service->service_settings;
		}

		/**
		 * Returns the settings for this service (e.g. for use in the form or for
		 * sending to the rate request endpoint
		 *
		 * @return array
		 */
		public function get_form_settings() {
			return array();
		}

		/**
		 * Takes the JSON schema for the form and extracts defaults (and other fields)
		 * in a format and with values compatible with the WC_Settings_API
		 *
		 * @return array
		 */
		public function get_instance_form_fields() {

			$form_schema = $this->get_form_schema();
			$fields = array();

			foreach ( $form_schema['properties'] as $property_key => $property_values ) {

				// Special handling for WC boolean, which is weird
				$type = $property_values['type'];
				$default = $property_values['default'];
				if ( "boolean" == $type ) {
					$type = "checkbox";
					$default = $default ? "yes" : "no";
				}

				$fields[ $property_key ] = array(
					'type'    => $type,
					'title'   => $property_values['title'],
					'label'   => $property_values['description'],
					'default' => $default,
				);
			}

			return $fields;

		}

		/**
		 * Handle the settings form submission.
		 *
		 * This method will pass the settings values off to the WCC server for validation.
		 *
		 * @return bool
		 */
		public function process_admin_options() {

			$settings = $_POST;
			unset( $settings['subtab'], $settings['_wpnonce'], $settings['_wp_http_referer'] );

			// Validate settings with WCC server
			$result = WC_Connect_API_Client::validate_service_settings( $this->id, $settings );

			if ( is_wp_error( $result ) ) {

				$this->add_error( $result->get_error_message() );

				return false;

			}

		}

		public function calculate_shipping( $package = array() ) {

			$response = WC_Connect_API_Client::get_shipping_rates( array(), $package );

			if ( ! is_wp_error( $response ) ) {
				if ( array_key_exists( $this->id, $response ) ) {
					$rates = $response[$this->id];

					foreach ( (array) $rates as $rate ) {
						$rate_to_add = array(
							'id' => $this->id,
							'label' => $rate['title'],
							'cost' => $rate['rate'],
							'calc_tax' => 'per_item'
						);

						$this->add_rate( $rate_to_add );
					}
				}
			}

			// TODO log error if get_shipping_rates fails
		}

		public function admin_options() {
			global $hide_save_button;
			$hide_save_button = true;

			?>
				<div id="wc-connect-admin-container"></div>
			<?php
		}

	}
}

