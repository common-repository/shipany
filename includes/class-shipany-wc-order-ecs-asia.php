<?php

use PR\REST_API\SHIPANY_Asia\Item_Info;
use PR\REST_API\SHIPANY_Asia\Client;
use Utils\ShipanyHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SHIPANY_WC_Order_eCS_Asia' ) ) :

class SHIPANY_WC_Order_eCS_Asia extends SHIPANY_WC_Order {
	
	protected $carrier = 'SHIPANY Asia';

	/**
	 * The endpoint for download close out labels.
	 *
	 * @since [*next-version*]
	 */
	const SHIPANY_DOWNLOAD_CLOSE_OUT_ENDPOINT = 'shipany_download_close_out';

	public function init_hooks() {
		parent::init_hooks();

		// add 'Label Created' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_label_column_header' ), 30 );
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_order_label_column_header' ), 30 );

		// add 'Label Created' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_label_column_content' ), 20, 2 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( $this, 'add_order_label_column_content' ), 20, 2 );

		// add bulk order filter for printed / non-printed orders
		add_action( 'restrict_manage_posts', array( $this, 'filter_orders_by_label_created') , 20 );
		add_filter( 'request',               array( $this, 'filter_orders_by_label_created_query' ) );

		// The Close out label download endpoint
		add_action( 'init', array( $this, 'add_download_close_out_endpoint' ) );
		add_action( 'parse_query', array( $this, 'process_download_close_out' ) );
	}

	public function add_download_close_out_endpoint() {
		add_rewrite_endpoint( self::SHIPANY_DOWNLOAD_CLOSE_OUT_ENDPOINT, EP_ROOT );
	}

	public function additional_meta_box_fields( $order_id, $is_disabled, $shipany_label_items, $shipany_obj ) {

		$order 	= wc_get_order( $order_id );
		
        // Get saved package description, otherwise generate the text based on settings
        if($is_disabled || !empty($shipany_label_items['shipany_description'])) {
            $selected_shipany_desc = $shipany_label_items['shipany_description'];
        } else if(!empty($order->get_customer_note())) {
			$selected_shipany_desc = $order->get_customer_note();
        } else {
            $selected_shipany_desc = $this->get_package_description( $order_id );
        }

        woocommerce_wp_textarea_input( array(
            'id'          		=> 'shipany_description',
            'label'       		=> __( 'Package description for customs (50 characters max): ', 'shipany' ),
            'placeholder' 		=> __( 'Please enter description', 'shipany' ),
            'description'		=> '',
            'value'       		=> $selected_shipany_desc,
            'custom_attributes'	=> array( $is_disabled => $is_disabled, 'maxlength' => '50' )
        ) );
	}
	

	/**
	 * Order Tracking Save
	 *
	 * Function for saving tracking items
	 */
	public function get_additional_meta_ids( ) {

		return array( 'shipany_duties', 'shipany_description', 'shipany_is_cod', 'shipany_additional_insurance', 'shipany_insurance_value', 'shipany_obox_service', 'pr_shipany_couier_service_plan' );

	}
	
	protected function get_tracking_url() {
		$result = ShipanyHelper::getApiUrlAndRealTk('portal', $this->shipping_shipnay_settings["shipany_api_key"], $this->shipping_shipnay_settings['shipany_region']);
		return $result['url'] . 'tracking?id=';
	}

	protected function get_package_description( $order_id ) {
		// $this->shipping_shipnay_settings = SHIPANY()->get_shipping_shipany_settings();
		$shipany_desc_default = '';
		$order = wc_get_order( $order_id );
		$ordered_items = $order->get_items();

		$desc_array = array();
		foreach ($ordered_items as $key => $item) {
			$product_id = $item['product_id'];
			$product = wc_get_product( $product_id );
			
			// If product does not exist, i.e. deleted go to next one
			if ( empty( $product ) ) {
				continue;
			}

			switch ($shipany_desc_default) {
				case 'product_cat':
					$product_terms = get_the_terms( $product_id, 'product_cat' );
					if ( $product_terms ) {
						foreach ($product_terms as $key => $product_term) {
							array_push( $desc_array, $product_term->name );
						}
					}
					break;
				case 'product_tag':
					$product_terms = get_the_terms( $product_id, 'product_tag' );
					if ( $product_terms ) {
						foreach ($product_terms as $key => $product_term) {
							array_push( $desc_array, $product_term->name );
						}
					}
					break;
				case 'product_name':
					array_push( $desc_array, $product->get_title() );
					break;
				case 'product_export':
					$export_desc = $product->get_meta('_shipany_export_description', true);
					array_push( $desc_array, $export_desc );
					break;
			}
		}

		// Make sure there are no duplicate taxonomies
		$desc_array = array_unique($desc_array);
		$desc_text = implode(', ', $desc_array);
		$desc_text = mb_substr( $desc_text, 0, 50, 'UTF-8' );
		$desc_text = '';
		return $desc_text;
	}

	protected function get_label_args_settings( $order_id, $shipany_label_items ) {
		// $this->shipping_shipnay_settings = SHIPANY()->get_shipping_shipany_settings();
		$order = wc_get_order( $order_id );

		// Get shipany pickup and distribution center
		$args['shipany_settings']['shipany_api_key'] = $this->shipping_shipnay_settings['shipany_api_key'];
		$args['shipany_settings']['shipany_api_secret'] = '';

		// Get shipany Pickup Address.
		$args[ 'shipany_settings' ]['shipany_contact_name'] 	= '';
		$args[ 'shipany_settings' ]['shipany_address_1'] 		= '';
		$args[ 'shipany_settings' ]['shipany_address_2'] 		= '';
		$args[ 'shipany_settings' ]['shipany_city'] 			= '';
		$args[ 'shipany_settings' ]['shipany_state'] 			= '';
		$args[ 'shipany_settings' ]['shipany_district'] 		= '';
		$args[ 'shipany_settings' ]['shipany_country'] 			= '';
		$args[ 'shipany_settings' ]['shipany_postcode'] 		= '';
		$args[ 'shipany_settings' ]['shipany_phone'] 			= '';
		$args[ 'shipany_settings' ]['shipany_email'] 			= '';

		// Get package prefix
		$args['order_details']['prefix'] = '';
		
		// Get package prefix
		if ( ! empty( $shipany_label_items['shipany_description'] ) ) {
			$args['order_details']['description'] = $shipany_label_items['shipany_description'];
		} else {
			// If description is empty and it is an international shipment throw an error
			if ( $this->is_crossborder_shipment( $order_id ) ) {
				throw new Exception( __('The package description cannot be empty!', 'shipany') );
				
			}			
		}

		// if ( isset( $this->shipping_shipnay_settings['shipany_order_note'] ) && $this->shipping_shipnay_settings['shipany_order_note'] == 'yes' ) {

		// 	if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '3.0', '>=' ) ) {
		// 		$args['order_details']['order_note'] = $order->get_customer_note();
		// 	} else {
		// 		$args['order_details']['order_note'] = $order->customer_note;
		// 	}
		// }

		if ( ! empty( $shipany_label_items['shipany_duties'] ) ) {
			$args['order_details']['duties'] = $shipany_label_items['shipany_duties'];
		}

		if ( ! empty( $shipany_label_items['shipany_is_cod'] ) ) {
			$args['order_details']['is_cod'] = $shipany_label_items['shipany_is_cod'];
		}

		if ( ! empty( $shipany_label_items['shipany_additional_insurance'] ) ) {
			$args['order_details']['additional_insurance'] = $shipany_label_items['shipany_additional_insurance'];
		}

		if ( ! empty( $shipany_label_items['shipany_insurance_value'] ) ) { 
			$args['order_details']['insurance_value'] = $shipany_label_items['shipany_insurance_value'];
		}

		if ( ! empty( $shipany_label_items['shipany_obox_service'] ) ) {
			$args['order_details']['obox_service'] = $shipany_label_items['shipany_obox_service'];
		}

		return $args;
	}
	
	// Pass args by reference to modify DG if needed
	protected function get_label_item_args( $product_id, &$args ) {

		$new_item = array();
		$product = wc_get_product($product_id);
		$dangerous_goods = $product->get_meta('_shipany_dangerous_goods', true);

        if( ! empty( $dangerous_goods ) ) {

	    	if ( isset( $args['order_details']['dangerous_goods'] ) ) {
	    		// if more than one item id DG, make sure to take the minimum value
	    		$args['order_details']['dangerous_goods'] = min( $args['order_details']['dangerous_goods'], $dangerous_goods );
	    	} else {
	    		$args['order_details']['dangerous_goods'] = $dangerous_goods;
	    	}
	    	
		}

		$new_item['item_export'] 		= get_post_meta( $product_id, '_shipany_export_description', true );

		return $new_item;
	}

	protected function save_default_shipany_label_items( $order_id ) {

        $order = wc_get_order( $order_id );

		parent::save_default_shipany_label_items( $order_id );

		$shipany_label_items = $this->get_shipany_label_items( $order_id );
		
		if( empty( $shipany_label_items['shipany_description'] ) ) {
			$shipany_label_items['shipany_description'] = $this->get_package_description( $order_id );
		}

		if( empty( $shipany_label_items['shipany_duties'] ) ) {
			$shipany_label_items['shipany_duties'] = $this->shipping_shipnay_settings['shipany_duties_default'];
		}

		if( empty( $shipany_label_items['shipany_is_cod'] ) ) {
			$shipany_label_items['shipany_is_cod'] = $this->is_cod_payment_method( $order_id ) ? 'yes' : 'no';
		}

		$settings_default_ids = array(
			'shipany_additional_insurance',
			'shipany_obox_service'
		);

		foreach ($settings_default_ids as $default_id) {
			$id_name = str_replace('shipany_', '', $default_id);

			if ( !isset($shipany_label_items[$default_id]) ) {
				$shipany_label_items[$default_id] = isset( $this->shipping_shipnay_settings['shipany_default_' . $id_name] ) ? $this->shipping_shipnay_settings['shipany_default_' . $id_name] : '';
			}
		}

        if( empty( $shipany_label_items['shipany_insurance_value'] ) ) {
            $shipany_label_items['shipany_insurance_value'] = $order->get_subtotal();
        }

		$this->save_shipany_label_items( $order_id, $shipany_label_items );
	}


	public function add_order_label_column_header( $columns ) {
		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {
			$new_columns[ $column_name ] = $column_info;

			if ( 'order_total' === $column_name ) {
				$new_columns['shipany_label_created']      = __( 'ShipAny Order Created', 'shipany' );
				$new_columns['shipany_tracking_number']    = __( 'ShipAny Order Number', 'shipany' );
			}
		}

		return $new_columns;
	}

	public function add_order_label_column_content( $column, $obj ) {
		$order_id = '';
		if(is_object($obj)){
			$order_id = $obj->get_id();
		} else if(is_numeric($obj)){
			$order_id = $obj;
		}

		if ( $order_id ) {
			if( 'shipany_label_created' === $column ) {
				echo $this->get_print_status( $order_id );
			}

			if( 'shipany_tracking_number' === $column ) {
				$tracking_link = ShipanyHelper::get_tracking_url_by_order_id($order_id, 'html');
				echo empty($tracking_link) ? '<strong>&ndash;</strong>' : wp_kses_post($tracking_link);
			}

			if( 'shipany_handover_note' === $column ) {
				echo $this->get_hangover_status( $order_id );
			}
		}
	}

	protected function get_download_label_url( $order_id ) {
		
		if( empty( $order_id ) ) {
			return '';
		}

		$label_tracking_info = $this->get_shipany_label_tracking( $order_id );
		// Check whether the label has already been created or not
		if( empty( $label_tracking_info ) ) {
			return '';
		}
		
		// If no 'label_path' isset but a 'label_url' is set them return it...
		// ... this indicates an old download style label!
		if ( isset( $label_tracking_info['label_url'] ) ){
			return $label_tracking_info['label_url'];
		}

		// Override URL with our solution's download label endpoint:
		return $this->generate_download_url( '/' . self::SHIPANY_DOWNLOAD_ENDPOINT . '/' . $order_id );
	}

	// public function get_order_detail($order_id) {
	// 	$response = SHIPANY()->get_shipany_factory()->api_client->get_order_info($order_id);
	// 	if (!empty($response->body->data->objects[0]->cur_stat)) {
	// 		return $response->body->data->objects[0];
	// 	}
	// 	return '';
	// }

	// private function get_print_status( $order_id ) {
	// 	$label_tracking_info = $this->get_shipany_label_tracking( $order_id );

	// 	if( empty( $label_tracking_info ) ) {
	// 		return '<strong>&ndash;</strong>';
	// 	} else {
	// 		$order_detail = $this->get_order_detail($label_tracking_info['shipment_id']);
	// 		if ($order_detail =="") {
	// 			return '';
	// 		}
	// 		if ($order_detail->pay_stat == "Insufficient balance"){
	// 			return '&#10004'.'(Draft state)';
	// 		} else {
	// 			return '&#10004';
	// 		}
			
	// 	}
	// 	return '';
	// }

	private function get_print_status( $order_id ) {
		$label_tracking_info = $this->get_shipany_label_tracking( $order_id );

		if( empty( $label_tracking_info ) ) {
			return '<strong>&ndash;</strong>';
		} else {
			$order=wc_get_order($order_id);
			$order_stat = $order->get_meta('_pr_shipment_shipany_order_state');
			if ($order_stat == "Order_Created") return '&#10004';
			else if ($order_stat == "Order_Drafted") return '&#10004'.'(Order Drafted)';
			else if ($order_stat == "Pickup_Request_Sent") return '&#10004'.'(Pickup Request Sent)';
			else if ($order_stat == "Pickup_Request_Received") return '&#10004'.'(Pickup Request Received)';
			else if ($order_stat == "Order_Cancelled") return '&#10004'.'(Order Cancelled)';
			else if ($order_stat == "Order_Delivered") return '&#10004'.'(Order Delivered)';
			else if ($order_stat == "Order_Completed") return '&#10004'.'(Order Completed)';
			else if ($order_stat == "Shipping") return '&#10004'.'(Shipping)';
			else if ($order_stat == "In_Transit") return '&#10004'.'(In Transit)';
			else if ($order_stat == "Ready_For_Shipment") return '&#10004'.'(Ready For Shipment)';
			else if ($order_stat == "Ready_For_Deliver") return '&#10004'.'(Ready For Deliver)';
			else if ($order_stat == "Delivery_In_Progress") return '&#10004'.'(Delivery In Progress)';
			else if ($order_stat == "Order_Returned") return '&#10004'.'(Order Returned)';
			else if ($order_stat == "Arrival") return '&#10004'.'(Arrival)';
			else if ($order_stat == "Returned") return '&#10004'.'(Returned)';
			else if ($order_stat == "Abnormal") return '&#10004'.'(Abnormal)';
			else if ($order_stat == "Shipment_On_Hold") return '&#10004'.'(Shipment On Hold)';
			else if ($order_stat == "Order_Processing") return '&#10004'.'(Order Processing)';			
			else if ($order_stat == "Arrived_Transit_Point") return '&#10004'.'(Arrived Transit Point)';
			else if ($order_stat == "Departed_Transit_Point") return '&#10004'.'(Departed Transit Point)';
			else if ($order_stat == "Forwarded") return '&#10004'.'(Forwarded)';
			else if ($order_stat == "Failed_To_Deliver") return '&#10004'.'(Failed To Deliver)';
			else if ($order_stat == "Collected_By_Agent") return '&#10004'.'(Collected By Agent)';
			else if ($order_stat == "Pickup_Request_Rejected") return '&#10004'.'(Pickup Request Rejected)';
			else if ($order_stat == "Return_To_Warehouse") return '&#10004'.'(Return To Warehouse)';
			else if ($order_stat == "Order_Imported") return '&#10004'.'(Order Imported)';
			else if ($order_stat == "Cancelled_On_Request") return '&#10004'.'(Cancelled On Request)';
			else if ($order_stat == "Delivery_Appointment") return '&#10004'.'(Delivery Appointment)';
			else if ($order_stat == "Returning_In_Progress") return '&#10004'.'(Returning In Progress)';
			else if ($order_stat == "Return_Completed") return '&#10004'.'(Return Completed)';
			else if ($order_stat == "More_Info_Required") return '&#10004'.'(More Info Required)';
			else if ($order_stat == "Preparing_For_Pickup") return '&#10004'.'(Preparing For Pickup)';
			else if ($order_stat == "Departure_Scan") return '&#10004'.'(Departure Scan)';
			else if ($order_stat == "Waiting_For_Payment") return '&#10004'.'(Waiting For Payment)';
			else if ($order_stat == "Order_Expired") return '&#10004'.'(Order Expired)';
			else if ($order_stat == "Custom_Clearance_In_Progress") return '&#10004'.'(Custom Clearance In Progress)';
			else if ($order_stat == "Order_Partially_Delivered") return '&#10004'.'(Order Partially Delivered)';
			else if ($order_stat == "Failed_To_Deliver_Pending_Retry") return '&#10004'.'(Failed To Deliver Pending Retry)';
			else if ($order_stat == "Failed_To_Deliver_Abandon_The_Goods") return '&#10004'.'(Failed To Deliver Abandon The Goods)';
			else if ($order_stat == "Failed_To_Deliver_Returning_To_Sender") return '&#10004'.'(Failed To Deliver Returning To Sender)';
			else if ($order_stat == "Delivery_Issue_Action_Required") return '&#10004'.'(Delivery Issue Action Required)';
			else if ($order_stat == "Shipping_Issue_Action_Required") return '&#10004'.'(Shipping Issue Action Required)';
			else if ($order_stat == "Held_At_Yamato") return '&#10004'.'(Held At Yamato)';
			else if ($order_stat == "Failed_To_Deliver_Absence") return '&#10004'.'(Failed To Deliver Absence)';
			else if ($order_stat == "Failed_To_Deliver_Other") return '&#10004'.'(Failed To Deliver Other)';
			else if ($order_stat == "Ready_For_Pickup") return '&#10004'.'(Ready For Pickup)';
			else if ($order_stat == "Collected_By_Courier") return '&#10004'.'(Collected By Courier)';
			else if ($order_stat == "Collected_By_Customer") return '&#10004'.'(Collected By Customer)';
			else if ($order_stat == "Waiting_For_Quotation") return '&#10004'.'(Waiting For Quotation)';
			else if ($order_stat == "Quotation_Provided") return '&#10004'.'(Quotation Provided)';
			else if ($order_stat == "Delivery_In_Progress_Retry") return '&#10004'.'(Delivery In Progress Retry)';
			else if ($order_stat == "Delivered_To_Locker") return '&#10004'.'(Delivered To Locker)';
			else if ($order_stat == "Delivered_To_Conv_Store") return '&#10004'.'(Delivered To Conv Store)';
			else if ($order_stat == "Collected_By_Courier_Overdue") return '&#10004'.'(Collected By Courier Overdue)';
			else if ($order_stat == "Pickup_Req_Rcvd_But_Status_Unavail") return '&#10004'.'(Pickup Req Rcvd But Status Unavail)';
			else if ($order_stat == "Shipment_Under_Processing") return '&#10004'.'(Shipment Under Processing)';
			else if ($order_stat == "Quotation_Declined") return '&#10004'.'(Quotation Declined)';
			else if ($order_stat == "Quotation_Accepted") return '&#10004'.'(Quotation Accepted)';
			else if ($order_stat == "Collected_By_Admin_Overdue") return '&#10004'.'(Collected By Admin Overdue)';
			else if ($order_stat == "Returning_From_Conv_Store") return '&#10004'.'(Returning From Conv Store)';
			else if ($order_stat == "Delivery_Address_Updated") return '&#10004'.'(Delivery Address Updated)';
			else if ($order_stat == "Return_Cancelled_Scheduling_Next_Delivery") return '&#10004'.'(Return Cancelled Scheduling Next Delivery)';
			else if ($order_stat == "Delivery_Cancelled") return '&#10004'.'(Delivery Cancelled)';
			else if ($order_stat == "No_Space_Available") return '&#10004'.'(No Space Available)';
			else if ($order_stat == "No_Space_Fit") return '&#10004'.'(No Space Fit)';
			else if ($order_stat == "Abnormal_Device") return '&#10004'.'(Abnormal Device)';
			else if ($order_stat == "Rejected_By_Customer") return '&#10004'.'(Rejected By Customer)';
			else if ($order_stat == "Oversized_Parcel") return '&#10004'.'(Oversized Parcel)';
			else if ($order_stat == "Delayed_Due_To_Traffic") return '&#10004'.'(Delayed Due To Traffic)';
			else if ($order_stat == "Waiting_To_Be_Returned") return '&#10004'.'(Waiting To Be Returned)';
			else if ($order_stat == "Return_Scheduled") return '&#10004'.'(Return Scheduled)';
			else if ($order_stat == "Requested_For_Diff_Delivery_Pt") return '&#10004'.'(Requested For Diff Delivery Pt)';
			else if ($order_stat == "Failed_To_Deliver_Oversized_Parcel") return '&#10004'.'(Failed To Deliver Oversized Parcel)';
			else if ($order_stat == "Abnormal_Manual_Handling") return '&#10004'.'(Abnormal Manual Handling)';
			else if ($order_stat == "Abnormal_Other") return '&#10004'.'(Abnormal Other)';
			else if ($order_stat == "Delivering_To_Conv_Store") return '&#10004'.'(Delivering To Conv Store)';
			else if ($order_stat == "Assigned_To_Courier") return '&#10004'.'(Assigned To Courier)';
			else if ($order_stat == "Courier_Reach_Pickup_Point_Nearby") return '&#10004'.'(Courier Reach Pickup Point Nearby)';
			else if ($order_stat == "Courier_Reach_Pickup_Point") return '&#10004'.'(Courier Reach Pickup Point)';
			else if ($order_stat == "Courier_Reach_Destination_Nearby") return '&#10004'.'(Courier Reach Destination Nearby)';
			else if ($order_stat == "Order_Cancelled_Bfr_Collection") return '&#10004'.'(Order Cancelled Bfr Collection)';
			else if ($order_stat == "Order_Cancelled_Aft_Collection") return '&#10004'.'(Order Cancelled Aft Collection)';
			else if ($order_stat == "Assigned_To_Another_Courier") return '&#10004'.'(Assigned To Another Courier)';
			else if ($order_stat == "Pickup_Request_Expired") return '&#10004'.'(Pickup Request Expired)';
			else if ($order_stat == "Order_Submitted") return '&#10004'.'(Order Submitted)';
			else if ($order_stat == "Order_Dropped") return '&#10004'.'(Order Dropped)';
			else if ($order_stat == "Courier_Pickup_Failed") return '&#10004'.'(Courier Pickup Failed)';
			else if ($order_stat == "Receiver_Request_Door_Delivery") return '&#10004'.'(Receiver Request Door Delivery)';
			else if ($order_stat == "Custom_Clearance_Release") return '&#10004'.'(Custom Clearance Release)';
			else if ($order_stat == "Custom_Clearance_Delay") return '&#10004'.'(Custom Clearance Delay)';
			else if ($order_stat == "Address_Invalid") return '&#10004'.'(Address Invalid)';
			else if ($order_stat == "Assigned_Documents_To_Another_Courier") return '&#10004'.'(Assigned Documents To Another Courier)';
			else if ($order_stat == "Waiting_To_Be_Collected") return '&#10004'.'(Waiting To Be Collected)';
			else if ($order_stat == "Order_Missorted") return '&#10004'.'(Order Missorted)';
			else if ($order_stat == "Order_Miss_Delivery_Cycle") return '&#10004'.'(Order Miss Delivery Cycle)';
			else return '&#10004';
		}
	}

	public function validate_bulk_actions( $action, $order_ids ) {
		
		$orders_count 	= count( $order_ids );

		if( 'shipany_create_labels' === $action ){

			if ( $orders_count < 1 ) {

				return __( 'No orders selected for bulk action, please select orders before performing the action.', 'shipany' );

			}

		}elseif( 'shipany_closeout_selected' === $action ){

			if ( $orders_count < 1 ) {

				return __( 'No orders selected for bulk action, please select orders before performing the action.', 'shipany' );

			}else{

				// Ensure the selected orders have a label created, otherwise don't create handover
				foreach ( $order_ids as $order_id ) {
					$label_tracking_info = $this->get_shipany_label_tracking( $order_id );
					if( empty( $label_tracking_info ) ) {
						return __( 'One or more orders do not have a label created, please ensure all labels are created for each order before creating a handoff document.', 'shipany' );
					}
				}

			}
		}

		return '';
	}

	public function process_bulk_actions( $action, $order_ids, $orders_count, $shipany_force_product = false, $is_force_product_dom = false ) {

		$array_messages = array();
		
		$action_arr = explode(':', $action);
		if ( ! empty( $action_arr ) ) {
			$action = $action_arr[0];

			if ( isset( $action_arr[1] ) && ($action_arr[1] == 'dom') ) {
				$is_force_product_dom = true;
			} else {
				$is_force_product_dom = false;
			}

			if ( isset( $action_arr[2] ) ) {
				$shipany_force_product = $action_arr[2];
			}
		}

		$array_messages += parent::process_bulk_actions( $action, $order_ids, $orders_count, $shipany_force_product, $is_force_product_dom );

		if( 'shipany_closeout_all' === $action ){

			$instance = SHIPANY()->get_shipany_factory();

			try {
				$closeout 	= $instance->close_out_shipment();

				if( !isset( $closeout['handover_id'] ) ){
					throw new Exception( __( 'Cannot get Handover ID!', 'shipany' ) );
				}
				
				$message 	= '';

				if( isset( $closeout['message'] ) ){
					$message .= $closeout['message'];
				}

				if( isset( $closeout['handover_id']) ){
					$message .= ( !empty( $message ) )? ' - ' : '';
					$message .= 'Handover ID: ' . $closeout['handover_id'];
				}

				array_push(
					$array_messages,
					array(
						'message' => $message,
						'type'    => 'success',
					)
				);
				
			} catch (Exception $exception) {
				array_push(
					$array_messages,
					array(
						'message' => $exception->getMessage(),
						'type'    => 'error',
					)
				);
			}

		}elseif( 'shipany_closeout_selected' === $action ){

			$instance = SHIPANY()->get_shipany_factory();

			$shipment_ids = array();

			try {

				foreach ( $order_ids as $order_id ) {

					if( !$this->is_crossborder_shipment( $order_id ) ){
						
						throw new Exception( __( 'Local shipment found! Please pick international shipment only.', 'shipany' ) );
					}
					$label_tracking_info 	= $this->get_shipany_label_tracking( $order_id );
					$shipment_ids[] 		= $label_tracking_info['shipment_id'];
				}

				$closeout 	= $instance->close_out_shipment( $shipment_ids );

				if( !isset( $closeout['handover_id'] ) ){
					throw new Exception( __( 'Cannot get Handover ID!', 'shipany' ) );
				}

				if( isset( $closeout['file_info']->url ) ){

					foreach( $order_ids as $order_id ){
						// Add post meta to identify if added to handover or not
						// update_post_meta( $order_id, '_pr_shipment_shipany_handover_note', 1 );
						$order = wc_get_order( $order_id );
						$order->update_meta_data( '_pr_shipment_shipany_handover_note', 1 );
						$order->save();
					}
					
					$label_url 			= $this->generate_download_url( '/' . self::SHIPANY_DOWNLOAD_CLOSE_OUT_ENDPOINT . '/' . $closeout['handover_id'] );

					$manifest_text 	= sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$label_url,
						__('Download Closeout File', 'shipany') . ' ' . $closeout['handover_id']
					);

				}else{
					$manifest_text = __('Handover ID : ', 'shipany' ) . $closeout['handover_id'];
				}

				$message = sprintf(
					__( 'Finalized Close Out - %2$s', 'shipany' ),
					$closeout['handover_id'],
					$manifest_text
				);

				array_push(
					$array_messages,
					array(
						'message' => $message,
						'type'    => 'success',
					)
				);
				
			} catch (Exception $exception) {
				array_push(
					$array_messages,
					array(
						'message' => $exception->getMessage(),
						'type'    => 'error',
					)
				);
			}

		}elseif ( 'shipany_handover' === $action ) {
			$redirect_url  = admin_url( 'edit.php?post_type=shop_order' );
			$order_ids_hash = md5( json_encode( $order_ids ) );
			// Save the order IDs in a option.
			// Initially we were using a transient, but this seemed to cause issues
			// on some hosts (mainly GoDaddy) that had difficulty in implementing a
			// proper object cache override.
			update_option( 'shipany_handover_order_ids_' . $order_ids_hash, $order_ids );

			$action_url = wp_nonce_url(
				add_query_arg(
					array(
						'shipany_action'   => 'print',
						'order_id'        => $order_ids[0],
						'order_ids'       => $order_ids_hash,
					),
					'' !== $redirect_url ? $redirect_url : admin_url()
				),
				'shipany_handover'
			);

			$print_link = '<a href="' . $action_url .'" target="_blank">' . __( 'Print SHIPANY handover.', 'shipany' ) . '</a>';

			$message = sprintf( __( 'SHIPANY handover for %1$s order(s) created. %2$s', 'shipany' ), $orders_count, $print_link );

			array_push($array_messages, array(
                'message' => $message,
                'type' => 'success',
            ));
		}

		return $array_messages;
	}

	public function process_download_close_out() {
		global $wp_query;
		
		$shipany_close_out_id = isset($wp_query->query_vars[ self::SHIPANY_DOWNLOAD_CLOSE_OUT_ENDPOINT ] )
			? $wp_query->query_vars[ self::SHIPANY_DOWNLOAD_CLOSE_OUT_ENDPOINT ]
			: null;
			
		// If the endpoint param ( order ID) is not in the query, we bail
		if ( $shipany_close_out_id === null ) {
			return;
		}

		$instance 	= SHIPANY()->get_shipany_factory();
		$label_path = $instance->get_shipany_close_out_label_file_info( $shipany_close_out_id )->path;
		
		$array_messages = get_option( '_shipany_bulk_action_confirmation' );
		if ( empty( $array_messages ) || !is_array( $array_messages ) ) {
			$array_messages = array( 'msg_user_id' => get_current_user_id() );
		}

		if ( false == $this->download_label( $label_path ) ) {
			array_push($array_messages, array(
				'message' => __( 'Unable to download file. Label appears to be invalid or is missing. Please try again.', 'shipany' ),
				'type' => 'error'
			));
		}

		update_option( '_shipany_bulk_action_confirmation', $array_messages );

		$redirect_url = isset($wp_query->query_vars[ 'referer' ])
			? $wp_query->query_vars[ 'referer' ]
			: admin_url('edit.php?post_type=shop_order');

		// If there are errors redirect to the shop_orders and display error
		if ( $this->has_error_message( $array_messages ) ) {
            wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), $redirect_url ) );
			exit;
		}
	}

	public function print_document( $template_args ) {
		remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
	}

	// Add filters for created (or not) labels
	public function filter_orders_by_label_created() {
		global $typenow;

		if ( 'shop_order' === $typenow ) :

			$options  = array(
				'shipany_label_not_created'    => __( 'ShipAny Order Not Created', 'shipany' ),
				'shipany_label_created'        => __( 'ShipAny Order Created', 'shipany' ),
			);

			$selected = isset($_GET['_shop_order_shipany_label_created']) ? sanitize_text_field($_GET['_shop_order_shipany_label_created']) : '';

			?>
			<select name="_shop_order_shipany_label_created" id="dropdown_shop_order_shipany_label_created">
				<option value=""><?php esc_html_e( 'Show all ShipAny statuses', 'shipany' ); ?></option>
				<?php foreach ( $options as $option_value => $option_name ) : ?>
					<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $selected, $option_value ); ?>><?php echo esc_html( $option_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php

		endif;
	}

	// Filter orders by created labels
	public function filter_orders_by_label_created_query( $vars ) {
		global $typenow;

		if ( 'shop_order' === $typenow && isset($_GET['_shop_order_shipany_label_created']) ) {

			$meta    = '';
			$compare = '';
			$value   = '';

			switch ( sanitize_text_field($_GET['_shop_order_shipany_label_created']) ) {
				case 'shipany_label_not_created' :
					$meta    = '_pr_shipment_shipany_label_tracking';
					$compare = 'NOT EXISTS';
				break;
				case 'shipany_label_created' :
					$meta    = '_pr_shipment_shipany_label_tracking';
					$compare = '>';
					$value   = '0';
				break;
			}

			if ( $meta && $compare ) {
				$vars['meta_key']     = $meta;
				$vars['meta_value']   = $value;
				$vars['meta_compare'] = $compare;
			}
		}

		return $vars;
	}

}

endif;
