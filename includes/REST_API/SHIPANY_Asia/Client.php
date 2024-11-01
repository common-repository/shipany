<?php

namespace PR\REST_API\SHIPANY_Asia;
use Utils\ShipanyHelper;

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);
include($baseDir . '/REST_API/API_Client.php');

use Exception;
use PR\REST_API\API_Client;
use PR\REST_API\Interfaces\API_Auth_Interface;
use PR\REST_API\Interfaces\API_Driver_Interface;
use SHIPANY\Utils\Args_Parser;
use SHIPANY\Utils\CommonUtils;
use stdClass;

/**
 * The API client for eCS.
 *
 * @since [*next-version*]
 */
class Client extends API_Client {
	/**
	 *
	 */
	protected $pickup_id;

	/**
	 *
	 */
	protected $soldto_id;

	/**
	 *
	 */
	protected $shipping_shipnay_settings;

	/**
	 * The language of the message
	 *
	 */
	protected $language = 'en';

	/**
	 * The version of the message
	 *
	 */
	protected $version = '1.4';

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 *
	 * @param string $contact_name The contact name to use for creating orders.
	 */
	public function __construct( $pickup_id, $soldto_id, $base_url, API_Driver_Interface $driver, API_Auth_Interface $auth = null ) {
		parent::__construct( $base_url, $driver, $auth );

		$this->pickup_id = $pickup_id;
		$this->soldto_id = $soldto_id;

	}

	/**
	 * Create shipping label
	 *
	 * @since [*next-version*]
	 *
	 * @param int $order_id The order id.
	 *
	 * @deprecated version
	 */
	public function create_label( Item_Info $item_info, $auto ){
		$shipping_shipnay_settings = SHIPANY()->get_shipping_shipany_settings();
		$route 	= $this->shipping_label_route();
		//$data = $this->item_info_to_request_data( $item_info );
		//shipany
		$data = $this->item_info_to_request_data_shipany( $item_info, $auto );

		$response = $this->post($route, $data);

		//shipany
		//no matter draft or not, api always return 201 when order created
		if ( $response->status === 201 || $response->status === 200 ) {
			return $response;
		}

		// If auto create order resp is not 201/200, it will enter this to make sure not break the woo sale order creation
		if ($auto == true) {
			return false;
		}
		// if ( $response->status === 400 && isset($response->body->result->details[0]) ) {
		// 	if (strpos($response->body->result->details[0], 'merchant ID nor') != false ) {
		// 		return false;
		// 	}
		// }
		throw new Exception(
			sprintf(
				__( 'Failed to create ShipAny order: %s', 'shipany'),
				$this->generate_error_details( $response )
			)
		);
	}
	public function update_order( $order_id, $parm ){
		$shipping_shipnay_settings = SHIPANY()->get_shipping_shipany_settings();
		$api_key_temp = $shipping_shipnay_settings['shipany_api_key'];

		$result = ShipanyHelper::getApiUrlAndRealTk('api', $api_key_temp, $shipping_shipnay_settings['shipany_region']);
		$temp_api_endpoint = $result['url'];
		$api_key_temp = $result['api-tk'];

        $order_detail = wp_remote_get($temp_api_endpoint . 'orders/' . $order_id . '/', array(
            'headers' => array(
                'api-tk'=> $api_key_temp,
                'order-from'=>'Woocommerce'
            )
        ));
		if (is_wp_error( $order_detail )) {
			wp_send_json( array(
				'error' => $order_detail->get_error_message()
			) );
		}
        $order_detail1 = json_decode($order_detail["body"]);
		if ($order_detail1->data->objects[0]->pay_stat == 'Insufficient balance' || $order_detail1->data->objects[0]->pay_stat == 'Insufficient Credit' || $order_detail1->data->objects[0]->ext_order_not_created == 'x') {
			$route = 'orders/' . $order_id . '/';
			$regen = false;
		} else if ($order_detail1->data->objects[0]->cur_stat == 'Order Created' && $order_detail1->data->objects[0]->lab_url != '') {
			$route = 'orders/' . $order_id . '/';
			$regen = true;
		} else {
			$route = 'orders/' . $order_id . '/';
			$regen = false;
		}
		$response = $this->patch($route, json_encode($parm), array(), array(), array(
			'regen' => $regen ? 'true' : 'false'
		));
		return $response;
	}
	public function send_pickup( $order_id ){
		$route = 'orders/' . $order_id . '/';
		$data = array(
			'ops' => 
			array(
			  0 => 
			  array(
				'op' => 'add',
				'path' => '/states/0',
				'value' => 
				array(
				  'stat' => 'Pickup Request Sent',
				),
			  ),
			),
		);
		$response = $this->patch($route, json_encode($data));
		return $response;
	}

	public function close_out_labels( $country_code, $shipment_ids = array() ){

		$route 			= $this->close_out_label_route();
		$shipment_items = array();
		
		if( is_array( $shipment_ids ) && count( $shipment_ids ) > 0 ){

			foreach( $shipment_ids as $shipment_id ){
				$shipment_items[] = array(
					'shipmentID' => $shipment_id,
				);
			}

		}

		$data 		= array(
			'closeOutRequest' 	=> array(
				'hdr' 	=> array(
					'messageType' 		=> $this->get_type( 'closeout' ),
					'messageDateTime' 	=> $this->get_datetime(),
					'messageVersion' 	=> '1.3',
					'messageLanguage' 	=> $this->get_language()
				),
				'bd' 	=> array(
					'pickupAccountId' 	=> $this->pickup_id,
					'soldToAccountId'	=> $this->soldto_id,
					'generateHandover' 	=> 'Y',
					'handoverMethod' 	=> 1,
				)
			)
		);

		if( !in_array( $country_code, array('IN', 'CN', 'HK', 'AU', 'SG', 'MY', 'TH') ) ){
			$data['closeOutRequest']['bd']['handoverID'] = 'C' . date("YmdHis");
			$data['closeOutRequest']['bd']['generateHandover'] = 'N';
		}

		if( count( $shipment_items ) > 0 ){
			$data['closeOutRequest']['bd']['shipmentItems'] = $shipment_items;
		}

		$response 		= $this->post($route, $data );
		$response_body 	= json_decode( $response->body );

		if ( $response->status === 200 ) {

			$status_code = $this->check_status_code( $response_body, 'closeOutResponse' );
			
			if( $status_code == 200 || $status_code == 204 ){

				return $this->get_closeout_content( $response_body, $shipment_ids );

			}
		}

		throw new Exception(
			sprintf(
				__( 'Failed to close out label: %s', 'shipany' ),
				$this->generate_error_details( $response_body, 'closeOutResponse' )
			)
		);

	}

	public function check_status_code( $label_response, $response_type = 'labelResponse' ){
		
		if( !isset( $label_response->$response_type->bd->responseStatus->code ) ){
			throw new Exception( __( 'Status does not exist!', 'shipany' ) );
		}

		return $label_response->$response_type->bd->responseStatus->code;
	}

	public function get_label_content( $label_response ){

		if( !isset( $label_response->labelResponse->bd->labels ) ){
			throw new Exception( __( 'Label info is not exist!', 'shipany' ) );
		}

		$labels_info 		= $label_response->labelResponse->bd->labels;

		foreach( $labels_info as $info ){

			if( !isset( $info->content ) ){
				throw new Exception( __( 'Label content is not exist!', 'shipany' ) );
			}elseif( !isset( $info->shipmentID ) ){
				throw new Exception( __( 'Shipment ID is not exist!', 'shipany' ) );
			}else{

				return $info;

			}
		}

		return false;
	}

	public function get_closeout_content( $response, $shipment_ids ){

		if( !isset( $response->closeOutResponse->bd->responseStatus->messageDetails ) && count( $shipment_ids ) < 1 ){
			throw new Exception( __( 'Message Detail does not exist!', 'shipany' ) );
		}

		return $response->closeOutResponse->bd;
	}

	public function generate_error_details( $label_response, $response_type = 'labelResponse' ){

		$error_details 	= '';

		if( $response_type == 'labelResponse' ){
			if( isset( $label_response->$response_type->bd->labels ) ) {
				$labels = $label_response->$response_type->bd->labels;
				$error_details .= $this->get_error_lists( $labels );
			} elseif( $label_response->status >= 500 ) {
				$error_details .= 'System error';
			} else {
				$error_details .= $label_response->body->result->details[0];
			}
		}elseif( $response_type == 'closeOutResponse' || $response_type == 'deleteShipmentResp' ){
			if( isset( $label_response->$response_type->bd->shipmentItems ) ) {
				$items = $label_response->$response_type->bd->shipmentItems;
				$error_details .= $this->get_error_lists( $items );
			}
		}
		
		$error_exception = '';

		if( !empty( $error_details ) ){
			$error_exception .= '<ul class = "wc_shipany_error">' . $error_details . '</ul>';
		}
		return $error_exception;
	}

	public function get_error_lists( $items ){

		$error_details = '';

		foreach( $items as $item ){
	
			if( !isset( $item->responseStatus->messageDetails ) ){
				continue;
			}

			$shipment_id_text = '';
			if( isset( $item->shipmentID ) ){
				$shipment_id_text = $item->shipmentID . ' - ';
			}

			foreach( $item->responseStatus->messageDetails as $message_detail ){

				if( isset( $message_detail->messageDetail ) ){

					$error_details .= '<li>' . $shipment_id_text . $message_detail->messageDetail . '</li>';

				}

			}

		}

		return $error_details;
	}

	/**
	 * Get message type.
	 *
	 * @param string $type The type of the message.
	 *
	 * @return string The type of the message.
	 */
	protected function get_type( $type = 'create' ){

		if( $type == 'delete' ) {
			return 'DELETESHIPMENT';
		}elseif( $type == 'closeout' ){
			return 'CLOSEOUT';
		}elseif( $type == 'create' ) {
			return 'LABEL';
		}
	}

	/**
	 * Get date time.
	 *
	 * @return string The date and time of the message.
	 */
	protected function get_datetime(){
		return date( 'c', time() );
	}

	/**
	 * Get message language.
	 *
	 * @return string The language of the message.
	 */
	protected function get_language(){
		return $this->language;
	}

	/**
 * Get message version.
 *
 * @return string The version of the message.
 */
    protected function get_version(){
        return $this->version;
    }

    /**
     * Get message version.
     *
     * @return string The version of the message.
     */
    protected function get_shipment_id( $prefix, $id ){
        $prefix = trim( $prefix );
        return $prefix . $id . time();
    }


	// public function query_rate( $received_data, $header = array(), $trigger_from_order_create = false ) {

	// 	// handle weight unit issue, do nothing if the query_rate is call from order create(function item_info_to_request_data_shipany). It is because weight already convert in that function
	// 	$weight_units = get_option( 'woocommerce_weight_unit', 'kg' );
	// 	if ($weight_units != 'kg' && $trigger_from_order_create != true) {
	// 		// convert order total weight
	// 		try {
	// 			$received_data["wt"]["val"] = weight_convert($received_data["wt"]["val"], $weight_units);
	// 			foreach ($received_data['items'] as &$item) {
	// 				if (is_array($item)) {
	// 					$item['wt']['val'] = weight_convert($item['wt']['val'], $weight_units);
	// 				} else if (is_object($item)) {
	// 					$item->wt->val = weight_convert($item->wt->val, $weight_units);
	// 				}
	// 			}
	// 		} catch (Exception $e) {
	// 			echo 'An error occurred: ' . $e->getMessage($e);
	// 		}
	// 	}
	// 	$route = 'couriers-connector/query-rate/ ';
    //     $target_cour_uid = isset($received_data['cour_uid'])?$received_data['cour_uid']:(isset($header['cour-uid'])?$header['cour-uid']:'');
	// 	$data = array(
	// 		"self_drop_off" => isset($received_data["self_drop_off"])?$received_data["self_drop_off"]:false,
	// 		"add-ons" => isset($received_data["add-ons"])?$received_data["add-ons"]:'',
	// 		"stg" => isset($received_data["stg"])?$received_data["stg"]:'',
	// 		"cour_svc_pl" => isset($received_data["cour_svc_pl"])?$received_data["cour_svc_pl"]:'',
    //         "cour_uid" => $target_cour_uid,
	// 		"wt" => array(
	// 			"val" => floatval($received_data["wt"]["val"]) > 0 ? floatval($received_data["wt"]["val"]) : 1,
	// 			"unt" => $received_data["wt"]["unt"]
	// 		),
	// 		"dim" => array(
	// 			"len" => $received_data["dim"]["len"],
	// 			"wid" => $received_data["dim"]["wid"],
	// 			"hgt" => $received_data["dim"]["hgt"],
	// 			"unt" => $received_data["dim"]["unt"]
	// 		),
	// 		"items" => $received_data['items'],
	// 		"mch_ttl_val" => array(
	// 			"val" => $received_data["mch_ttl_val"]["val"],
	// 			"ccy" => "HKD"
	// 		),
	// 		"sndr_ctc" => array(
	// 			"ctc" => array(
	// 				"tit" => "",
	// 				"f_name" => $received_data["sndr_ctc"]["ctc"]["f_name"],
	// 				"l_name" => $received_data["sndr_ctc"]["ctc"]["l_name"],
	// 				"phs" => array(
	// 					array(
	// 						"typ" => $received_data["sndr_ctc"]["ctc"]["phs"][0]["typ"],
	// 						"cnty_code" => $received_data["sndr_ctc"]["ctc"]["phs"][0]["cnty_code"],
	// 						"ar_code" => "",
	// 						"num" => $received_data["sndr_ctc"]["ctc"]["phs"][0]["num"],
	// 						"ext_no" => ""
	// 					)
	// 				),
	// 				"email" => "",
	// 				"note" => ""
	// 			),
	// 			"addr" => array(
	// 				"typ" => $received_data["sndr_ctc"]["addr"]["typ"],
	// 				"ln" => $received_data["sndr_ctc"]["addr"]["ln"],
	// 				"ln2" => $received_data["sndr_ctc"]["addr"]["ln2"],
	// 				"ln3" => "",
	// 				"distr" => $received_data["sndr_ctc"]["addr"]["distr"],
	// 				"city" => $received_data["sndr_ctc"]["addr"]["city"],
	// 				"cnty" => isset($received_data["sndr_ctc"]["addr"]["cnty"]) ? $received_data["sndr_ctc"]["addr"]["cnty"] : "HKG",
	// 				"state" => $received_data["sndr_ctc"]["addr"]["state"]
	// 			)
	// 		),
	// 		"rcvr_ctc" => array(
	// 			"ctc" => array(
	// 				"tit" => "",
	// 				"f_name" => $received_data["rcvr_ctc"]["ctc"]["f_name"],
	// 				"l_name" => $received_data["rcvr_ctc"]["ctc"]["l_name"],
	// 				"phs" => array(
	// 					array(
	// 						"typ" => $received_data["rcvr_ctc"]["ctc"]["phs"][0]["typ"],
	// 						"cnty_code" => $received_data["rcvr_ctc"]["ctc"]["phs"][0]["cnty_code"],
	// 						"ar_code" => "",
	// 						"num" => $received_data["rcvr_ctc"]["ctc"]["phs"][0]["num"],
	// 						"ext_no" => ""
	// 					)
	// 				),
	// 				"email" => $received_data["rcvr_ctc"]["ctc"]["email"],
	// 				"note" => ""
	// 			),
	// 			"addr" => array(
	// 				"typ" => $received_data["rcvr_ctc"]["addr"]["typ"],
	// 				"ln" => $received_data["rcvr_ctc"]["addr"]["ln"],
	// 				"ln2" => $received_data["sndr_ctc"]["addr"]["ln2"],
	// 				"ln3" => "",
	// 				"distr" => isset($received_data["rcvr_ctc"]["addr"]["distr"])?$received_data["rcvr_ctc"]["addr"]["distr"]:'',
	// 				"cnty" => isset($received_data["rcvr_ctc"]["addr"]["cnty"])?$received_data["rcvr_ctc"]["addr"]["cnty"]:'',
	// 				'state' => isset($received_data["rcvr_ctc"]["addr"]["state"])  && $received_data["rcvr_ctc"]["addr"]["cnty"] == 'HKG' ? $received_data["rcvr_ctc"]["addr"]["state"]:'',
	// 				"city" => isset($received_data["rcvr_ctc"]["addr"]["city"])?($received_data["rcvr_ctc"]["addr"]["cnty"] == 'TWN' ? $received_data["rcvr_ctc"]["addr"]["state"] : $received_data["rcvr_ctc"]["addr"]["city"]):'',
    //                 "zc" => isset($received_data["rcvr_ctc"]["addr"]["zc"])?$received_data["rcvr_ctc"]["addr"]["zc"]:''

    //             )
    //         )
	// 	);

    //     $request_headers = array(
    //         "cour-uid" => $target_cour_uid
    //     );
	// 	if (empty($request_headers["cour-uid"])) {
	// 		$request_headers["cour-uid"] = $header['cour-uid'];
	// 	}
	// 	$response = $this->post($route, $data, $request_headers);
	// 	if ( $response->status === 200 ) {
    //         // the trick here we need to consider to empty rate return but have error
	// 		if(isset($response->body->data) && isset($response->body->data->objects) && count($response->body->data->objects) && isset($response->body->data->objects[0]->quots)){
	// 			$quots = $response->body->data->objects[0]->quots;//$response->body->data->objects[0]->quots[0]->quot_uid
	// 			if(!empty($quots)){
	// 				return $quots;
	// 			}
	// 		}
	// 		if( count($response->body->result->details) > 0){
	// 			return $response->body->result->details[0];
	// 		} else {
	// 			return $response->body->result->descr;
	// 		}
    //     }
	// }

	protected function get_consignee( Item_Info $item_info ) {
        $consignee 			= $item_info->consignee;

        if( empty( $consignee['district'] ) && ! empty( $consignee['state'] )) {
            $consignee['district'] = $consignee['state'];
        }

        foreach ( $consignee as $consignee_key => $consignee_val ) {
            // If the field is empty do not pass it
            if( empty( $consignee_val ) ){
                unset( $consignee[ $consignee_key ] );
            }
        }

        return $consignee;
    }

	public function get_order_info($order_id) {
		$response = $this->get('orders/' . $order_id . '/');
		return $response;
	}
	public function get_merchant_info() {
		$response = $this->get('merchants/self/');
		return $response->body->data->objects[0];
	}

	protected function item_info_to_request_data_shipany( Item_Info $item_info, $auto ) {
		$weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
		$shipmentid 		= $this->get_shipment_id( $item_info->shipment['prefix'], $item_info->shipment['order_id'] );
		// $response = $this->get('couriers/');
		$shipping_shipnay_settings = SHIPANY()->get_shipping_shipany_settings();

		//get merchant id from setting otherwise get from api
		// if (isset($shipping_shipnay_settings["merchant_info"])) {
		// 	if ($shipping_shipnay_settings["merchant_info"] != ''){
		// 		$merchant = json_decode($shipping_shipnay_settings["merchant_info"])->data->objects[0];
		// 	} else {
		// 		$merchant = $this->get_merchant_info();
		// 	}
		// } else {
		// 	$merchant = $this->get_merchant_info();
		// }
		$merchant = $this->get_merchant_info();
		//shipany
		$order=wc_get_order($item_info->shipment["order_id"]);
		// $ccy = $order->data["currency"];
		$alpha_three_country_code = CommonUtils::convert_country_code($order->get_shipping_country());
		$contents = $item_info->contents;
		$consignee = $this->get_consignee($item_info);
		$shipment_contents 	= array();
		foreach( $contents as $content ){
			$shipment_content = array(
				'sku' => $content['sku'],
				'name' => $content['description'],
				'descr' => $content['attributes'],
				'unt_price' => array(
					'val' => $content['value'],
					'ccy' => 'HKD'
				),
				'qty' => $content['qty'],
				'wt' => array(
					'val' => weight_convert(floatval($content["weight"]), $weight_unit),
					'unt' => 'kg'
				),
				'dim' => array(
					'len' => $content["length"]?floatval($content["length"]):1,//handle empty length
					'wid' => $content["width"]?floatval($content["width"]):1,//handle empty width
					'hgt' => $content["height"]?floatval($content["height"]):1,//handle empty height
					'unt' => 'cm',
				),
                'stg' => 'Normal' // default value, it will be overwritten by the value from the setting or select drop down box
			);

			// handle non expected dimension value
			if ($shipment_content["dim"]["len"] == 0) {
				$shipment_content["dim"]["len"] = 1;
			}
			if ($shipment_content["dim"]["wid"] == 0) {
				$shipment_content["dim"]["wid"] = 1;
			}
			if ($shipment_content["dim"]["hgt"] == 0) {
				$shipment_content["dim"]["hgt"] = 1;
			}

			// add storage type
			// todo: chk if $item_info->shipment["storage_type"] isset? if yes, get this value rather than the setting one.
			if ($auto == true) {
				$shipment_content["stg"] = $shipping_shipnay_settings["set_default_storage_type"];
			} else if ($auto == false) {
				$shipment_content["stg"] = $item_info->shipment["storage_type"];
			}

			if ($alpha_three_country_code != 'HKG') {
				$shipment_content['unt_price']['ccy']= $order->get_currency();
			}
			$shipment_contents[] = $shipment_content;
		}

		$request_data = array(
			'paid_by_rcvr' => isset($_POST["pr_shipany_paid_by_rec"])? ($_POST["pr_shipany_paid_by_rec"] == 'yes'? true : false ): false,
			'cour_uid' => $item_info->shipment["product_code"],
			// 'cour_uid' => 'ad4b9127-5126-4247-9ff8-d7893ae2d2bb',
			'mch_uid' => $merchant->uid,
			'order_from' => "woocommerce",
			'woocommerce_default_create' => $auto,
			'ext_order_ref' => strval($item_info->shipment["order_id"]) . (isset($shipping_shipnay_settings["shipany_customize_order_id"]) ? $shipping_shipnay_settings["shipany_customize_order_id"] : ''),
			'wt' => array(
				'val' => weight_convert(floatval(($item_info->shipment['weight'])), $weight_unit),
				'unt' => 'kg'
			),
			'dim' => array(
				'len' => count($shipment_contents)>1?1:$shipment_contents[0]["dim"]["len"],
				'wid' => count($shipment_contents)>1?1:$shipment_contents[0]["dim"]["wid"],
				'hgt' => count($shipment_contents)>1?1:$shipment_contents[0]["dim"]["hgt"],
				'unt' => 'cm',
			),
			'items' => $shipment_contents,
			'mch_ttl_val' => array(
				'val' => $item_info->shipment['items_value'],
				'ccy' => $order->get_currency()
			),
			'cour_ttl_cost' => array(
				'val' => 1,
				'ccy' => 'HKD'
			),
			'sndr_ctc' => array(
				'ctc' => array(
					'co_name' => $merchant->co_info->org_ctcs[0]->ctc->f_name,
					'f_name' => $merchant->co_info->ctc_pers[0]->ctc->f_name,
					'l_name' => $merchant->co_info->ctc_pers[0]->ctc->l_name,
					'email' => isset($merchant->co_info->org_ctcs[0]->ctc->email) ? $merchant->co_info->org_ctcs[0]->ctc->email : '',
					'phs' => array(
						array(
							'typ' => $merchant->co_info->ctc_pers[0]->ctc->phs[0]->typ,
							'cnty_code' => $merchant->co_info->ctc_pers[0]->ctc->phs[0]->cnty_code,
							'num' => $merchant->co_info->ctc_pers[0]->ctc->phs[0]->num
						)
					)
				),
				'addr' => array(
					'typ' => $merchant->co_info->org_ctcs[0]->addr->typ,
					'ln' => $merchant->co_info->org_ctcs[0]->addr->ln,
					'ln2' => $merchant->co_info->org_ctcs[0]->addr->ln2,
					'city' => $alpha_three_country_code === 'HKG' ? $merchant->co_info->org_ctcs[0]->addr->cnty : $merchant->co_info->org_ctcs[0]->addr->city,
					'cnty' => $merchant->co_info->org_ctcs[0]->addr->cnty,
					'distr' => $merchant->co_info->org_ctcs[0]->addr->distr,
					'state' => $merchant->co_info->org_ctcs[0]->addr->state
				)
			)
		);

		// paid by receiver
		// FIXME: ????
		if ($auto == true && $shipping_shipnay_settings["shipany_paid_by_rec"] == 'yes') {
			$request_data['paid_by_rcvr'] = true;
		}
		if ($alpha_three_country_code === 'HKG' || $alpha_three_country_code === '') {
			$request_data['rcvr_ctc'] = array(
				'ctc' => array(	
					'co_name' => $item_info->consignee["company"],
					'f_name' => $consignee["first_name"],
					'l_name' => $consignee["last_name"],
					'phs' => array(
						array(
							'typ' => 'Mobile',
							'cnty_code' => '852',
							'num' => $consignee["phone"]
						)
					),
					'email' => $consignee['email'],
				),
				'addr' => array(
					'typ' => 'Residential',
					'ln' => $consignee['address1'],
					'ln2' => isset($consignee['address2'])?$consignee['address2']:'',
					'distr' => $consignee['city'],//Town/District in checkout page
					'cnty' => "Hong Kong S.A.R.",
					'state' => "Hong Kong S.A.R."
				)
				);
		} else {
			$request_data['rcvr_ctc'] = array(
				'ctc' => array(	
					'co_name' => $item_info->consignee["company"],
					'f_name' => $consignee["first_name"],
					'l_name' => $consignee["last_name"],
					'phs' => array(
						array(
							'typ' => 'Mobile',
							'cnty_code' => str_replace('+', '', WC()->countries->get_country_calling_code($order->get_shipping_country())),
							'num' => $consignee["phone"]
						)
					),
					'email' => $consignee['email'],
				),
				'addr' => array(
					'typ' => 'Residential',
					'ln' => $consignee['address1'],
					'ln2' => isset($consignee['address2'])?$consignee['address2']:'',
					'distr' => $alpha_three_country_code == 'TWN' ? $consignee["city"] : $consignee["district"],//Town/District in checkout page
					'cnty' => $alpha_three_country_code,
					'state' => $alpha_three_country_code == 'TWN' ? '' : $consignee["state"],
					'city' => $alpha_three_country_code == 'TWN' ? $consignee["state"] : $consignee["city"],
					'zc' => $consignee["postCode"]
				)
				);
		}

		if ($item_info->shipment["description"] != " ") {
			$request_data['mch_notes'] = array($item_info->shipment["description"]);
		}
		if ($request_data['cour_uid'] == "") {
			$request_data['cour_uid']=$shipping_shipnay_settings["shipany_default_courier"];
		}
		if (in_array($request_data['cour_uid'], $GLOBALS['Courier_uid_mapping']['Hongkong Post'])) {
			$request_data['self_drop_off'] = true;
		}
		$couruer_service_plan = '';
		$wc_order = wc_get_order($_POST["order_id"]);
		$shipany_label_items = $wc_order->get_meta('_pr_shipment_shipany_label_items');
		if (!empty($shipany_label_items[0]['pr_shipany_couier_service_plan'])) {
			// UPS, lalamove
			$couruer_service_plan = json_decode($shipany_label_items[0]['pr_shipany_couier_service_plan']);
			$request_data['cour_ttl_cost'] = array('ccy' => $couruer_service_plan->cour_ttl_cost->ccy, 'val' => $couruer_service_plan->cour_ttl_cost->val);
			$request_data['cour_svc_pl'] = $couruer_service_plan->cour_svc_pl;
			$request_data['cour_type'] = $couruer_service_plan->cour_type;
			if (in_array($item_info->shipment["product_code"], $GLOBALS['Courier_uid_mapping']['Zeek'])) {
				$request_data['ext_cl_mch_id'] = $couruer_service_plan->ext_cl_mch_id;
				$request_data['rcvr_ctc']['addr']['gps']['long'] = explode(',', $couruer_service_plan->recipient_location)[1];
				$request_data['rcvr_ctc']['addr']['gps']['lat'] = explode(',', $couruer_service_plan->recipient_location)[0];
			}

			if (!empty($couruer_service_plan->quot_uid)) {
				$request_data['quot_uid'] = $couruer_service_plan->quot_uid;
			}
		} else {
			if ($auto == true && strpos($shipping_shipnay_settings["shipany_default_courier_additional_service"], 'Lalamove') != false ) {
				$request_data["cour_svc_pl"] = $shipping_shipnay_settings["shipany_default_courier_additional_service"];
				$query_rate_list = $this->query_rate($request_data, array('cour-uid' => $shipping_shipnay_settings["shipany_default_courier"]), $trigger_from_order_create = true);
			} else if ($auto == true && in_array($shipping_shipnay_settings["shipany_default_courier"], $GLOBALS['Courier_uid_mapping']['Zeek'])) {
				$query_rate_list = $this->query_rate($request_data, array('cour-uid' => $shipping_shipnay_settings["shipany_default_courier"]), $trigger_from_order_create = true);
				$request_data['ext_cl_mch_id'] = $query_rate_list[0]->ext_cl_mch_id;
				$request_data['rcvr_ctc']['addr']['gps']['long'] = explode(',', $query_rate_list[0]->recipient_location)[1];
				$request_data['rcvr_ctc']['addr']['gps']['lat'] = explode(',', $query_rate_list[0]->recipient_location)[0];
				$request_data['cour_svc_type'] = 3;
				$request_data['pod_type'] = 3;
			} else {
				$query_rate_list = $this->query_rate($request_data, array(), $trigger_from_order_create = true);
			}
			foreach( $query_rate_list as $query_rate ){
				if( $query_rate->cour_uid == $request_data['cour_uid'] ){
					$request_data['cour_ttl_cost'] = array('ccy' => $query_rate->cour_ttl_cost->ccy, 'val' => $query_rate->cour_ttl_cost->val);
					$request_data['cour_svc_pl'] = $query_rate->cour_svc_pl;
					// $request_data['cour_svc_type'] = $query_rate->cour_svc_type;
					$request_data['cour_type'] = $query_rate->cour_type;
					if (!empty($query_rate->quot_uid)) {
						$request_data['quot_uid'] = $query_rate->quot_uid;
					}
					break;
				}
			}
		}

		if (in_array($item_info->shipment["product_code"], $GLOBALS['Courier_uid_mapping']['Zeek'])) {
			$request_data['cour_svc_type'] = 3;
			$request_data['pod_type'] = 3;
		}

        return Args_Parser::unset_empty_values( $request_data );
	}

	/**
	 * Deletes an item from the remote API.
	 *
	 * @since [*next-version*]
	 *
	 * @param int $shipment_id The ID of the shipment to delete.
	 *
	 * @return stdClass The response.
	 *
	 * @throws Exception
	 */
	public function delete_label( $shipment_id ) {

		$route 	= $this->delete_label_route();

		$data 		= array(
			'deleteShipmentReq' 	=> array(
				'hdr' 	=> array(
					'messageType' 		=> $this->get_type( 'delete' ),
					'messageDateTime' 	=> $this->get_datetime(),
					'messageVersion' 	=> $this->get_version(),
					'messageLanguage' 	=> $this->get_language()
				),
				'bd' 	=> array(
					'pickupAccountId' 	=> $this->pickup_id,
					'soldToAccountId'	=> $this->soldto_id,
					'shipmentItems' 	=> array(
						array(
							'shipmentID' 		=> $shipment_id,
						)
					 ),
				)
			)
		);

		$response 	= $this->post($route, $data);

		$response_body = json_decode( $response->body );
		
		if ( $response->status === 200 ) {

			if( $this->check_status_code( $response_body, 'deleteShipmentResp' ) == 200 ){

				return $response_body->deleteShipmentResp->bd;

			}
		}

		throw new Exception(
			sprintf(
				__( 'Failed to delete label: %s', 'shipany' ),
				$this->generate_error_details( $response_body, 'deleteShipmentResp' )
			)
		);
	}

	/**
	 * Prepares an API route with the customer namespace and EKP.
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 */
	protected function shipping_label_route() {
		//return 'rest/v2/Label';
		//shipany
		return 'orders/ ';
	}

	/**
	 * Prepares an API route for deleting label.
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 */
	protected function delete_label_route() {
		return $this->shipping_label_route(). '/Delete';
	}

	/**
	 * Prepares a CloseOut API route.
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 */
	protected function close_out_label_route() {
		return 'rest/v2/Order/Shipment/CloseOut';
	}

}
