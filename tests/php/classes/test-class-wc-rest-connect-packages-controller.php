<?php

/**
 * Unit test for WC_REST_Connect_Packages_Controller
 */
class WP_Test_WC_REST_Connect_Packages_Controller extends WC_REST_Unit_Test_Case {

	/** @var WC_Connect_API_Client_Live $api_client_mock */
	protected $api_client_mock;

	/** @var WC_Connect_Logger $connect_logger_mock */
	protected $connect_logger_mock;

	/** @var WC_Connect_Service_Schemas_Store $service_schemas_store_mock */
	protected $service_schemas_store_mock;

	/** @var WC_Connect_Service_Settings_Store $settings_store */
	protected $settings_store;

	/**
	 * @inherit
	 */
	public static function setupBeforeClass() {
		require_once dirname( __FILE__ ) . '/../../../classes/class-wc-connect-api-client-live.php';
		require_once dirname( __FILE__ ) . '/../../../classes/class-wc-connect-service-settings-store.php';
		require_once dirname( __FILE__ ) . '/../../../classes/class-wc-connect-logger.php';
		require_once dirname( __FILE__ ) . '/../../../classes/class-wc-connect-service-schemas-store.php';
		require_once dirname( __FILE__ ) . '/../../../classes/class-wc-connect-package-settings.php';
		require_once dirname( __FILE__ ) . '/../../../classes/class-wc-rest-connect-base-controller.php';
		require_once dirname( __FILE__ ) . '/../../../classes/class-wc-rest-connect-packages-controller.php';
	}

	/**
	 * Setup the test case.
	 *
	 * @see WC_Unit_Test_Case::setUp()
	 */
	public function setUp() {
		parent::setUp();

		// Creating a mock class and override protected request method so that we can mock the API response.
		$this->api_client_mock = $this->getMockBuilder( WC_Connect_API_Client_Live::class )
			->disableOriginalConstructor()
			->setMethods( [ 'request' ] )
			->getMock();

		$this->connect_logger_mock = $this->createMock( WC_Connect_Logger::class );
		$this->service_schemas_store_mock = $this->createMock( WC_Connect_Service_Schemas_Store::class );
		$this->settings_store = new WC_Connect_Service_Settings_Store( $this->service_schemas_store_mock, $this->api_client_mock, $this->connect_logger_mock );
	}

	/**
	 * Test that creating custom packages updates the custom packages in settings store while predefined packages remain the same.
	 */
	public function test_creating_packages_with_only_custom_packages_updates_packages_in_settings_store() {
		// Given
		$controller = new WC_REST_Connect_Packages_Controller( $this->api_client_mock, $this->settings_store, $this->connect_logger_mock, $this->service_schemas_store_mock );

		$package_1 = array(
			'is_user_defined' => true,
			'name' => 'Fun box',
			'inner_dimensions' => '10 x 20 x 5',
			'box_weight' => 0.23,
			'max_weight' => 0
		);
		$package_2 = array(
			'is_user_defined' => true,
			'name' => 'Fun envelope',
			'inner_dimensions' => '12 x 16 x 11',
			'box_weight' => 0.5,
			'max_weight' => 0
		);

		$existing_packages = array($package_1);
		$new_packages = array($package_2);
		$this->settings_store->update_packages($existing_packages);

		$predefined_packages_before_creation = $this->settings_store->get_predefined_packages();

		// When
		$request = new WP_REST_Request( 'POST', '/wc/v1/connect/packages' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(json_encode(array(
			'custom' => $new_packages
		)));
		fwrite(STDERR, print_r($request->get_json_params()));
		fwrite(STDERR, print_r($request->get_body_params()));
		$response = $controller->post($request);

		// Then
		$this->assertEquals( 200, $response->status );

		$actual_packages_after_creation = $this->settings_store->get_packages();
		$expected_packages_after_creation = array($package_1, $package_2);
		$this->assertEquals( $expected_packages_after_creation, $actual_packages_after_creation );

		// Predefined packages should not change from the creation request.
		$predefined_packages_after_creation = $this->settings_store->get_predefined_packages();
		$this->assertEquals( $predefined_packages_before_creation, $predefined_packages_after_creation );
	}

	/**
	 * Test that creating predefined packages updates the predefined packages in settings store while custom packages remain the same.
	 */
	public function test_creating_packages_with_only_predefined_packages_updates_predefined_packages_in_settings_store() {
		// Given
		$controller = new WC_REST_Connect_Packages_Controller( $this->api_client_mock, $this->settings_store, $this->connect_logger_mock, $this->service_schemas_store_mock );

		$existing_predefined_packages = array(
			'usps' => array(
				'flat_envelope', 'padded_flat_envelope'
			),
		);
		$new_predefined_packages = array(
			'dhlexpress' => array(
				'SmallPaddedPouch', 'Box2Cube'
			),
			'usps' => array(
				'legal_flat_envelope'
			),
		);
		$this->settings_store->update_predefined_packages($existing_predefined_packages);

		$custom_packages_before_creation = $this->settings_store->get_packages();

		// When
		$request = new WP_REST_Request( 'POST', '/wc/v1/connect/packages' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(json_encode(array(
			'predefined' => $new_predefined_packages
		)));
		fwrite(STDERR, print_r($request->get_json_params()));
		fwrite(STDERR, print_r($request->get_body_params()));
		$response = $controller->post($request);

		// Then
		$this->assertEquals( 200, $response->status );

		$actual_predefined_packages_after_creation = $this->settings_store->get_predefined_packages();
		$expected_predefined_packages_after_creation = array(
			'usps' => array(
				'flat_envelope', 'padded_flat_envelope', 'legal_flat_envelope'
			),
			'dhlexpress' => array(
				'SmallPaddedPouch', 'Box2Cube'
			),
		);
		$this->assertEquals( $expected_predefined_packages_after_creation, $actual_predefined_packages_after_creation );

		// Predefined packages should not change from the creation request.
		$custom_packages_after_creation = $this->settings_store->get_packages();
		$this->assertEquals( $custom_packages_before_creation, $custom_packages_after_creation );
	}

	/**
	 * Test that creating both custom and predefined packages updates the both package types in settings store.
	 */
	public function test_creating_packages_with_both_custom_and_predefined_packages_updates_both_types_of_packages_in_settings_store() {
		// Given
		$controller = new WC_REST_Connect_Packages_Controller( $this->api_client_mock, $this->settings_store, $this->connect_logger_mock, $this->service_schemas_store_mock );

		// Set up custom packages
		$package_1 = array(
			'is_user_defined' => true,
			'name' => 'Fun box',
			'inner_dimensions' => '10 x 20 x 5',
			'box_weight' => 0.23,
			'max_weight' => 0
		);
		$package_2 = array(
			'is_user_defined' => true,
			'name' => 'Fun envelope',
			'inner_dimensions' => '12 x 16 x 11',
			'box_weight' => 0.5,
			'max_weight' => 0
		);
		$this->settings_store->update_packages(array($package_1));

		// Set up predefined packages
		$existing_predefined_packages = array(
			'usps' => array(
				'flat_envelope', 'padded_flat_envelope'
			),
		);
		$new_predefined_packages = array(
			'dhlexpress' => array(
				'SmallPaddedPouch', 'Box2Cube'
			),
			'usps' => array(
				'legal_flat_envelope'
			),
		);
		$this->settings_store->update_predefined_packages($existing_predefined_packages);

		// When
		$request = new WP_REST_Request( 'POST', '/wc/v1/connect/packages' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(json_encode(array(
			'predefined' => $new_predefined_packages,
			'custom' => array($package_2)
		)));
		fwrite(STDERR, print_r($request->get_json_params()));
		fwrite(STDERR, print_r($request->get_body_params()));
		$response = $controller->post($request);

		// Then
		$this->assertEquals( 200, $response->status );

		$actual_packages_after_creation = $this->settings_store->get_packages();
		$expected_packages_after_creation = array($package_1, $package_2);
		$this->assertEquals( $expected_packages_after_creation, $actual_packages_after_creation );

		$actual_predefined_packages_after_creation = $this->settings_store->get_predefined_packages();
		$expected_predefined_packages_after_creation = array(
			'usps' => array(
				'flat_envelope', 'padded_flat_envelope', 'legal_flat_envelope'
			),
			'dhlexpress' => array(
				'SmallPaddedPouch', 'Box2Cube'
			),
		);
		$this->assertEquals( $expected_predefined_packages_after_creation, $actual_predefined_packages_after_creation );
	}
}
