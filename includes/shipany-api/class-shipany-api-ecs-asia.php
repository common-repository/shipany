<?php

use PR\REST_API\SHIPANY_Asia\Auth;
use PR\REST_API\SHIPANY_Asia\Client;
use PR\REST_API\SHIPANY_Asia\Item_Info;
use PR\REST_API\Drivers\JSON_API_Driver;
use PR\REST_API\Drivers\Logging_Driver;
use PR\REST_API\Drivers\WP_API_Driver;
use PR\REST_API\Interfaces\API_Auth_Interface;
use PR\REST_API\Interfaces\API_Driver_Interface;
use Utils\ShipanyHelper;

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

include($baseDir . '/includes/REST_API/Drivers/WP_API_Driver.php');
include($baseDir . '/REST_API/Drivers/Logging_Driver.php');
include($baseDir . '/REST_API/Drivers/JSON_API_Driver.php');
include($baseDir . '/REST_API/SHIPANY_Asia/Auth.php');
include($baseDir . '/REST_API/SHIPANY_Asia/Client.php');
include($baseDir . '/REST_API/SHIPANY_Asia/Item_Info.php');

// Exit if accessed directly or class already exists
if ( ! defined( 'ABSPATH' ) || class_exists( 'SHIPANY_API_eCS_Asia', false ) ) {
	return;
}

class SHIPANY_API_eCS_Asia extends SHIPANY_API {
	/**
	 * The URL to the API.
	 *
	 * @since [*next-version*]
	 */
	const API_URL_PRODUCTION = '';

	/**
	 * The URL to the sandbox API.
	 *
	 * @since [*next-version*]
	 */
	//shipany
	const API_URL = 'https://api.shipany.io/';

	/**
	 * The transient name where the API access token is stored.
	 *
	 * @since [*next-version*]
	 */
	const ACCESS_TOKEN_TRANSIENT = 'pr_SHIPANY_Asia_access_token';

	/**
	 * The API driver instance.
	 *
	 * @since [*next-version*]
	 *
	 * @var API_Driver_Interface
	 */
	public $api_driver;
	/**
	 * The API authorization instance.
	 *
	 * @since [*next-version*]
	 *
	 * @var Auth
	 */
	public $api_auth;
	/**
	 * The API client instance.
	 *
	 * @since [*next-version*]
	 *
	 * @var Client
	 */
	public $api_client;

	/**
	 * Constructor.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $country_code The country code.
	 *
	 * @throws Exception If an error occurred while creating the API driver, auth or client.
	 */
	public function __construct( $country_code ) {
		$this->country_code = $country_code;

		try {
			$this->api_driver = $this->create_api_driver();
			$this->api_auth = $this->create_api_auth();
			$this->api_client = $this->create_api_client();
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Initializes the API client instance.
	 *
	 * @since [*next-version*]
	 *
	 * @return Client
	 *
	 * @throws Exception If failed to create the API client.
	 */
	protected function create_api_client() {
		// Create the API client, using this instance's driver and auth objects
		return new Client(
			$this->get_pickup_id(),
			$this->get_soldto_id(),
			$this->get_api_url(),
			$this->api_driver,
			$this->api_auth
		);
	}

	/**
	 * Initializes the API driver instance.
	 *
	 * @since [*next-version*]
	 *
	 * @return API_Driver_Interface
	 *
	 * @throws Exception If failed to create the API driver.
	 */
	protected function create_api_driver() {
		// Use a standard WordPress-driven API driver to send requests using WordPress' functions
		$driver = new WP_API_Driver();

		// This will log requests given to the original driver and log responses returned from it
		$driver = new Logging_Driver( SHIPANY(), $driver );

		// This will prepare requests given to the previous driver for JSON content
		// and parse responses returned from it as JSON.
		$driver = new JSON_API_Driver( $driver );

		//, decorated using the JSON driver decorator class
		return $driver;
	}

	/**
	 * Initializes the API auth instance.
	 *
	 * @since [*next-version*]
	 *
	 * @return API_Auth_Interface
	 *
	 * @throws Exception If failed to create the API auth.
	 */
	protected function create_api_auth() {
		// Get the savedcustomer API credentials
		list( $client_id, $client_secret ) = $this->get_api_creds();
		
		// Create the auth object using this instance's API driver and URL
		return new Auth(
			$this->api_driver,
			$this->get_api_url(),
			$client_id,
			$client_secret,
			static::ACCESS_TOKEN_TRANSIENT
		);
	}
	protected function create_api_auth_test_con($api_key_temp) {
		// Get the savedcustomer API credentials
		list( $client_id, $client_secret ) = $this->get_api_creds();
		
		// Create the auth object using this instance's API driver and URL
		return new Auth(
			$this->api_driver,
			$this->get_api_url(),
			$api_key_temp,
			$client_secret,
			static::ACCESS_TOKEN_TRANSIENT
		);
	}
	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function is_shipany_ecs_asia() {
		return true;
	}

	/**
	 * Retrieves the API URL.
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 *
	 * @throws Exception If failed to determine if using the sandbox API or not.
	 */
	public function get_api_url() {
		return ShipanyHelper::getApiUrlAndRealTk('api', $this->get_setting( 'shipany_api_key' ), $this->get_setting( 'shipany_region' ))['url'];
	}

	/**
	 * Retrieves the API credentials.
	 *
	 * @since [*next-version*]
	 *
	 * @return array The client ID and client secret.
	 *
	 * @throws Exception If failed to retrieve the API credentials.
	 */
	public function get_api_creds() {
		return array(
			$this->get_setting( 'shipany_api_key' ),
			$this->get_setting( 'shipany_api_secret' ),
		);
	}

	/**
	 * Retrieves the Pickup Account ID
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 *
	 * @throws Exception If failed to retrieve the EKP from the settings.
	 */
	public function get_pickup_id() {
		return $this->get_setting( 'shipany_pickup_id' );
	}

	/**
	 * Retrieves the Pickup Account ID
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 *
	 * @throws Exception If failed to retrieve the EKP from the settings.
	 */
	public function get_soldto_id() {
		return $this->get_setting( 'shipany_soldto_id' );
	}

	/**
	 * Retrieves a single setting.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $key     The key of the setting to retrieve.
	 * @param string $default The value to return if the setting is not saved.
	 *
	 * @return mixed The setting value.
	 */
	public function get_setting( $key, $default = '' ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Retrieves all of the ShipAny settings.
	 *
	 * @since [*next-version*]
	 *
	 * @return array An associative array of the settings keys mapping to their values.
	 */
	public function get_settings() {
		return get_option( 'woocommerce_shipany_ecs_asia_settings', array() );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function shipany_test_connection( $client_id, $client_secret ) {
		try {
			// Test the given ID and secret
			$token = $this->api_auth->test_connection( $client_id, $client_secret );
			// Save the token if successful
			$this->api_auth->save_token( $token );
			
			return $token;
		} catch ( Exception $e ) {
			$this->api_auth->save_token( null );
			throw $e;
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function shipany_reset_connection() {
		return $this->api_auth->revoke();
	}

	public function get_shipany_content_indicator() {
		return array(
			'00' => __('Does not contain Lithium Batteries', 'shipany' ),
			'01' => __('Lithium Batteries in item', 'shipany' ),
			'02' => __('Lithium Batteries packed with item', 'shipany' ),
			'03' => __('Lithium Batteries only', 'shipany' ),
			'04' => __('Rechargeable Batteries in item', 'shipany' ),
			'05' => __('Rechargeable Batteries packed with item', 'shipany' ),
			'06' => __('Rechargeable Batteries only', 'shipany' ),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function get_shipany_courier() {

		$response = SHIPANY()->get_shipany_factory()->api_client->get('couriers/');

		if ($response->status == 401) {
			return false;
		}

		if ($response->status != 200) {
			return [];
		}

		$courier_list = $response->body->data->objects;

		return $courier_list;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function get_shipany_products_domestic() {

		$country_code 	= $this->country_code;

		$products 	= $this->list_shipany_products_domestic();

		$accepted_products = array();

		foreach( $products as $product_code => $product ){
			if( strpos( $product['origin_countries'],  $country_code ) !== false ){
				$accepted_products[ $product_code ] = $product['name'];
			}
		}

		return $accepted_products;
	}

	public function list_shipany_products_domestic() {

		$products = array(
			'PDO' => array(
				'name' 	    => __( 'Parcel Domestic', 'shipany' ),
				'origin_countries' => 'TH,VN,AU,MY'
			),
			'PDE' => array(
				'name' 	    => __( 'Parcel Domestic Expedited', 'shipany' ),
				'origin_countries' => 'AU,VN'
			),/*
			'PDR' => array(
				'name' 	    => __( 'Parcel Return', 'shipany' ),
				'origin_countries' => 'TH,VN,MY'
			),*/
			'SDP' => array(
				'name' 	    => __( 'Parcel Metro', 'shipany' ),
				'origin_countries' => 'VN,TH,MY'
			),
		);

		return $products;
	}


	public function send_pickup_request( $order_id ) {
		$response_object = $this->api_client->send_pickup( $order_id );
		return $response_object;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 * 
	 * @deprecated version
	 */
	public function get_shipany_label( $args, $auto = false ) {

		$order_id = isset( $args[ 'order_details' ][ 'order_id' ] )
			? $args[ 'order_details' ][ 'order_id' ]
			: null;
		$order=wc_get_order($order_id);
		if ($args["order_details"]["weight"] <= 0) {
			$args["order_details"]["weight"] = 1;
		}
		$uom 				= get_option( 'woocommerce_weight_unit' );
		$label_format 		= '';
        $is_cross_border 	= SHIPANY()->is_crossborder_shipment( $args['shipping_address']['country'] );
		try {
			$item_info = new Item_Info( $args, $uom, $is_cross_border );
		} catch (Exception $e) {
			throw $e;
		}
		
		// Create the shipping label
		/*
		$label_info			= $this->api_client->create_label( $item_info );
		$label_pdf_data 	= ( $label_format == 'ZPL' )? $label_info->content : base64_decode( $label_info->content );
		$shipment_id 		= $label_info->shipmentID;
		$this->save_shipany_label_file( 'item', $shipment_id, $label_pdf_data );
		*/
		//shipany
		$response = $this->api_client->create_label( $item_info, $auto );
		if ($response == false) {
			return false;
		}
		$response_object = $response->body->data->objects[0];

		$shipment_id = $response_object->uid;
		$lab_url = $response_object->lab_url;
		if ($lab_url != "") {
			$response= wp_remote_get($lab_url, array( 'sslverify' => false ));
			$label_pdf_data = wp_remote_retrieve_body( $response );
		} else {
			$label_pdf_data = "";
		}
		
		if ($label_pdf_data != "") {
			$this->save_shipany_label_file( 'item', $shipment_id, $label_pdf_data );
		}
		

		if ($response_object->pay_stat == 'Insufficient balance' || $response_object->pay_stat == 'Insufficient Credit') {
			$order->update_meta_data( '_pr_shipment_shipany_order_state', 'Order_Drafted' );
			$order->save();
			sprintf(__( 'Failed to create ShipAny order: %s', 'shipany'),'NO CREDIT');
			return array(
				'label_path' 			=> $this->get_shipany_label_file_info( 'item', $shipment_id )->path,
				//'label_path' 			=> $lab_url,
				'shipment_id' 			=> $shipment_id,
				'tracking_number' 		=> $shipment_id,
				'tracking_status' 		=> '',
				'insufficient_balance'  => true,
				'courier_service_plan'	=> $response_object->cour_svc_pl . '-' . $response_object->cour_ttl_cost->ccy . ' ' . $response_object->cour_ttl_cost->val,
				'asn_id'				=> $response_object->asn_id
			);
		} else if ($response_object->ext_order_not_created == 'x') {
			$order->update_meta_data( '_pr_shipment_shipany_order_state', 'Order_Drafted' );
			$order->save();
			$response_details = $response->body->result->details[0];
			sprintf(__( 'Failed to create ShipAny order: %s', 'shipany'),'ERROR');
			return array(
				'label_path' 			=> $this->get_shipany_label_file_info( 'item', $shipment_id )->path,
				//'label_path' 			=> $lab_url,
				'shipment_id' 			=> $shipment_id,
				'tracking_number' 		=> $shipment_id,
				'tracking_status' 		=> '',
				'courier_service_plan'	=> $response_object->cour_svc_pl . '-' . $response_object->cour_ttl_cost->ccy . ' ' . $response_object->cour_ttl_cost->val,
				'asn_id'				=> $response_object->asn_id,
				'ext_order_not_created' => $response_object->ext_order_not_created,
				'response_details' 		=> $response_details
			);
		} else {
			$order->update_meta_data( '_pr_shipment_shipany_order_state', 'Order_Created' );
			$order->save();
		}
		return array(
			'label_path' 			=> $this->get_shipany_label_file_info( 'item', $shipment_id )->path,
			'label_path_s3' 		=> $lab_url,
			'shipment_id' 			=> $shipment_id,
			'tracking_number' 		=> $shipment_id,
			'tracking_status' 		=> '',
			'courier_service_plan'	=> $response_object->cour_svc_pl . ' - ' . $response_object->cour_ttl_cost->ccy . ' ' . $response_object->cour_ttl_cost->val,
			'asn_id'				=> $response_object->asn_id,
			'courier_tracking_number'=> $response_object->trk_no,
			'courier_tracking_url'=> $response_object->trk_url,
            'commercial_invoice_url'=> $response_object->comm_invoice_url
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function delete_shipany_label( $label_info ) {
		
		if ( ! isset( $label_info['label_path'] ) ) {
			throw new Exception( __( 'ShipAny Order has no path1', 'shipany' ) );
		}
		$shipment_id 	= $label_info['shipment_id'];
		$response 		= $this->api_client->delete_label( $shipment_id );
			
		$label_path = $label_info['label_path'];

		if ( file_exists( $label_path ) ) {
			$res = unlink( $label_path );

			if ( ! $res ) {
				throw new Exception( __( 'ShipAny Order could not be deleted!', 'shipany' ) );
			}
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function close_out_shipment( $shipment_ids = array() ){

		$response 	= $this->api_client->close_out_labels( $this->country_code, $shipment_ids );
		
		$return = array();

		if( isset( $response->responseStatus->messageDetails ) ){

			foreach( $response->responseStatus->messageDetails as $msg ){
				
				if( isset( $msg->messageDetail ) ){
					$return['message'] = $msg->messageDetail;
				}

			}
		}

		return $return;
	}

	/**
	 * Retrieves the filename for item label files.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $barcode The item barcode.
	 * @param string $format The file format.
	 *
	 * @return string
	 */
	public function get_shipany_item_label_file_name( $barcode, $format = 'pdf' ) {
		return sprintf('shipany-%s.%s', $barcode, $format);
	}

	/**
	 * Retrieves the file info for a item label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $barcode The item barcode.
	 * @param string $format The file format.
	 *
	 * @return object An object containing the file "path" and "url" strings.
	 */
	public function get_shipany_item_label_file_info( $barcode, $format = 'pdf' ) {
		$file_name = $this->get_shipany_item_label_file_name($barcode, "pdf");

		return (object) array(
			'path' => SHIPANY()->get_shipany_label_folder_dir() . $file_name,
			'url' => SHIPANY()->get_shipany_label_folder_url() . $file_name,
		);
	}

	/**
	 * Retrieves the file info for any label file, based on type.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $type The label type: "item" or "order".
	 * @param string $key The key: barcode for type "item", and order ID for type "order".
	 *
	 * @return object An object containing the file "path" and "url" strings.
	 */
	public function get_shipany_label_file_info( $type, $key ) {

		if( $type == 'closeout' ){
			return $this->get_shipany_close_out_label_file_info( $key, 'pdf' );
		}

		$label_format = strtolower( $this->get_setting( 'shipany_label_format' ) );
		// Return info for "item" type
		return $this->get_shipany_item_label_file_info( $key, $label_format );
	}

	/**
	 * Saves an item label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $type The label type: "item", or "order".
	 * @param string $key The key: barcode for type "item", and order ID for type "order".
	 * @param string $data The label file data.
	 *
	 * @return object The info for the saved label file, containing the "path" and "url".
	 *
	 * @throws Exception If failed to save the label file.
	 */
	public function save_shipany_label_file( $type, $key, $data ) {
		// global $woocommerce, $post;

		// Get the file info based on type
		$file_info = $this->get_shipany_label_file_info( $type, $key );
		
		// Validate all file path including windows path
		if ( validate_file( $file_info->path ) > 0 && validate_file( $file_info->path ) !== 2 ) {
			throw new Exception( __( 'Invalid file path!', 'shipany' ) );
		}

		$file_ret = file_put_contents( $file_info->path, $data );
		#shipany bypass
		// if ( empty( $file_ret ) ) {
		// 	throw new Exception( __( 'label file cannot be saved!', 'shipany' ) );
		// }

		return $file_info;
	}

	/**
	 * Deletes an AWB label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $type The label type: "item", "awb" or "order".
	 * @param string $key The key: barcode for type "item", AWB for type "awb" and order ID for type "order".
	 *
	 * @throws Exception If the file could not be deleted.
	 */
	public function delete_shipany_label_file( $type, $key )
	{
		// Get the file info based on type
		$file_info = $this->get_shipany_label_file_info( $type, $key );

		// Do nothing if file does not exist
		if ( ! file_exists( $file_info->path ) ) {
			return;
		}

		// Attempt to delete the file
		$res = unlink( $file_info->path );

		// Throw error if the file could not be deleted
		if (!$res) {
			throw new Exception(__(' AWB Label could not be deleted!', 'shipany'));
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function shipany_validate_field( $key, $value ) {
	}

}
