<?php

// No direct access please
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Connect_API_Client_Local_Test_Mock' ) ) {
	require_once( dirname( __FILE__ ) . '/../../classes/class-wc-connect-api-client.php' );

	class WC_Connect_API_Client_Local_Test_Mock extends WC_Connect_API_Client {

		/**
		 * Gets mock endpoint data from json file.
		 *
		 * json data structure must map connect server endpoints to
		 * mock data that will be returned when WCS makes a request.
		 *
		 * @return object
		 */
		private function get_mock_endpoints() {
			$json_string = file_get_contents( plugin_dir_path( __FILE__ ) . 'test_data/services_data_mock_with_card.json' );
			return json_decode( $json_string );
		}

		/**
		 * Gets mock endpoint data from json file as an associative array.
		 *
		 * json data structure must map connect server endpoints to
		 * mock data that will be returned when WCS makes a request.
		 *
		 * @return object
		 */
		private function get_mock_endpoints_associative() {
			$json_string = file_get_contents( plugin_dir_path( __FILE__ ) . 'test_data/services_data_mock_with_card_associative.json' );
			return json_decode( $json_string, true );
		}

		/**
		 * Returns mock data for what would otherwise come from WC connect server
		 *
		 * @param $method
		 * @param $path
		 * @param $body
		 * @return mixed|WP_Error
		 */
		protected function request( $method, $path, $body = array() ) {
			$mock_data = $this->get_mock_endpoints();

			if ( isset( $mock_data->mock_endpoints->$path ) ) {
				return $mock_data->mock_endpoints->$path;
			}

			$mock_data_associative = $this->get_mock_endpoints_associative();
			if ( isset( $mock_data_associative[ 'mock_endpoints' ][ $path ] ) ) {
				return $mock_data_associative[ 'mock_endpoints' ][ $path ];
			}
			// Just return empty data for requests not matching mock endpoint data.
			return new stdClass;
		}
	}
}
