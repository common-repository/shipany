<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class SHIPANY_API_REST_Label extends SHIPANY_API_REST implements SHIPANY_API_Label {

	private $shipany_label_format = 'PDF';
	private $shipany_label_size = '4x6'; // must be lowercase 'x'
	private $shipany_label_page = 'A4';
	private $shipany_label_layout = '1x1';

	const SHIPANY_AUTO_CLOSE = '1';

	private $args = array();

	public function __construct( ) {
		try {

			parent::__construct( );
			// Set Endpoint
			$this->set_endpoint( '/shipping/v1/label' );

		} catch (Exception $e) {
			throw $e;
		}
	}

	public function get_shipany_label( $args ) {
		$this->set_arguments( $args );
		$this->set_query_string();

		$response_body = $this->post_request( $args['shipany_settings']['shipany_api_key'], $args['shipany_settings']['shipany_api_secret']);

		// This will work on one order but NOT on bulk!
		$label_response = $response_body->shipments[0]->packages[0]->responseDetails;
		$package_id = $label_response->labelDetails[0]->packageId;

		$label_tracking_info = $this->save_label_file( $package_id , $label_response->labelDetails[0]->format, $label_response->labelDetails[0]->labelData );

		$label_tracking_info['tracking_number'] = $package_id;
		$label_tracking_info['tracking_status'] = isset( $label_response->trackingNumberStatus ) ? $label_response->trackingNumberStatus : '';

		return $label_tracking_info;
	}

	public function delete_shipany_label( $args ) {
		$upload_path = wp_upload_dir();
		$label_path = str_replace( $upload_path['url'], $upload_path['path'], $args['label_url'] );
		
		if( file_exists( $label_path ) ) {
			$res = unlink( $label_path );
			
			if( ! $res ) {
				throw new Exception( __('ShipAny Order could not be deleted!', 'shipany' ) );
			}
		}
	}

	public function shipany_test_connection( $client_id, $client_secret ) {
		return $this->get_access_token( $client_id, $client_secret );
	}

	public function shipany_validate_field( $key, $value ) {
		$this->validate_field( $key, $value );
	}

	protected function validate_field( $key, $value ) {

		try {

			switch ( $key ) {
				case 'weight':
					$this->validate( $value );
					break;
				case 'hs_code':
					$this->validate( $value, 'string', 4, 20 );
					break;
				default:
					parent::validate_field( $key, $value );
					break;
			}
			
		} catch (Exception $e) {
			throw $e;
		}
	}

	protected function save_label_file( $package_id, $format, $label_data ) {
		$label_name = 'shipany2-' . $package_id . '.' . $format;
		$label_path = SHIPANY()->get_shipany_label_folder_dir() . $label_name;
		$label_url = SHIPANY()->get_shipany_label_folder_url() . $label_name;

		if( validate_file($label_path) > 0 ) {
			throw new Exception( __('Invalid file path!', 'shipany' ) );
		}

		$label_data_decoded = base64_decode($label_data);
		$file_ret = file_put_contents( $label_path, $label_data_decoded );
		
		if( empty( $file_ret ) ) {
			throw new Exception( __('ShipAny Order file cannot be saved!', 'shipany' ) );
		}

		return array( 'label_url' => $label_url, 'label_path' => $label_path);
	}

	protected function set_arguments( $args ) {
		// Validate set args
		
		if ( empty( $args['shipany_settings']['shipany_api_key'] ) ) {
			throw new Exception( __('Please provide the username in the shipping settings', 'shipany' ) );
		}

		if ( empty( $args['shipany_settings']['shipany_api_secret'] )) {
			throw new Exception( __('Please provide the password for the username in the shipping settings', 'shipany') );
		}

		// Validate order details
		if ( empty( $args['shipany_settings']['pickup'] ) ) {
			throw new Exception( __('Please provide a pickup account in the shipping settings', 'shipany' ) );
		}

		if ( empty( $args['shipany_settings']['distribution'] )) {
			throw new Exception( __('Please provide a distribution center in the shipping settings', 'shipany') );
		}

		if ( empty( $args['order_details']['shipany_product'] )) {
			throw new Exception( __(' "Product" is empty!', 'shipany') );
		}

		if ( empty( $args['order_details']['order_id'] )) {
			throw new Exception( __('Shop "Order ID" is empty!', 'shipany') );
		}

		if ( empty( $args['order_details']['weightUom'] )) {
			throw new Exception( __('Shop "Weight Units of Measure" is empty!', 'shipany') );
		}

		if ( empty( $args['order_details']['weight'] )) {
			throw new Exception( __('Order "Weight" is empty!', 'shipany') );
		}

		// Validate weight
		try {
			$this->validate_field( 'weight', $args['order_details']['weight'] );
		} catch (Exception $e) {
			throw new Exception( 'Weight - ' . $e->getMessage() );
		}

		if ( empty( $args['order_details']['currency'] )) {
			throw new Exception( __('Shop "Currency" is empty!', 'shipany') );
		}

		// Validate shipping address
		if ( empty( $args['shipping_address']['address_1'] )) {
			throw new Exception( __('Shipping "Address 1" is empty!', 'shipany') );
		}

		if ( empty( $args['shipping_address']['city'] )) {
			throw new Exception( __('Shipping "City" is empty!', 'shipany') );
		}

		if ( empty( $args['shipping_address']['country'] )) {
			throw new Exception( __('Shipping "Country" is empty!', 'shipany') );
		}

		// Add default values for required fields that might not be passed e.g. phone
		$default_args = array( 'shipping_address' => 
									array( 'name' => '',
											'company' => '',
											'address_2' => '',
											'email' => '',
											// 'idNumber' => '',
											// 'idType' => '',
											'postcode' => '',
											'state' => '',
											'phone' => ' '
											),
								'order_details' =>
									array( 'cod_value' => 0,
											'dangerous_goods' => ''
										) 
						);

		$args['shipping_address'] = wp_parse_args( $args['shipping_address'], $default_args['shipping_address'] );
		$args['order_details'] = wp_parse_args( $args['order_details'], $default_args['order_details'] );

		$default_args_item = array( 'item_description' => '',
									'sku' => '',
									'item_value' => 0,
									'country_origin' => '',
									'hs_code' => '',
									'qty' => 1
									);

		foreach ($args['items'] as $key => $item) {
			
			if ( ! empty( $item['hs_code'] ) ) {
				try {
					$this->validate_field( 'hs_code', $item['hs_code'] );
				} catch (Exception $e) {
					throw new Exception( 'HS Code - ' . $e->getMessage() );
				}
			}

			$args['items'][$key] = wp_parse_args( $item, $default_args_item );			
		}

		$this->args = $args;
	}

}
