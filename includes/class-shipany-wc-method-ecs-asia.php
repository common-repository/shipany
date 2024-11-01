<?php
use Utils\ShipanyHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// // Create hidden checkout field type
// add_filter( 'woocommerce_form_field_hidden', 'create_checkout_hidden_field_type', 5, 4 );
// function create_checkout_hidden_field_type( $field, $key, $args, $value ){
//     return '<input type="hidden" name="'.esc_attr($key).'" id="'.esc_attr($args['id']).'" value="'.esc_attr($args['default']).'" />';
// }

/**
 * Shipping Method.
 */

if ( ! class_exists( 'SHIPANY_WC_Method_eCS_Asia' ) ) :

class SHIPANY_WC_Method_eCS_Asia extends WC_Shipping_Method {
	private $supported_storage_types_courier = array();

	private $storage_types_trans = array();

	private $js_injection = '';

	/**
	 * Init and hook in the integration.
	 */
	public function __construct( $instance_id = 0 ) {
		// add_action( 'init', array( $this, 'init' ), 0 );
		$this->id = 'shipany_ecs_asia';
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'ShipAny', 'shipany' ); #shipany
		$this->init();
	}

	private function check_used_local_pickup() {
		$zone_ids = array_keys( array('') + WC_Shipping_Zones::get_zones() );
		foreach ($zone_ids as $zone_id) {
			$zone = new WC_Shipping_Zone($zone_id);
			$shipping_methods = $zone->get_shipping_methods(true, 'values');
			foreach ($shipping_methods as $shipping_method) {
				if ($shipping_method->id == 'local_pickup') {
					return true;
				}
			}
		}
		return false;
	}

	public function service_location_checking_notice() {
		if (get_option('woocommerce_shipany_is_contain_location_list') === 'true' && $this->check_used_local_pickup()) {
			ShipanyHelper::get_courier_service_point_locations();
			if(get_option('woocommerce_shipany_service_locations_forbidden', false)){
				?>
					<div class="notice notice-error is-dismissible">
						<p>
							<h4 style="margin: 0;">
								<?php echo __('Please note that the Locker/Store List feature is not enabled in your ShipAny account.', 'shipany'); ?>
							</h4>
						</p>
					</div>
				<?php
			}
		}
	}

	/**
	 * init function.
	 */
	private function init() {
		global $pagenow;
		if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'shipping' && isset($_GET['section']) && $_GET['section'] === 'shipany_ecs_asia') {
			$this->service_location_checking_notice();
		}
		// Load the settings.
		$this->storage_types_trans = array(
			'Air Conditioned (17°C to 22°C)' => __('Air Conditioned (17°C to 22°C)', 'shipany'),
			'Chilled (0°C to 4°C)' => __('Chilled (0°C to 4°C)', 'shipany'),
			'Frozen (-18°C to -15°C)' => __('Frozen (-18°C to -15°C)', 'shipany'),
			'Air Conditioned' => __('Air Conditioned (17°C to 22°C)', 'shipany'),
			'Chilled' => __('Chilled (0°C to 4°C)', 'shipany'),
			'Frozen' => __('Frozen (-18°C to -15°C)', 'shipany'),
			'Normal' => __('Normal', 'shipany'),
			'Document' => __('Document', 'shipany')
		);
		$this->init_settings();
		$this->init_form_fields();
		// $this->init_settings();
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		// add_action( 'woocommerce_after_settings_shipping', array( $this, 'after_load_shipping_page'));
		if (isset($_REQUEST["page"]) && $_REQUEST["page"] =='wc-settings') {
			wp_enqueue_script( 'wc-shipany-setting-js', SHIPANY_PLUGIN_DIR_URL . '/assets/js/shipany-setting.js', array('jquery'), SHIPANY_VERSION );
			wp_localize_script( 'wc-shipany-setting-js', 'shipany_setting_val', $this->get_params_to_rest() + array(
				'courier_show_paid_by_rec' => (isset($this->settings["courier_show_paid_by_rec"]) && $this->settings["courier_show_paid_by_rec"]) ? $this->settings["courier_show_paid_by_rec"] : array(),
				'courier_show_self_drop_off' => (isset($this->settings["courier_show_self_drop_off"]) && $this->settings["courier_show_self_drop_off"]) ? $this->settings["courier_show_self_drop_off"] : array()
			) + array('store_url' => home_url()) );
			wp_localize_script( 'wc-shipany-setting-js', 'trans', array_merge(array(
				'Register now' => __('Register now', 'shipany'),
				'Grant Permission' => __('Grant Permission', 'shipany')
			), $this->storage_types_trans));
			wp_localize_script( 'wc-shipany-setting-js', 'supported_storage_types_courier', $this->supported_storage_types_courier);
			wp_enqueue_script( 'wc-shipany-setting-js-md5', SHIPANY_PLUGIN_DIR_URL . '/assets/js/md5.js', array('jquery'), SHIPANY_VERSION );
			wp_localize_script( 'wc-shipany-setting-js-md5', 'shipany_setting_val2', array() );
		}
		// wp_enqueue_script( 'wc-shipany-setting-js', SHIPANY_PLUGIN_DIR_URL . '/assets/js/TestConnection.js', array('jquery'), SHIPANY_VERSION );
		// wp_localize_script( 'wc-shipany-setting-js', 'shipany_label_data', $dump );
        wp_enqueue_script( 'wc-dialog-js', SHIPANY_PLUGIN_DIR_URL . '/assets/js/dialog.js', array('jquery'), SHIPANY_VERSION );
		wp_enqueue_script( 'wc-dialog-sendpickup-js', SHIPANY_PLUGIN_DIR_URL . '/assets/js/dialog-sendpickup.js', array('jquery'), SHIPANY_VERSION );
    }

	private function get_params_to_rest() {
		//TODO: Add parmams for shipping area
		$has_token = false;
		$merchant_info = '';
		if (isset($this->settings["shipany_api_key"]) && $this->settings["shipany_api_key"] !=''){
			$api_tk = $this->settings["shipany_api_key"];

			$result = ShipanyHelper::getApiUrlAndRealTk('api', $api_tk, $this->settings['shipany_region']);
			$temp_api_endpoint = $result['url'];
			$api_tk = $result['api-tk'];

			$merchant_resp = wp_remote_get($temp_api_endpoint.'merchants/self/', array(
				'headers' => array(
					'api-tk'=> $api_tk,
					'order-from' => 'portal'
				)
			));
			if (wp_remote_retrieve_response_code($merchant_resp) == 200) {
				// $merchant_info = json_decode($merchant_resp['body'])->data->objects[0];
				$merchant_info = $merchant_resp['body'];
				$merchant_info_decode = json_decode($merchant_info);
				if (json_decode($merchant_info)->data->objects[0]->asn_mode == "Disable") {
					update_option('shipany_has_asn', false);
				} else {
					update_option('shipany_has_asn', true);
				}
				$stores = json_decode($merchant_info)->data->objects[0]->configs->stores;
				foreach($stores as $store) {
					if ($store->pltf =='woocommerce'){
						if ($store->token !='') {
							$has_token = true;
							break;
						}
					}
				}
			}
		}
		
		$rv = array();
		$rv['ajax_url'] = admin_url('admin-ajax.php');
		$rv['rest_url'] = get_rest_url();
		if ($merchant_info != '') {
			$rv['mch_uid'] = json_decode($merchant_info)->data->objects[0]->uid;
		} else {
			$rv['mch_uid'] = '';
		}
		$rv['shipany_api_key'] = isset($this->settings["shipany_api_key"])? $this->settings["shipany_api_key"] : '';
		$rv['has_token'] = $has_token;
		return $rv;
	}

	/**
	 * Get message
	 * @return string Error
	 */
	private function get_message( $message, $type = 'notice notice-error is-dismissible' ) {

		ob_start();
		?>
		<div class="<?php echo esc_attr($type) ?>">
			<p><?php echo esc_attr($message) ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$setting = get_option('woocommerce_shipany_ecs_asia_settings');
		if(!$setting){ // newly install
			$setting['show_courier_tracking_number_enable'] = 'no';
			update_option('woocommerce_shipany_ecs_asia_settings', $setting);
		} else if (!isset($setting['show_courier_tracking_number'])){
			// backward compatibility for old version
			$setting['show_courier_tracking_number_enable'] = 'yes';
			update_option('woocommerce_shipany_ecs_asia_settings', $setting);
		}
		$log_path = SHIPANY()->get_log_url();
		// header("Location: http://localhost/appcider/wc-auth/v1/authorize?app_name=My+App+Name&scope=write&user_id=1&return_url=http%3A%2F%2Fgoogle.com&callback_url=https%3A%2F%2Fwebhook.site%2F510a1c48-01d8-465a-b921-5d253c6572ea");
		try {
			$select_shipany_courier_int = '<empty>';
			$lalamove_addons_name_key_pair = array();
			if (isset($_GET["section"])){
				if ($_GET["section"] == "shipany_ecs_asia"){
					$select_shipany_courier_int = ShipanyHelper::get_couriers();
					if($select_shipany_courier_int === false){ // if return false, means token is invalid
						// rewrite select_shipany_courier_int to empty array prevent foreach error
						$select_shipany_courier_int = [];
						if (!function_exists(('ti_custom_javascript'))){
							function ti_custom_javascript() {
								?>
									<script>jQuery(function ($) {
										if (typeof input_woocommerce_shipany_ecs_asia_shipany_api_key !== "undefined") {
											input_woocommerce_shipany_ecs_asia_shipany_api_key.setAppendIcon('cross')
										}
									});
									</script>
								<?php
							}
						}
					} else {
						if (!function_exists(('ti_custom_javascript'))){
							function ti_custom_javascript() {
								?>
									<script>jQuery(function ($) {
										if (typeof input_woocommerce_shipany_ecs_asia_shipany_api_key !== "undefined") {
											input_woocommerce_shipany_ecs_asia_shipany_api_key.setAppendIcon('tick')
										}
									});
									</script>
								<?php
							}
						}
					}
					add_action('admin_footer', 'ti_custom_javascript');

					$courier_uid_name_key_pair = array();
					$lalamove_addons = '';
					
					$courier_show_paid_by_rec = array();
					$courier_show_self_drop_off = array();
					foreach ($select_shipany_courier_int as $key => $value){
						$courier_uid_name_key_pair[$value->uid] = __($value->name, 'shipany');
						if ($value->name == 'Lalamove') {
							$lalamove_addons = $value->cour_svc_plans;
						}
						$this->supported_storage_types_courier[$value->uid] = $value->cour_props->delivery_services->supported_storage_types;
						if ($value->cour_props->delivery_services->paid_by_rcvr) {
							array_push($courier_show_paid_by_rec,$value->uid);
						}
						if ($value->cour_props->pickup_services->self_drop_off) {
							array_push($courier_show_self_drop_off,$value->uid);
						}
					}
					$select_shipany_courier_int = $courier_uid_name_key_pair;
					if ($lalamove_addons !=''){
						foreach ($lalamove_addons as $key => $value) {
							foreach($lalamove_addons[$key]->{'add-ons'}->additional_services as $_key => $_value){
								$lalamove_addons[$key]->{'add-ons'}->additional_services[$_key]->descr = __($lalamove_addons[$key]->{'add-ons'}->additional_services[$_key]->descr, 'shipany');
							}
							$lalamove_addons_name_key_pair[$value->cour_svc_pl] = __($value->cour_svc_pl, 'shipany');
						}
					}
					$this->settings['courier_show_paid_by_rec'] = $courier_show_paid_by_rec;
					$this->settings['courier_show_self_drop_off'] = $courier_show_self_drop_off;
				}
			}


		} catch (Exception $e) {
			SHIPANY()->log_msg( __('Products not displaying - ', 'shipany') . $e->getMessage() );
		}
		
		$weight_units = get_option( 'woocommerce_weight_unit' );

		$set_default_storage_type_options = array();
		if(isset($this->supported_storage_types_courier[$this->settings["shipany_default_courier"]])){
			foreach ($this->supported_storage_types_courier[$this->settings["shipany_default_courier"]] as $key => $value) {
				$set_default_storage_type_options[$value] = $this->storage_types_trans[$value];
			}
		}
		if(!$set_default_storage_type_options && $this->settings["shipany_default_courier"]){
			$set_default_storage_type_options = [
				'' => __('Normal', 'shipany')
			];
		}

		$regions = ShipanyHelper::getRegions();
		foreach ( $regions as $key => $region ) {
			$translated_region = __( $region, 'shipany' );
			$regions[ $key ] = $translated_region;
		}
		$this->form_fields = array(
			'shipany_api'           => array(
				'title'           => __( 'API Settings', 'shipany' ),
				'type'            => 'title',
				'description'     => __( 'You need to have a ShipAny account before you can complete the setup. No account? ', 'shipany' ),
				'class'			  => 'shipany-register-descr',
			),
			'shipany_region' => array(
				'title'             => __( 'Region', 'shipany' ),
				'type'              => 'select',
				'description'       => __( 'Please select region.', 'shipany' ),
				'desc_tip'          => true,
				'options'           => $regions,
				'class'				=> 'wc-enhanced-select shipany-region',
				'custom_attributes' => array( 'required' => 'required' )
			),
			'shipany_api_key' => array(
				'title'             => __( 'API Token', 'shipany' ),
				'type'              => 'text',
				'description'       => __( 'You can find your API token in Account page after logging in ShipAny portal.', 'shipany' ),
				'desc_tip'          => true,
				'custom_attributes' => array( 'required' => 'required' ),
				'default'           => '',
			),
			// 'shipany_test_connection_button' => array(
			// 	'title'             => __( 'Test Connection', 'shipany' ),
			// 	'type'              => 'button',
			// 	'custom_attributes' => array(
			// 		'onclick' => "shipanyTestConnection('woocommerce_shipany_ecs_asia_shipany_test_connection_button');"
			// 	),
			// 	'description'       => __( 'Click the button for testing the connection.', 'shipany' ),
			// 	'desc_tip'          => true,
			// ),
			'shipany_default_courier' => array(
				'title'             => __( 'Default Courier', 'shipany' ),
				'type'              => 'select',
				'description'       => __( 'Please select the default courier. You should find here a list of couriers available for your account after you input the API token above and save.', 'shipany' ),
				'desc_tip'          => true,
				'options'           => $select_shipany_courier_int,
				'class'				=> 'wc-enhanced-select default-courier-selector',
				'custom_attributes' => array( 'required' => 'required' )
			),
			'set_default_storage_type' => array(
				'title'             => __( 'Default Temperature Type', 'shipany' ),
				'type'              => 'select',
				'description'       => __( 'Please select the default temperature type (if you are not using a cold chain service as your default courier, please leave it as "Normal").', 'shipany' ),
				'desc_tip'          => true,
				'options'  => $set_default_storage_type_options,
				'class'				=> 'wc-enhanced-select default-storage-type',
			),
			'set_default_create' => array(
				'title' => __('Auto-create Shipment Order', 'shipany'),
				'type' => 'checkbox',
				'label' => __(' ', 'shipany'),
				'default' => 'no',
				'description' => __('Enable this if you want to create a ShipAny Order automatically as soon as a WooCommerce order is created', 'shipany'),
				'desc_tip' => true,
			),
			'set_default_create_order_status' => array(
				'title' => __('Order Status triggering Shipment Order Auto-creation', 'shipany'),
				'type' => 'select',
				'description' => __('Please select the order status for auto-creation.', 'shipany'),
				'desc_tip' => true,
				'options' => array(
					'' => __('ANY', 'shipany')
				) + wc_get_order_statuses(),
				'class' => 'wc-enhanced-select default-create-order-status',
			),
			"shipany_send_product_attrs_to_shipany" => array(
				'title'             => __( 'Use Product Variants as Item Description', 'shipany' ),
				'type'              => 'checkbox',
				'label'             => __( ' ', 'shipany' ),
				'default'           => 'no',
				'description'       => __( 'Please tick here if you want to use Product Variants (e.g. color, size… etc.) as Item Description when orders are created onto ShipAny. This is particularly useful if your operation team rely on such info in ShipAny packing slip.', 'shipany' ),
				'desc_tip'          => true,
			),
			'shipany_tracking_note_enable' => array(
				'title'             => __( 'Write tracking info to Order Notes', 'shipany' ),
				'type'              => 'checkbox',
				'label'             => __( ' ', 'shipany' ),
				'default'           => 'yes',
				'description'       => __( 'Enable this if you want to add shipment tracking info to WooCommerce order notes notes', 'shipany' ),
				'desc_tip'          => true,
			),
			'shipany_tracking_note_txt' => array(
				'title'             => __( 'Tracking Info Description Text', 'shipany' ),
				'type'            	=> 'text',
				'default'  			=> 'ShipAny Tracking Number:',
				'placeholder'		=> __( 'Tracking note prefix in order details page', 'shipany' ),
				'label'             => __( ' ', 'shipany' ),
				'description'       => __( 'Customize tracking note description text in the order details page.', 'shipany' ),
				'desc_tip'          => true,
				'class'				=> 'shipany_tracking_note'
			),
			'show_courier_tracking_number_enable' => array(
				'title'             => __( 'Show Courier Tracking Number', 'shipany' ),
				'type'              => 'checkbox',
				'label'             => __( ' ', 'shipany' ),
				'default'           => 'no',
				'description'       => __( 'Enable this if you want to show courier tracking number in the order details page', 'shipany' ),
				'desc_tip'          => true,
			),
			'shipany_customize_order_id' => array(
				'title'             => __( 'ShipAny Shipment Order Ref Suffix', 'shipany' ),
				'type'            	=> 'text',
				'description'       => __( 'Specify a string to be appended to WooCommerce Order ID to form the Order Ref when an order is created onto ShipAny. This is particularly useful if you have multiple WooCommerce stores connected.', 'shipany' ),
				'desc_tip'          => true
			),	
			'merchant_info' => array(
				'type'              => 'hidden',
				'default'           => '',
			),
		);

		// append locker setting field v2
		$insert_locker_setting1 = array(
			'title'             => __( 'Enable Locker/Store List', 'shipany' ),
			'type'            	=> 'text',
			'label'             => __( ' ', 'shipany' ),
			'description'       => __( 'Follow this instruction to enable locker/store list on your store', 'shipany' ),
			'desc_tip'          => true,
			'class'			  	=> 'shipany-enable-locker'
		);
		$insert_locker_setting2 = array(
			'title'             => __( 'Locker/Store List Display Name', 'shipany' ),
			'type'            	=> 'text',
			'default'  			=> 'Pick up at locker/store',
			'placeholder'		=> __( 'Display name in checkout page', 'shipany' ),
			'label'             => __( ' ', 'shipany' ),
			'description'       => __( 'Customize shipping method display name in checkout page.', 'shipany' ),
			'desc_tip'          => true,
			'class'			  	=> 'shipany-enable-locker-2'
		);
		$insert_locker_setting2_1 = array(
			'title'             => __( 'Locker/Store List Change Address Button Display Name', 'shipany' ),
			'type'            	=> 'text',
			'default'  			=> 'Change address',
			'placeholder'		=> __( 'Change Address Button Display Name in checkout page', 'shipany' ),
			'label'             => __( ' ', 'shipany' ),
			'description'       => __( 'Customize the display name of Change Address button in checkout page.', 'shipany' ),
			'desc_tip'          => true,
			'class'			  	=> 'shipany-enable-locker-2'
		);
		$insert_locker_setting3 = array(
			'title'             => __( 'Write Locker/Store Address Only To Shipping Address', 'shipany' ),
			'type'              => 'checkbox',
			'label'             => __( ' ', 'shipany' ),
			'default'           => 'no',
			'description'       => __( 'By default, the selected Locker/Store address will be written to both the shipping address and billing address during checkout. Enable this if you want it to be only written to shipping address.', 'shipany' ),
			'desc_tip'          => true,
		);
		$force_show_shipping_address_in_email_for_local_pickup = array(
			'title'             => __('Show Shipping Address in Local Pickup Order Notification Emails', 'shipany'),
			'type'              => 'checkbox',
			'label'             => __(' ', 'shipany'),
			'default'           => 'no',
			'description'       => __('Enable this if you want to show Shipping Address in Local Pickup order notification emails.', 'shipany'),
			'desc_tip'          => true,
		);
		$insert_locker_setting4 = array(
			'title'             => __( 'Locker/Store List Minimum Checkout Amount for Free Shipping', 'shipany' ),
			'type'            	=> 'text',
			'placeholder'		=> __( 'Minimum checkout amount for free shipping', 'shipany' ),
			'label'             => __( ' ', 'shipany' ),
			'description'       => __( 'If specified, the shipping fee will be 0 as long as the checkout value reaches this amount.<br><br>Otherwise, the shipping fee follows the Cost defined in Local pickup settings.', 'shipany' ),
			'desc_tip'          => true,
			'class'			  	=> 'shipany_tracking_note'
		);
		$insert_locker_setting5 = array(
			'title'             => __( 'Locker/Store List covers Macau', 'shipany' ),
			'type'              => 'checkbox',
			'label'             => __( ' ', 'shipany' ),
			'default'           => 'yes',
			'description'       => __( 'Enable this if you want the Locker/Store List to cover Macau.', 'shipany' ),
			'desc_tip'          => true,
		);
		if($setting['shipany_region'] != 0){
			if(!function_exists(('hide_macau_javascript'))){
				function hide_macau_javascript() {
					?>
						<script>jQuery(function ($) {$('#woocommerce_shipany_ecs_asia_shipany_locker_include_macuo').parents('tr').hide()});</script>
					<?php
				}
			}
			add_action('admin_footer', 'hide_macau_javascript');
		}
		$insert_locker_setting6 = array(
			'title'             => __( 'Locker/Store Address Length Limit', 'shipany' ),
			'type'              => 'text',
			'label'             => __( ' ', 'shipany' ),
			'default'           => 0,
			'description'       => __( 'Specify the length limit (in number of characters) of Locker/Store addresses to be prefilled to checkout form. This may be necessary if there are other plugins consuming the checkout form but requiring a specific address length limit.', 'shipany' ),
			'desc_tip'          => true,
		);
		$get_token = array(
			'title'             => __( 'Active Notification', 'shipany' ),
			'type'            	=> 'button',
			'label'             => __( ' ', 'shipany' ),
			'description'       => __( 'Grant permission to ShipAny for notifying WooCommerce the shipment order statuses and tracking numbers.', 'shipany' ),
			'desc_tip'          => true
		);
		// $update_address = array(
		// 	'title'             => __( 'Refresh Sender Address', 'shipany' ),
		// 	'type'              => 'button',
		// 	'description'       => __( 'Press the button to refresh the sender address. You might need to do this after updating Pickup Address Settings in ShipAny Portal.', 'shipany' ),
		// 	'desc_tip'          => true,
		// 	'class'				=> 'button-secondary update-address',
		// );
		$default_weight = array(
			'title'             => __( 'Always overwrite shipment order weight to 1kg', 'shipany' ),
			'type'              => 'checkbox',
			'label'             => __( ' ', 'shipany' ),
			'default'           => 'no',
			'description'       => __( 'By default, the shipment order weight is calculated based on the product weight. Please tick here if you want to always overwrite shipment order weight to 1kg. The shipment will be charged based on logistics courier final measurement.', 'shipany' ),
			'desc_tip'          => true
		);
		$default_courier_additional_service = array(
			'title'             => __( 'Default Courier Additional Service', 'shipany' ),
			'type'              => 'select',
			'description'       => __( 'Please select the default courier additional service.', 'shipany' ),
			'desc_tip'          => true,
			'options'           => $lalamove_addons_name_key_pair,
			'class'				=> 'wc-enhanced-select'
		);
		$paid_by_rec = array(
			'title'             => __( 'Paid by Receiver', 'shipany' ),
			'type'              => 'checkbox',
			'label'             => __( ' ', 'shipany' ),
			'default'           => 'no',
			'description'       => __( 'Please tick here if you want the shipping fee to be paid by the receiver.', 'shipany' ),
			'desc_tip'          => true
		);
		$self_drop_off = array(
			'title'             => __( 'Self Drop-off', 'shipany' ),
			'type'              => 'checkbox',
			'label'             => __( ' ', 'shipany' ),
			'default'           => 'no',
			'desc_tip'          => true
		);

		$this->form_fields = array_slice($this->form_fields, 0, 7, true) + array(
			"shipany_default_courier_additional_service" => $default_courier_additional_service,
			"shipany_paid_by_rec" => $paid_by_rec,
			"shipany_self_drop_off" => $self_drop_off,
			"shipany_rest_token" => $get_token,
			"shipany_enable_locker_list" => $insert_locker_setting1,
			"shipany_enable_locker_list2" => $insert_locker_setting2,
			"shipany_enable_locker_list2_1" => $insert_locker_setting2_1,
			"shipany_bypass_billing_address" => $insert_locker_setting3,
			"shipany_force_show_shipping_address_in_email_for_local_pickup" => $force_show_shipping_address_in_email_for_local_pickup,
			"shipany_locker_free_cost" => $insert_locker_setting4,
			"shipany_locker_include_macuo" => $insert_locker_setting5,
			"shipany_locker_length_truncate" => $insert_locker_setting6,
			// 'shipany_update_address' => $update_address,
			'default_weight' => $default_weight
		) + array_slice($this->form_fields, 7, count($this->form_fields) - 1, true) + array( // real common settings
			'shipany_everyday_force_update_time' => array(
				'type'              => 'hidden',
				'default'           => '06:00:00',
			),
		) ;

	
		if (isset($this->settings["shipany_api_key"]) && !in_array(md5($this->settings["shipany_api_key"]), array('8241d0678fb9abe65a77fe6d69f7063c', '7df5eeebe4116acfefa81a7a7c3f12ed'))) {
			$update_temp = get_option('woocommerce_shipany_ecs_asia_settings');
			$update_temp['default_weight'] = 'no';
			update_option('woocommerce_shipany_ecs_asia_settings', $update_temp);
		}

		// if not lalamove, remove the db value shipany_default_courier_additional_service
		if (isset($this->settings["shipany_default_courier"]) && !in_array($this->settings["shipany_default_courier"],$GLOBALS['COURIER_LALAMOVE'])) {
			$update_temp = get_option('woocommerce_shipany_ecs_asia_settings');
			$update_temp['shipany_default_courier_additional_service'] = '';
			update_option('woocommerce_shipany_ecs_asia_settings', $update_temp);
		}
		// get rid of the shipany_tracking_note_txt field if merchant asn_mode is enabled
		// if (json_decode($this->settings["merchant_info"])->data->objects[0]->asn_mode != "Disable" || get_option('shipany_has_asn')) {
		// 	$this->form_fields = array_slice($this->form_fields, 0, 9, true) + array_slice($this->form_fields, 10, true);
		// }
	}

	/**
	 * Generate Button HTML.
	 *
	 * @access public
	 * @param mixed $key
	 * @param mixed $data
	 * @since 1.0.0
	 * @return string
	 */
	public function generate_button_html( $key, $data ) {
		$field    = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<button type="button" class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 */
	public function process_admin_options() {
		$old_settings = json_decode(json_encode($this->settings), true); // backup old settings
		$saved = parent::process_admin_options();
		ShipanyHelper::reload();
		try {
			$shipany_obj = SHIPANY()->get_shipany_factory();
			$shipany_obj->shipany_reset_connection();
			if(!$this->settings['shipany_api_key']){
				$this->update_option('shipany_default_courier', '');
				$this->update_option('set_default_storage_type', '');
				// save as option, no settings
				update_option('woocommerce_shipany_is_contain_location_list', 'false');
			} else if(
				(!$this->settings['shipany_default_courier'] || $old_settings['shipany_default_courier'] !== $this->settings['shipany_default_courier']) || 
				$old_settings['shipany_self_drop_off'] !== $this->settings['shipany_self_drop_off'] || 
				$old_settings['shipany_paid_by_rec'] !== $this->settings['shipany_paid_by_rec']
			){
				$new_cour_uid = $this->settings['shipany_default_courier'];
				$couriers = ShipanyHelper::get_couriers();
				$courier = null;

				foreach ($couriers as $value) {
					if ($value->uid == $new_cour_uid) {
						$courier = $value;
						break;
					}
				}

				if(!$courier) {
					foreach ($couriers as $value) {
						if ($value->uid == $old_settings['shipany_default_courier']) {
							$courier = $value;
							break;
						}
					}

					if(!$courier) {
						$courier = $couriers[0];
					}
				}

				// update Option(woocommerce_shipany_is_contain_location_list)  
				$this->update_option('shipany_default_courier', $courier->uid);
				// save as option, no settings
				update_option('woocommerce_shipany_is_contain_location_list', $courier->cour_props->delivery_services->supported_service_location_types ? 'true' : 'false');

				if(
					// $this->settings['set_default_storage_type'] &&
					!in_array($this->settings['set_default_storage_type'], $courier->cour_props->delivery_services->supported_storage_types
				)){
					$this->update_option('set_default_storage_type', $courier->cour_props->delivery_services->supported_storage_types[0]);
				}

				if(!$courier->cour_props->pickup_services->collect_from_door && $courier->cour_props->pickup_services->self_drop_off){
					$this->update_option('shipany_self_drop_off', 'yes');
				} else if(!$courier->cour_props->pickup_services->self_drop_off && $courier->cour_props->pickup_services->collect_from_door){
					$this->update_option('shipany_self_drop_off', 'no');
				}

				if(!$courier->cour_props->delivery_services->paid_by_rcvr){
					$this->update_option('shipany_paid_by_rec', 'no');
				}
			}
		} catch (Exception $e) {
			echo $this->get_message( __('Could not reset connection: ', 'shipany') . $e->getMessage() );
			// throw $e;
		}

		$api_tk = $this->settings['shipany_api_key'];
		$region_id = $this->settings['shipany_region'];
		$result = ShipanyHelper::getApiUrlAndRealTk('api', $api_tk, $region_id);
		$temp_api_endpoint = $result['url'];
		$api_tk = $result['api-tk'];

		$para = [
			"store" => [
				  "domain" => get_permalink( wc_get_page_id( 'shop' )),
				  "src_platform" => "woocommerce", 
				  "meta" => [
					 "store_id" => get_bloginfo( 'name' ),
					 "shop_display_name" => home_url()
				  ] 
			   ] 
		 ]; 
	
		// FIXME: TO BE REMOVE
		$merchant_resp = wp_remote_get($temp_api_endpoint.'merchants/self/', array(
			'headers' => array(
				'api-tk'=> $api_tk,
				'order-from' => 'portal'
			)
		));
		if (wp_remote_retrieve_response_code($merchant_resp) == 200) {
			// $merchant_info = json_decode($merchant_resp['body'])->data->objects[0];
			$merchant_info = $merchant_resp['body'];
			$_POST["woocommerce_shipany_ecs_asia_merchant_info"] = $merchant_info;
			if (json_decode($merchant_info)->data->objects[0]->asn_mode == "Disable") {
				update_option('shipany_has_asn', false);
			} else {
				update_option('shipany_has_asn', true);
			}
		}
		// connect store
		$shipany_obj = SHIPANY()->get_shipany_factory();
		$response = $shipany_obj->api_client->post_connect('ecommerce/connect/ ', $para,$api_tk, $temp_api_endpoint);
		$this->init_form_fields();
		return $saved;
	}
}

endif;
