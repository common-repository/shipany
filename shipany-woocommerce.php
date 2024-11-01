<?php
/**
 * @package ShipAny
 */
/*
Plugin Name: ShipAny
Plugin URI: http://wordpress.org/plugins/shipany
Description: ShipAny one-stop logistics platform interconnects WooCommerce to multiple logistics service providers (including SF Express, Kerry Express, SF Cold-Chain, Alfred Locker, Hongkong Post, SF Locker, Convenience Store, etc.) so merchants can enjoy full-set features of logistics automation which disrupt the manual logistics process and bring E-Commerce to new generation.
Version: 1.1.60
Author: ShipAny
Author URI: https://www.shipany.io
License: GPLv2 or later
Text Domain: shipany
Domain Path: /lang
*/

use Utils\ShipanyHelper;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});
/** @deprecated 1.1.42 */
add_action('woocommerce_after_shipping_rate', 'addClickAndCollectWidget', 10, 2);
add_filter('woocommerce_cart_shipping_method_full_label', 'rename_popup_local_pickup', 10, 2);
add_action('woocommerce_review_order_after_shipping', 'woocommerce_review_order_after_shipping', 10, 1);
add_action('woocommerce_cart_totals_after_shipping', 'woocommerce_review_order_after_shipping', 10, 1);
/** end @deprecated 1.1.42 */

add_action('activated_plugin', 'shipany_activation_redirect');
add_action('wp_enqueue_scripts', 'themeslug_enqueue_script');
add_filter('woocommerce_package_rates', 'hide_shipping_methods', 8, 1);
add_filter('woocommerce_package_rates', 'customizing_shipping_methods', 9, 2);
add_action('woocommerce_package_rates', 'shipany_blocks', 10, 1);

// add_filter('woocommerce_available_payment_gateways', function($available_gateways){
// 	if($available_gateways['cod']){
// 		unset($available_gateways['cod']);
// 	}
// 	return $available_gateways;
// });

// FIXME: For cart only, wanna find other way to handle this
// add_action('woocommerce_calculated_shipping', 'update_address', 10);

add_filter('woocommerce_order_needs_shipping_address', 'maybe_display_shipping_address', 10, 3);
add_filter('woocommerce_shipping_method_add_rate_args', 'modify_rate_args_for_local_pickup', 10, 2);
// add_filter('update_post_meta', 'update_post_metadata_hook', 10, 4);

add_action('rest_api_init', function () {
	register_rest_route('shipany/v1', '/get-latest-locker-list', array(
		'methods' => 'GET',
		'callback' => 'get_latest_locker_list_view',
		'permission_callback' => function () {
			return true;
		}
	));
	register_rest_route('shipany/v1', '/update-shipping-address', array(
		'methods' => 'POST',
		'callback' => 'update_shipping_address',
		'permission_callback' => function () {
			return true;
		}
	));
	register_rest_route('shipany/v1', '/version', array(
		'methods' => 'GET',
		'callback' => function () {
			header("Shipany-Plugin-Version: " . SHIPANY_VERSION);
			return new WP_Error(
				'rest_no_route',
				__('No route was found matching the URL and request method.'),
				array('status' => 404)
			);
		},
		'permission_callback' => function () {
			return true;
		}
	));
});

add_filter('perfmatters_rest_api_exceptions', function ($exceptions) {
	$exceptions[] = 'shipany';
	return $exceptions;
});

function get_latest_locker_list_view() {
	if (!isset($_REQUEST['k']) || !wp_verify_nonce($_REQUEST['k'], 'shipany_get_latest_locker_list')) {
		return new WP_Error(
			'rest_no_route',
			__('No route was found matching the URL and request method.'),
			array('status' => 404)
		);
	}
	$json = ShipanyHelper::get_latest_locker_list();
	header("Content-Type: application/json");
	if(ini_get('zlib.output_compression') ||
		ini_get('brotli.output_compression') ||
		ini_get('gz.output_compression') ||
		(function_exists('apache_getenv') && @apache_getenv("no-gzip")) ||
		(function_exists('apache_get_modules') && is_array(apache_get_modules()) && in_array('mod_deflate', apache_get_modules()))
	) {
		echo $json;
	} else {
		header('Content-Encoding: deflate');
		echo gzdeflate($json, 9, ZLIB_ENCODING_DEFLATE);
	}
	exit;
}

function maybe_display_shipping_address($needs_shipping, $hidden_shipping_methods, $order) {
	if (ShipanyHelper::get_settings('shipany_force_show_shipping_address_in_email_for_local_pickup') == 'yes') {
		$locker = ShipanyHelper::get_latest_locker_list();
		if (get_option('woocommerce_shipany_is_contain_location_list') === 'true' && $locker && $locker != '[]') {
			foreach ($order->get_shipping_methods() as $shipping_method) {
				if ($shipping_method->get_method_id() === 'local_pickup') {
					return true;
				}
			}
		}
	}
	return $needs_shipping;
}

function update_shipping_address() {
	// require a valid nonce
	if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'shipany_update_shipping_address')) {
		wp_send_json_error('Invalid nonce', 403);
	}
	WC()->frontend_includes();
	wc_load_cart();
	$useShippingAsBilling = false;
	if(isset($_POST["useShippingAsBilling"]) && $_POST["useShippingAsBilling"]){
		$useShippingAsBilling = wc_clean($_POST["useShippingAsBilling"]) == 'yes';
	}
	$region = strtoupper(wc_clean($_POST["region"]));
	$bypass_billing_address = ShipanyHelper::get_settings('shipany_bypass_billing_address');
	$locker_length_truncate = ShipanyHelper::get_settings('shipany_locker_length_truncate');

	$country = ShipanyHelper::get_settings('shipany_region') == 2 ? 'TW' : 'HK';
	$state = '';
	foreach(WC()->countries->get_states()[$country] as $key => $value) {
		if (strpos($value, $region) !== false) {
			$state = $key;
			break;
		}
	}

	$locate = get_locale();
	$is_chinese = strpos($locate, 'zh') === 0;
	if ($is_chinese) {
		$address1 = wc_clean($_POST["shippingAddress2"]);
	} else {
		$address1 = wc_clean($_POST["shippingAddress1"]);
	}

	if ($bypass_billing_address != 'yes' && $useShippingAsBilling) {
		if ($locker_length_truncate > 15) {
			WC()->customer->set_billing_address_1(substr($address1, 0, $locker_length_truncate));
		} else {
			WC()->customer->set_billing_address_1($address1);
		}
		WC()->customer->set_billing_city(wc_clean($_POST["district"]));
		WC()->customer->set_billing_country($country);
		WC()->customer->set_billing_state($state);
	}
	 else {
		$useShippingAsBilling = false;
	}

	if ($locker_length_truncate > 15) {
		WC()->customer->set_shipping_address_1(substr($address1, 0, $locker_length_truncate));
	} else {
		WC()->customer->set_shipping_address_1($address1);
	}
	WC()->customer->set_shipping_city(wc_clean($_POST["district"]));
	WC()->customer->set_shipping_country($country);
	WC()->customer->set_shipping_state($state);

	WC()->customer->save();
	
	wp_send_json_success(array(
		'billing' => WC()->customer->get_billing(),
		'shipping' => WC()->customer->get_shipping(),
		'useShippingAsBilling' => $useShippingAsBilling,
	));
}


// function update_post_metadata_hook($check, $object_id, $meta_key, $meta_value) {
// 	// check object is order
// 	if (get_post_type($object_id) == 'shop_order') {
// 		// if meta_key is _pr_shipment_shipany_label_tracking
// 		if ($meta_key == '_pr_shipment_shipany_label_tracking') {
// 			// get tracking number
// 			$shipany_uid = $meta_value['shipment_id'] ?? '';
// 			// get order object -> $orgi_order
// 			$orgi_order = wc_get_order($object_id);
// 			$orgi_meta_value = $orgi_order->get_meta('_pr_shipment_shipany_label_tracking');
// 			$orgi_shipany_uid = $orgi_meta_value['shipment_id'] ?? '';

// 			// if tracking number is not empty and tracking number meta is empty
// 			if (!empty($orgi_shipany_uid) && !empty($shipany_uid) && $shipany_uid != $orgi_shipany_uid) {
// 				// push comment
// 				if (is_array($meta_value['courier_service_plan'])) {
// 					$meta_value['courier_service_plan'] = sprintf(
// 						'%s - %s %s',
// 						__($meta_value['courier_service_plan']['cour_svc_pl'], 'shipany'),
// 						__($meta_value['courier_service_plan']['cour_ttl_cost']['ccy'], 'shipany'),
// 						$meta_value['courier_service_plan']['cour_ttl_cost']['val']
// 					);
// 				}
// 				$tracking_note = ShipanyHelper::get_tracking_note(
// 					$shipany_uid,
// 					$meta_value['courier_tracking_number'],
// 					strtok($meta_value['courier_service_plan'], '-'),
// 					$meta_value['courier_tracking_url']
// 				);

// 				if (isset($meta['label_path_s3']) && !empty($meta['label_path_s3'])) {
// 					$meta['label_path'] = ShipanyHelper::get_shipany_label_file_info($shipany_uid)->path;
// 				}

// 				$tracking_note_type = ShipanyHelper::get_tracking_note_type();
// 				$tracking_note_type = empty($tracking_note_type) ? 0 : 1;
// 				$orgi_order->add_order_note($tracking_note, $tracking_note_type, true);
// 			}
// 		}
// 	}
// }

// For #143
// TODO: Remove it
function shipany_blocks($rates) {
	if (in_array('woo-gutenberg-products-block/woocommerce-gutenberg-products-block.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		wp_enqueue_script(
			'wc-shipment-blocks',
			SHIPANY_PLUGIN_DIR_URL . '/assets/js/shipany-blocks.js',
			array('jquery'),
			SHIPANY_VERSION
		);

		wp_localize_script('wc-shipment-blocks', 'shipany_setting', array());
	}
	return $rates;
}

$GLOBALS['Courier_uid_mapping'] = array('SF Express' => array('6ae8b366-0d42-49c8-a543-71823226204f', '5ec1c56d-c2cd-4e41-a83d-ef11b0a0fefe', 'b92add3c-a9cb-4025-b938-33a2e9f7a3a7'),
	'UPS' => array('c7f6452b-567f-42c9-9007-2bdbc8cbea15', 'afed5748-dbb5-44db-be22-3b9a28172cd9', 'afed5748-dbb5-44db-be22-3b9a28172cd9'),
	'ZeekDash' => array('cb6d3491-1215-420f-beb1-dbfa6803d89c', 'cb2f0d03-cb53-4a2b-9539-e245f5df05b7', '94c7dbc2-e200-43d5-a2b8-1423b91fa2a4'),
	'Lalamove' => array('37577614-9806-4e1c-9163-53b0b2d8163f', 'c6175c19-ef5c-44b1-b719-ce784a96025c', '2cef1de7-841b-4493-a681-5d2694a8faee', 'e033e28f-a072-4151-b936-285ee7ae9c64'),
	'ZTO Express' => array('540013ae-1d5f-4688-b03a-772d38bd257d', 'ad4b9127-5126-4247-9ff8-d7893ae2d2bb', 'f3d59685-0994-49cc-be4d-42af1d9557fe'),
	'Hongkong Post' => array('93562f05-0de4-45cb-876b-c1e449c09d77', '167ba23f-199f-41eb-90b9-a231f5ec2436', '83b5a09a-a446-4b61-9634-5602bf24d6a0'),
	'Zeek2Door' => array('998d3a95-3c8c-41c9-90d8-8e7bcf95e38d', '85cc2f44-8508-4b46-b49d-28b7b4c65da4', '651bb29b-68a8-402d-bca6-57cf31de065c'),
	'HAVI (Cold Chain)' => array('f403ee94-e84b-4574-b340-e734663cdb39', 'c6e80140-a11f-4662-8b74-7dbc50275ce2', '2ba434b5-fa1d-4541-bc43-3805f8f3a26d'),
	'Quantium' => array('a9edf457-6515-4111-bcac-738a29d0b58b', '2124fd86-dc2b-4762-acd6-625bd406bbcb', 'ccdf3c16-d34f-4e77-996c-1b00ed8a925e'),
	'Zeek' => array('c04cb957-7c8f-4754-ba29-4df0c276392b', 'fe08c066-acbe-4fac-b395-4289bd0e02d6', '0864b67a-cb87-462a-b4f7-69c30691cdea'),
	'Jumppoint' => array('79703b17-4198-472b-80b3-e195cd8250a4', '6e494226-985a-4ca0-bb0b-d5994a051932', '60a9855e-9983-4e1c-ad5f-373d3e25a0f1'),
	'SF Express (Cold Chain)' => array('1d22bb21-da34-4a3c-97ed-60e5e575a4e5', '1bbf947d-8f9d-47d8-a706-a7ce4a9ddf52', 'c74daf26-182a-4889-924b-93a5aaf06e19')
);
// New Multi Checkbox field for woocommerce backend
/**
 * Convert weight to kg (if $from_shipany is false)
 * Convert weight to other unit (if $from_shipany is true)
 */
function weight_convert($value, $unit, $from_shipany = false) {
	if ($from_shipany == false) {
		if ($unit == 'kg') {
			return $value;
		} else if ($unit == 'g') {
			return $value * 0.001;
		} else if ($unit == 'lbs') {
			return $value * 0.453592;
		} else if ($unit == 'oz') {
			return $value * 0.0283495;
		}
	} else if ($from_shipany == true) {
		if ($unit == 'kg') {
			return $value;
		} else if ($unit == 'g') {
			return $value * 1000;
		} else if ($unit == 'lbs') {
			return round($value * 2.20462, 2);
		} else if ($unit == 'oz') {
			return round($value * 35.274, 2);
		}
	}

}
function woocommerce_wp_multi_checkbox($field) {
	$field['class'] = isset($field['class']) ? $field['class'] : 'select short';
	$field['style'] = isset($field['style']) ? $field['style'] : '';
	$field['wrapper_class'] = isset($field['wrapper_class']) ? $field['wrapper_class'] : '';
	$field['value'] = isset($field['value']) ? $field['value'] : array();
	$field['name'] = isset($field['name']) ? $field['name'] : $field['id'];
	$field['desc_tip'] = isset($field['desc_tip']) ? $field['desc_tip'] : false;

	echo '<fieldset class="form-field ' . esc_attr($field['id']) . '_field ' . esc_attr($field['wrapper_class']) . '">
    <legend>' . wp_kses_post($field['label']) . '</legend>';

	if (!empty($field['description']) && false !== $field['desc_tip']) {
		echo wc_help_tip($field['description']);
	}

	echo '<ul>';

	foreach ($field['options'] as $key => $value) {

		echo '<li><label><input
                name="' . esc_attr($field['name'] . $value) . '"
                value="' . esc_attr($key) . '"
                type="checkbox"
                class="' . esc_attr($field['class']) . '"
                style="' . esc_attr($field['style']) . '"
                ' . (is_array($field['value']) && in_array($key, $field['value']) ? 'checked="checked"' : '') . ' /> ' . esc_html($value) . '</label>
        </li>';
	}
	echo '</ul>';

	if (!empty($field['description']) && false === $field['desc_tip']) {
		echo '<span class="description">' . wp_kses_post($field['description']) . '</span>';
	}

	echo '</fieldset>';
}

function customizing_shipping_methods($rates, $package) {
	if (is_admin() && !defined('DOING_AJAX'))
		return $rates;
	$min_cost = SHIPANY()->get_shipping_shipany_settings()['shipany_locker_free_cost'];
	if ($min_cost != '') {
		// // Iterating through Shipping Methods
		foreach ($rates as $rate_values) {
			$method_id = $rate_values->method_id;
			$rate_id = $rate_values->id;
			// For "Local pickup" Shipping" Method only
			if ('local_pickup' === $method_id) {
				if ($package["contents_cost"] >= floatval($min_cost)) {
					// Set the rate calculated cost based on cart items count
					$rates[$rate_id]->cost = 0;
				}
			}
		}
	}

	return $rates;
}

function hide_shipping_methods($rates) {
	$shipping_shipnay_settings = SHIPANY()->get_shipping_shipany_settings();
	$default_courier_id = $shipping_shipnay_settings['shipany_default_courier'];
	$api_config = ShipanyHelper::getApiUrlAndRealTk('api', $shipping_shipnay_settings['shipany_api_key'], $shipping_shipnay_settings['shipany_region']);

	$locker = null;
	// Option(woocommerce_shipany_is_contain_location_list) is not defined
	if (get_option('woocommerce_shipany_is_contain_location_list', null) === null || get_option('woocommerce_shipany_is_contain_location_list_version', null) !== SHIPANY_VERSION) {
		$couriers = ShipanyHelper::get_couriers();
		$courier = null;

		foreach ($couriers as $value) {
			if ($value->uid == $default_courier_id) {
				$courier = $value;
				break;
			}
		}
		if ($courier) {
			update_option('woocommerce_shipany_is_contain_location_list', $courier->cour_props->delivery_services->supported_service_location_types ? 'true' : 'false');
		}
		update_option('woocommerce_shipany_is_contain_location_list_version', SHIPANY_VERSION);
	}

	// Option(woocommerce_shipany_is_contain_location_list)  is already defined
	if (get_option('woocommerce_shipany_is_contain_location_list') === 'true' && $locker != '[]') {
		if ($locker === null) {
			$locker = ShipanyHelper::get_latest_locker_list($default_courier_id);
		}
		if ($locker && $locker !== '[]') {
			return $rates;
		}
	}
	if (get_option('woocommerce_shipany_is_contain_location_list') === 'false' || $locker == '[]') {
		foreach ($rates as $rate_id => $rate) {
			if ('local_pickup' !== $rate->method_id) {
				$rates_arr[$rate_id] = $rate;
			}
		}
		return !empty($rates_arr) ? $rates_arr : $rates;
	}

	// old path
	$locker = ShipanyHelper::get_latest_locker_list($default_courier_id);
	if ($locker === false) {
		foreach ($rates as $rate_id => $rate) {
			if ('local_pickup' !== $rate->method_id) {
				$rates_arr[$rate_id] = $rate;
			}
		}
	}

	return !empty($rates_arr) ? $rates_arr : $rates;
}

/**
 * @deprecated 1.1.42
 */
function woocommerce_review_order_after_shipping() {
	?>
	<div style='display:none !important'>
		<script type="text/javascript">
			var createChangeLocationElement;
			var closeModalNew = () => {
				var modal_new = document.getElementById("shipany-woo-plugin-modal");
				modal_new.classList.remove("shipany-woo-plugin-showModal");
				var radioBtns = document.querySelectorAll('input[type="radio"]');

				radioBtns.forEach((item) => {
					item.style.display = null;
				});
			};

			var shipping_methods = document.getElementsByClassName('shipping_method')
			for (let shipping_method of shipping_methods) {
				if (!shipping_method.id.includes('local')) {
					shipping_method.onclick = closeModalNew
				}
			}

			function trigger_list() {
				jQuery('.wc-proceed-to-checkout').css('pointer-events', 'none');
				jQuery('.wc-proceed-to-checkout').css('opacity', '0.5');
				setTimeout(function () {
					jQuery('.wc-proceed-to-checkout').css('pointer-events', '');
					jQuery('.wc-proceed-to-checkout').css('opacity', '1');
				}, 5000);
				jQuery('input[name="shipany_locker_collect"]').click();
			}
			var lockedField = false;
			function lockField(b) {
				if (!jQuery('#ship-to-different-address-checkbox').length || (shipany_setting.shipany_bypass_billing_address == 'no' || shipany_setting.shipany_bypass_billing_address == null)) {
					if (jQuery('#ship-to-different-address-checkbox').length && jQuery('#ship-to-different-address-checkbox').get(0).checked) {
						jQuery('#billing_address_1').prop('readonly', false);
						jQuery('#billing_address_2').prop('readonly', false);
						jQuery('#billing_city').prop('readonly', false);
						jQuery('#billing_country').prop('readonly', false);
						jQuery('#billing_state').prop('readonly', false);
						jQuery('#billing_postcode').prop('readonly', false);
					} else {
						jQuery('#billing_address_1').prop('readonly', b);
						jQuery('#billing_address_2').prop('readonly', b);
						jQuery('#billing_city').prop('readonly', b);
						jQuery('#billing_country').prop('readonly', b);
						jQuery('#billing_state').prop('readonly', b);
						jQuery('#billing_postcode').prop('readonly', b);
					}
				}
				jQuery('#shipping_address_1').prop('readonly', b);
				jQuery('#shipping_address_2').prop('readonly', b);
				jQuery('#shipping_city').prop('readonly', b);
				jQuery('#shipping_country').prop('readonly', b);
				jQuery('#shipping_state').prop('readonly', b);
				jQuery('#shipping_postcode').prop('readonly', b);
				lockedField = b;
			}
			jQuery(document).on('change', '#billing_country, #shipping_country', function (e) {
				console.log(e);
				if (jQuery('#shipping_address_1').prop('readonly')) {
					if (shipany_setting.shipany_bypass_billing_address == 'no' || shipany_setting.shipany_bypass_billing_address == null) {
						jQuery('#billing_address_1').val('');
						jQuery('#billing_address_2').val('');
					}
					jQuery('#shipping_address_1').val('');
					jQuery('#shipping_address_2').val('');
				}
				if (e.originalEvent && this.querySelector('input.shipping_method').id == document.querySelector('[id^="shipping_method_0_local_pickup"]').id) {
					lockField(true);
				} else {
					lockField(false);
				}
			})
			jQuery(document).on('click', '#shipping_method > *', function (e) {
				if (e.originalEvent && this.querySelector('input.shipping_method').id == document.querySelector('[id^="shipping_method_0_local_pickup"]').id) {
					trigger_list()
					if (shipany_setting.shipany_bypass_billing_address == 'yes') {
						if (jQuery('#ship-to-different-address-checkbox').length && !jQuery('#ship-to-different-address-checkbox').get(0).checked) {
							jQuery('#ship-to-different-address-checkbox').click()
						}
						jQuery('#ship-to-different-address-checkbox').prop('disabled', true);
						jQuery('#ship-to-different-address-checkbox').before('<input type="hidden" name="ship_to_different_address" value="1">')

					}
					lockField(true);
				} else {
					jQuery('#ship-to-different-address-checkbox').prop('disabled', false);
					// jQuery remove input hidden name="ship_to_different_address", must check it is type=hidden
					jQuery('input[name="ship_to_different_address"][type="hidden"]').remove()
					lockField(false);
				}
			})
			if (window.location.href.includes('checkout') || window.location.href.includes('cart')) {
				let aTag = document.getElementById('onChangeLocation');
				// add change component
				createChangeLocationElement = function () {
					jQuery('#shipany-woo-plugin-change-location').remove();
					var div = document.createElement('div');
					div.style.marginLeft = '6px'
					div.style.display = 'inline'
					div.id = 'shipany-woo-plugin-change-location'
					let defaultLabelName = 'Change address'
					if (window?.shipany_setting?.shipany_enable_locker_list2_1) {

						defaultLabelName = shipany_setting.shipany_enable_locker_list2_1

					}

					var componentButtonTemplate = `<div><a style="cursor: pointer;" id="onChangeLocation">${defaultLabelName}</a></div>`
					div.innerHTML = componentButtonTemplate.trim();
					if (document.querySelector('[for^="shipping_method_0_local_pickup"]') != null) {
						document.querySelector('[for^="shipping_method_0_local_pickup"]').parentNode.insertBefore(
							div, document.querySelector('[for^="shipping_method_0_local_pickup"]').nextSibling
						)
					}
					aTag = document.getElementById('onChangeLocation');
					if(aTag != null){
						aTag.onclick = () => {
							jQuery('[id^="shipping_method_0_local_pickup"][type="radio"]')[0].click();
							trigger_list();
						};
					}
				}
				jQuery(document.body).on('updated_cart_totals', function () {
					createChangeLocationElement();
				});
				createChangeLocationElement();
				if (document.getElementById("shipping_method").getElementsByTagName("li").length == 1) {
					var shipping_method_ori = document.querySelector('[for^="shipping_method_"]')
					if (shipping_method_ori != null && shipping_method_ori.outerHTML.includes('local_pickup')) {
						lockField(true);
						if (shipany_setting.shipany_bypass_billing_address == 'yes') {
							if (jQuery('#ship-to-different-address-checkbox').length && !jQuery('#ship-to-different-address-checkbox').get(0).checked) {
								jQuery('#ship-to-different-address-checkbox').click()
							}
							jQuery('#ship-to-different-address-checkbox').prop('disabled', true);
							jQuery('#ship-to-different-address-checkbox').before('<input type="hidden" name="ship_to_different_address" value="1">')
						}
						// 		shipping_method_ori.style.display = 'none'
						// 		var textContent = shipping_method_ori.textContent
						// 		document.getElementById('onChangeLocation').text = textContent
						// 		document.getElementById('onChangeLocation').style.color = 'blue'
						// 		document.getElementById('onChangeLocation').style.textDecoration = 'underline'
					}
				} else {
					var shipping_method_ori = document.querySelector('[id^="shipping_method_0_local_pickup"]')
					if (shipping_method_ori != null && shipping_method_ori.checked == true) {
						lockField(true);
						if (shipany_setting.shipany_bypass_billing_address == 'yes') {
							if (jQuery('#ship-to-different-address-checkbox').length && !jQuery('#ship-to-different-address-checkbox').get(0).checked) {
								jQuery('#ship-to-different-address-checkbox').click()
							}
							jQuery('#ship-to-different-address-checkbox').prop('disabled', true);
							jQuery('#ship-to-different-address-checkbox').before('<input type="hidden" name="ship_to_different_address" value="1">')
						}
					} else {
						lockField(false);
					}
				}
			}

		</script>
	</div>
	<?php

}

/**
 * @since 1.1.43
 */
function modify_rate_args_for_local_pickup($args, $obj) {
	if (isset($obj->id) && $obj->id === 'local_pickup' && (!isset($GLOBALS['LocalPickUpExisted']) || $GLOBALS['LocalPickUpExisted'] === $args['id'])) {
		$args['label'] = ShipanyHelper::get_settings('shipany_enable_locker_list2');
		if (!$args['label']) {
			$args['label'] = "Pick up at locker/store";
			$referer = '';
			if(isset($_SERVER['HTTP_REFERER'])){
				$referer = $_SERVER['HTTP_REFERER'];
			}
			if(is_cart() || $referer === get_permalink(wc_get_page_id('cart'))){
				$args['label'] .= " [Select at next page after clicked \"Proceed to checkout\"]";
			}
		}
		$args['meta_data'] = array_merge($args['meta_data'], array('shipany_locker_collect' => 'yes'));
		// For Woocommerce Block checkout page, the price has been shown already
		// if ($args['cost'] > 0) {
		// 	$args['label'] = $args['label'] . ': ' . wc_price($args['cost']);
		// }
		$GLOBALS['LocalPickUpExisted'] = $args['id'];
	}
	return $args;
}

/**
 * @deprecated 1.1.42
 */
function rename_popup_local_pickup($label, $method) {
	if ('local_pickup' === $method->method_id && !isset($_COOKIE['LocalPickUpExisted'])) {
		if (array_key_exists('shipany_enable_locker_list2', SHIPANY()->get_shipping_shipany_settings())) {
			$label = SHIPANY()->get_shipping_shipany_settings()['shipany_enable_locker_list2'] ? SHIPANY()->get_shipping_shipany_settings()['shipany_enable_locker_list2'] : "Pick up at locker/store";
		} else {
			$label = "Pick up at locker/store";
		}
		if ($method->cost > 0) {
			$label = $label . ': ' . wc_price($method->cost);
		}
		$_COOKIE['LocalPickUpExisted'] = true;
	}
	return $label;
}

function shipany_activation_redirect($plugin) {
	if ($plugin == plugin_basename(__FILE__)) {
		if (wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=shipany_ecs_asia'))) {
			exit;
		}
	}
}
/**
 * @deprecated 1.1.42
 */
function addClickAndCollectWidget($method, $index) {
	$chosen_methods = WC()->session->get('chosen_shipping_methods');
	$chosen_shipping = $chosen_methods[0];

	if (strpos($chosen_shipping, 'local_pickup') === 0 || strpos($method->get_id(), 'local_pickup') === 0) {
		include("pages/click-collect-widget.php");
	}

}

function themeslug_enqueue_script() {
	wp_enqueue_script('easywidgetjs', plugin_dir_url(__FILE__) . "pages/easywidgetSDK/easywidget.js?" . time(), array('jquery'), null, true);
	$script_params = array(
		'path' => plugin_dir_url(__FILE__),
		'courier_id' => isset(get_option('woocommerce_shipany_ecs_asia_settings')['shipany_default_courier']) ? get_option('woocommerce_shipany_ecs_asia_settings')['shipany_default_courier'] : "",
		'region_idx' => isset(get_option('woocommerce_shipany_ecs_asia_settings')['shipany_region']) ? get_option('woocommerce_shipany_ecs_asia_settings')['shipany_region'] : "",
		'env' => isset(get_option('woocommerce_shipany_ecs_asia_settings')['shipany_api_key']) ? ShipanyHelper::extractApiKeyEnvironment(get_option('woocommerce_shipany_ecs_asia_settings')['shipany_api_key'])['env'] : "",
		'lang' => strval(get_locale()),
		'ver' => SHIPANY_VERSION
	);
	wp_localize_script('easywidgetjs', 'scriptParams', $script_params);
	wp_enqueue_style('wc-shipment-shipany-label-css', SHIPANY_PLUGIN_DIR_URL . '/assets/css/shipany-admin.css');
	wp_enqueue_script(
		'wc-shipment-rename-localpickup-js',
		SHIPANY_PLUGIN_DIR_URL . '/assets/js/shipany-rename-localpickup.js',
		array('jquery'),
		SHIPANY_VERSION
	);
	add_action('wp_head', 'sa_wpse_add_inline_script');
	function sa_wpse_add_inline_script() {
		echo '<script>' . PHP_EOL;
		echo 'var wp_rest_nonce = "' . wp_create_nonce('wp_rest') . '";' . PHP_EOL;
		echo 'var locationListEndpoint = "' . get_home_url() . '/wp-json/shipany/v1/get-latest-locker-list?k=' . wp_create_nonce('shipany_get_latest_locker_list') . '";' . PHP_EOL;
		echo 'var updateShippingAddressEndpoint = "' . get_home_url() . '/wp-json/shipany/v1/update-shipping-address?nonce=' . wp_create_nonce('shipany_update_shipping_address') . '";' . PHP_EOL;
		echo '</script>' . PHP_EOL;
	}
	wp_enqueue_script('test', SHIPANY_PLUGIN_DIR_URL . '/pages/woocommerce-checkout.js', array('wp-i18n', 'wp-plugins', 'wc-blocks-checkout', 'wc-blocks-data-store', 'wp-element'));
	$temp_setting = SHIPANY()->get_shipping_shipany_settings();
	if (isset($temp_setting['shipany_api_key'])) {
		unset($temp_setting['shipany_api_key']);
	}
	wp_localize_script('wc-shipment-rename-localpickup-js', 'shipany_setting', $temp_setting);
}

if (!class_exists('SHIPANY_WC')):

	class SHIPANY_WC {
		public static $list;
		private $version = "1.1.60";

		protected static $_instance = null;

		public $shipping_shipany_order = null;

		// protected $shipping_shipany_product = null;

		protected $logger = null;

		private $payment_gateway_titles = array();

		protected $base_country_code = '';

		// 'LI', 'CH', 'NO'
		protected $eu_iso2 = array('AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SI', 'SK', 'ES', 'SE');

		protected $us_territories = array('US', 'GU', 'AS', 'PR', 'UM', 'VI');

		/**
		 * Construct the plugin.
		 */
		public function __construct() {
			add_action('init', array($this, 'load_plugin'), 0);

			$upload_dir = wp_upload_dir();
			if ($file_handle = @fopen(trailingslashit($upload_dir['basedir'] . '/woocommerce_shipany_label') . '.htaccess', 'w')) {
				fwrite($file_handle, '');
				fclose($file_handle);
			}
			global $COURIER_LALAMOVE;
			$COURIER_LALAMOVE = [
				'37577614-9806-4e1c-9163-53b0b2d8163f',
				'f3bbaf88-e389-4f70-b70e-979c508da4c9',
				'c6175c19-ef5c-44b1-b719-ce784a96025c',
				'2cef1de7-841b-4493-a681-5d2694a8faee',
				'e033e28f-a072-4151-b936-285ee7ae9c64', // TW DEMO1
			];
		}

		/**
		 *
		 * Ensures only one instance is loaded or can be loaded.
		 * @return self Main instance.
		 */
		public static function instance() {
			if (is_null(self::$_instance)) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Define WC Constants.
		 */
		private function define_constants() {
			$upload_dir = wp_upload_dir();

			// Path related defines
			$this->define('SHIPANY_PLUGIN_FILE', __FILE__);
			$this->define('SHIPANY_PLUGIN_BASENAME', plugin_basename(__FILE__));
			$this->define('SHIPANY_PLUGIN_DIR_PATH', untrailingslashit(plugin_dir_path(__FILE__)));
			$this->define('SHIPANY_PLUGIN_DIR_URL', untrailingslashit(plugins_url('/', __FILE__)));

			$this->define('SHIPANY_VERSION', $this->version);

			$this->define('SHIPANY_LOG_DIR', $upload_dir['basedir'] . '/wc-logs/');

			$this->define('SHIPANY_ECS_ASIA_TRACKING_URL', 'https://portal.shipany.io/tracking?id=');
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		public function includes() {
			// Auto loader class
			include_once('includes/class-shipany-autoloader.php');
			// Load abstract classes
			include_once('includes/abstract-shipany-wc-order.php');
			include_once("lib/PDFMerger-master/PDFMerger.php");

			// Composer autoloader
			include_once('vendor/autoload.php');
		}

		/**
		 * Determine which plugin to load.
		 */
		public function load_plugin() {
			// Checks if WooCommerce is installed.
			if (class_exists('WC_Shipping_Method')) {
				$this->base_country_code = $this->get_base_country();

				$shipany_parcel_countries = array('NL', 'BE', 'LU');

				if (!in_array($this->base_country_code, $shipany_parcel_countries) || apply_filters('shipping_shipany_bypass_load_plugin', false)) {
					$this->define_constants();
					$this->includes();
					$this->init_hooks();
					$this->init_ajax_action();
				}
			} else {
				// Throw an admin error informing the user this plugin needs WooCommerce to function
				add_action('admin_notices', array($this, 'notice_wc_required'));
			}

		}

		/**
		 * Initialize the plugin.
		 */
		public function init() {
			add_action('admin_notices', array($this, 'environment_check'));
			// $this->get_shipany_wc_product();
			$this->get_shipany_wc_order();
		}

		public function init_hooks() {
			add_action('init', array($this, 'init'), 1);
			add_action('init', array($this, 'load_textdomain'));

			add_action('admin_enqueue_scripts', array($this, 'shipany_enqueue_scripts'));

			add_action('woocommerce_shipping_init', array($this, 'includes'));
			add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

			// add_filter( 'woocommerce_states', array( $this, 'custom_woocommerce_states' ));
			add_action('wp_ajax_test_shipany_connection', array($this, 'test_shipany_connection_callback'));
			add_action('wp_ajax_update_default_courier', array($this, 'update_default_courier'));
		}

		public function update_default_courier() {
			check_ajax_referer('shipany-test-con', 'test_con_nonce');
			$api_key_temp = $_POST['val'];


			try {

				$shipany_obj = $this->get_shipany_factory();
				// $response = $shipany_obj->api_client->get_test_con('couriers/',$api_key_temp);
				// if ( $response->status != 200 ) {
				// 	return;
				// }
				$connection_msg = __('Connection Success!', 'shipany');
				$this->log_msg($connection_msg);
				$courier_list = $response->body->data->objects;
				self::$list = $courier_list;


				wp_send_json(array(
					'connection_success' => $connection_msg,
					'button_txt' => __('Test Connection', 'shipany'),
					'courier_list' => $courier_list
				));

			} catch (Exception $e) {
				$this->log_msg($e->getMessage());

				wp_send_json(array(
					'connection_error' => sprintf(__('Connection Failure: %s Make sure to save the settings before testing the connection. ', 'shipany'), $e->getMessage()),
					'button_txt' => __('Test Connection', 'shipany')
				));
			}

			wp_die();
		}


		public function test_shipany_connection_callback() {

			$api_key_temp = $_POST['val'];
			$region = $_POST['region'];

			$result = ShipanyHelper::getApiUrlAndRealTk('api', $api_key_temp, $region);
			$temp_api_endpoint = $result['url'];
			$api_key_temp = $result['api-tk'];

			$response = wp_remote_get($temp_api_endpoint . 'merchants/self/', array(
				'headers' => array(
					'api-tk' => $api_key_temp
				)
			));
			$status_code = wp_remote_retrieve_response_code($response);
			wp_send_json(array(
				'connection_success' => $status_code
			));
			// check_ajax_referer( 'shipany-test-con', 'test_con_nonce' );
			// $api_key_temp= $_POST['val'];
			// try {

			// 	$shipany_obj = $this->get_shipany_factory();
			// 	$response = $shipany_obj->api_client->get_merchant_info_test_con($api_key_temp);
			// 	if ( $response->status != 200 ) {
			// 		return;
			// 	}

			// 	$connection_msg = __('Connection Success!', 'shipany');
			// 	$this->log_msg( $connection_msg );

			// 	wp_send_json( array( 
			// 		'connection_success' 	=> $connection_msg,
			// 		'button_txt'			=> __( 'Test Connection', 'shipany' )
			// 		) );

			// } catch (Exception $e) {
			// 	$this->log_msg($e->getMessage());

			// 	wp_send_json( array( 
			// 		'connection_error' => sprintf( __('Connection Failure: %s Make sure to save the settings before testing the connection. ', 'shipany'), $e->getMessage() ),
			// 		'button_txt'			=> __( 'Test Connection', 'shipany' )
			// 		 ) );
			// }

			// wp_die();
		}

		public function get_shipany_wc_order() {
			if (!isset($this->shipping_shipany_order)) {
				try {
					$shipany_obj = $this->get_shipany_factory();

					if ($shipany_obj->is_shipany_ecs_asia()) {
						$this->shipping_shipany_order = new SHIPANY_WC_Order_eCS_Asia();
					}
					// Ensure folder exists
					$this->shipany_label_folder_check();
				} catch (Exception $e) {
					add_action('admin_notices', array($this, 'environment_check'));
				}
			}

			return $this->shipping_shipany_order;
		}

		/**
		 * Localisation
		 */
		public function load_textdomain() {
			load_plugin_textdomain('shipany', false, dirname(plugin_basename(__FILE__)) . '/lang/');
		}

		public function shipany_enqueue_scripts() {
			// Enqueue Styles
			wp_enqueue_style('wc-shipment-shipany-label-css', SHIPANY_PLUGIN_DIR_URL . '/assets/css/shipany-admin.css');
			wp_enqueue_style('wc-shipment-shipany-loader-css', SHIPANY_PLUGIN_DIR_URL . '/assets/css/shipany-loader.css');
			wp_enqueue_style('wc-shipment-shipany-common-css', SHIPANY_PLUGIN_DIR_URL . '/assets/css/shipany-common.css');

			// Enqueue Scripts
			$screen = get_current_screen();
			$screen_id = $screen ? $screen->id : '';
			$test_con_data = array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'loader_image' => admin_url('images/loading.gif'),
				'test_con_nonce' => wp_create_nonce('shipany-test-con'),
			);
			wp_enqueue_script(
				'wc-shipment-shipany-testcon-js',
				SHIPANY_PLUGIN_DIR_URL . '/assets/js/shipany-test-connection.js',
				array('jquery'),
				SHIPANY_VERSION
			);
			wp_localize_script('wc-shipment-shipany-testcon-js', 'shipany_test_con_obj', $test_con_data);
		}


		/**
		 * Define constant if not already set.
		 *
		 * @param  string $name
		 * @param  string|bool $value
		 */
		public function define($name, $value) {
			if (!defined($name)) {
				define($name, $value);
			}
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_shipping_method($shipping_method) {
			// Check country somehow
			try {
				$shipany_obj = $this->get_shipany_factory();

				if ($shipany_obj->is_shipany_ecs_asia()) {
					$shipany_ship_meth = 'SHIPANY_WC_Method_eCS_Asia';
					$shipping_method['shipany_ecs'] = $shipany_ship_meth;
				}

			} catch (Exception $e) {
				// do nothing
			}

			return $shipping_method;
		}

		/**
		 * Admin error notifying user that WC is required
		 */
		public function notice_wc_required() {
			?>
			<div class="error">
				<p>
					<?php _e('requires WooCommerce to be installed and activated!', 'shipany'); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * environment_check function.
		 */
		public function environment_check() {
			// Try to get the shipany object...if exception if thrown display to user, mainly to check country support.
			try {
				$this->get_shipany_factory();
			} catch (Exception $e) {
				echo '<div class="error"><p>' . esc_html($e->getMessage()) . '</p></div>';
			}
		}

		public function get_base_country() {
			$country_code = wc_get_base_location();
			return apply_filters('shipping_shipany_base_country', $country_code['country']);
		}

		/**
		 * Create an object from the factory based on country.
		 */
		public function get_shipany_factory() {

			$base_country_code = $this->get_base_country();

			try {
				$shipany_obj = SHIPANY_API_Factory::make_shipany($base_country_code);
			} catch (Exception $e) {
				throw $e;
			}

			return $shipany_obj;
		}

		public function get_shipany_factory_test_con($api_key_temp) {

			$base_country_code = $this->get_base_country();

			try {
				$shipany_obj = SHIPANY_API_Factory::make_shipany_test_con($base_country_code, $api_key_temp);
			} catch (Exception $e) {
				throw $e;
			}

			return $shipany_obj;
		}

		public function get_api_url() {

			try {

				$shipany_obj = $this->get_shipany_factory();

				if ($shipany_obj->is_shipany_ecs_asia()) {

					return $shipany_obj->get_api_url();

				}

			} catch (Exception $e) {
				throw new Exception('Cannot get shipany api credentials!');
			}
		}

		public function get_shipping_shipany_settings() {
			$shipany_settings = array();

			try {
				$shipany_obj = $this->get_shipany_factory();

				if ($shipany_obj->is_shipany_ecs_asia()) {
					$shipany_settings = $shipany_obj->get_settings();
				}

			} catch (Exception $e) {
				throw $e;
			}

			return $shipany_settings;
		}

		public function log_msg($msg) {

			try {
				$shipping_shipnay_settings = $this->get_shipping_shipany_settings();
				$shipany_debug = isset($shipping_shipnay_settings['shipany_debug']) ? $shipping_shipnay_settings['shipany_debug'] : 'yes';

				if (!$this->logger) {
					$this->logger = new SHIPANY_Logger($shipany_debug);
				}

				$this->logger->write($msg);

			} catch (Exception $e) {
				// do nothing
			}
		}

		public function get_log_url() {

			try {
				$shipping_shipnay_settings = $this->get_shipping_shipany_settings();
				$shipany_debug = isset($shipping_shipnay_settings['shipany_debug']) ? $shipping_shipnay_settings['shipany_debug'] : 'yes';

				if (!$this->logger) {
					$this->logger = new SHIPANY_Logger($shipany_debug);
				}

				return $this->logger->get_log_url();

			} catch (Exception $e) {
				throw $e;
			}
		}

		/**
		 * Function return whether the sender and receiver country is the same territory
		 */
		public function is_shipping_domestic($country_receiver) {

			// If base is US territory
			if (in_array($this->base_country_code, $this->us_territories)) {

				// ...and destination is US territory, then it is "domestic"
				if (in_array($country_receiver, $this->us_territories)) {
					return true;
				} else {
					return false;
				}

			} elseif ($country_receiver == $this->base_country_code) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Function return whether the sender and receiver country is "crossborder" i.e. needs CUSTOMS declarations (outside EU)
		 */
		public function is_crossborder_shipment($country_receiver) {

			if ($this->is_shipping_domestic($country_receiver)) {
				return false;
			}

			// Is sender country in EU...
			if (in_array($this->base_country_code, $this->eu_iso2)) {
				// ... and receiver country is in EU means NOT crossborder!
				if (in_array($country_receiver, $this->eu_iso2)) {
					return false;
				} else {
					return true;
				}
			} else {
				return true;
			}
		}

		/**
		 * Installation functions
		 *
		 * Create temporary folder and files. labels will be stored here as required
		 *
		 * empty_pdf_task will delete them hourly
		 */
		public function create_shipany_label_folder() {
			// Install files and folders for uploading files and prevent hotlinking
			$upload_dir = wp_upload_dir();

			$files = array(
				array(
					'base' => $upload_dir['basedir'] . '/woocommerce_shipany_label',
					'file' => '.htaccess',
					'content' => ''
				),
				array(
					'base' => $upload_dir['basedir'] . '/woocommerce_shipany_label',
					'file' => 'index.html',
					'content' => ''
				)
			);

			foreach ($files as $file) {

				if (wp_mkdir_p($file['base']) && !file_exists(trailingslashit($file['base']) . $file['file'])) {

					if ($file_handle = @fopen(trailingslashit($file['base']) . $file['file'], 'w')) {
						fwrite($file_handle, $file['content']);
						fclose($file_handle);
					}

				}

			}
		}

		public function shipany_label_folder_check() {
			$upload_dir = wp_upload_dir();
			$this->create_shipany_label_folder();
		}

		public function get_shipany_label_folder_dir() {
			$upload_dir = wp_upload_dir();
			return $upload_dir['basedir'] . '/woocommerce_shipany_label/';
			return '';
		}

		public function get_shipany_label_folder_url() {
			$upload_dir = wp_upload_dir();
			return $upload_dir['baseurl'] . '/woocommerce_shipany_label/';
			return '';
		}
		public function init_ajax_action() {
			// hook into admin-ajax
			// the text after 'wp_ajax_' and 'wp_ajax_no_priv_' in the add_action() calls
			// that follow is what you will use as the value of data.action in the ajax
			// call in your JS
			// if the ajax call will be made from JS executed when user is logged into WP,
			// then use this version
			add_action('wp_ajax_on_change_load_couriers', array($this, 'on_change_load_couriers'));
			add_action('wp_ajax_on_click_update_address', array($this, 'on_click_update_address'));
			// if the ajax call will be made from JS executed when no user is logged into WP,
			// then use this version
			add_action('wp_ajax_nopriv_on_change_load_couriers', array($this, 'on_change_load_couriers'));
		}

		public function on_click_update_address() {


			$api_tk = SHIPANY()->get_shipping_shipany_settings()['shipany_api_key'];

			$result = ShipanyHelper::getApiUrlAndRealTk('api', $api_tk, SHIPANY()->get_shipping_shipany_settings()['shipany_region']);
			$temp_api_endpoint = $result['url'];
			$api_tk = $result['api-tk'];

			$response = wp_remote_get($temp_api_endpoint . 'merchants/self/', array(
				'headers' => array(
					'api-tk' => $api_tk,
					'order-from' => 'portal'
				)
			));
			if (wp_remote_retrieve_response_code($response) == 200) {
				// $merchant_info = json_decode($merchant_resp['body'])->data->objects[0];
				$merchant_info = $response['body'];
				$address = json_decode($merchant_info)->data->objects[0]->co_info->org_ctcs[0]->addr;
				$update = get_option('woocommerce_shipany_ecs_asia_settings');
				$update['merchant_info'] = $merchant_info;
				update_option('woocommerce_shipany_ecs_asia_settings', $update);
				wp_send_json_success(array('success' => true, 'address_line1' => $address->ln, 'address_line2' => $address->ln2, 'distr' => $address->distr, 'cnty' => $address->cnty));
			}
		}

		public function on_change_load_couriers() {
			if (!isset($_POST['api_tk'])) {
				// set the return value you want on error
				// return value can be ANY data type (e.g., array())
				$return_value = 'Invalid API token';

				wp_send_json_error($return_value);
			}
			$region = $_POST['region'];
			$api_tk = $_POST['api_tk'];

			$result = ShipanyHelper::getApiUrlAndRealTk('api', $api_tk, $region);
			$temp_api_endpoint = $result['url'];
			$api_tk = $result['api-tk'];

			$response = wp_remote_get($temp_api_endpoint . 'couriers/', array(
				'headers' => array(
					'api-tk' => $api_tk
				),
				'timeout' => 30,
			));
			$status_code = wp_remote_retrieve_response_code($response);
			if (empty($status_code)) {
				$return_value = array(
					'success' => false,
					'data' => array(
						'error_title' => 'no endpoint',
						'error_detail' => 'no endpoint'
					)
				);

				wp_send_json_error($return_value);
			}
			$body = wp_remote_retrieve_body($response);
			if ($status_code !== 200) {
				$resp_body = json_decode($body);
				$error_title = $resp_body->result->descr;
				$error_detail = implode('.', $resp_body->result->details);
				$return_value = array(
					'success' => false,
					'data' => array(
						'error_title' => $error_title,
						'error_detail' => $error_detail
					)
				);

				wp_send_json_error($return_value);
			}

			$body = json_decode($body)->data->objects;
			// do processing you want based on $id
			$rv_cour_list = array();
			$supported_storage_types_courier = array();
			foreach ($body as $key => $value) {
				$rv_cour_list[$value->uid] = __($value->name, 'shipany');
				$supported_storage_types_courier[$value->uid] = $value->cour_props->delivery_services->supported_storage_types;
			}
			// set the return value you want on success
			// return value can be ANY data type (e.g., array())


			$response_merchant = wp_remote_get($temp_api_endpoint . 'merchants/self/', array(
				'headers' => array(
					'api-tk' => $api_tk,
					'order-from' => 'portal'
				)
			));
			$status_code = wp_remote_retrieve_response_code($response_merchant);
			$body = '';
			if ($status_code == 200) {
				$body = wp_remote_retrieve_body($response_merchant);
				$body = json_decode($body)->data->objects;
				if ($body[0]->activated !== true) {
					wp_send_json_error(['data' => [
						'error_title' => __('Please activate your account first.', 'shipany'),
						'error_detail' => ''
					]]);
					return false;
				}
			}
			$return_value = array('success' => true, 'data' => [
				'cour_list' => $rv_cour_list,
				'supported_storage_types_courier' => $supported_storage_types_courier,
			], 'asn_mode' => $body[0]->asn_mode);

			wp_send_json_success($return_value);
		}
	}

endif;

if (!function_exists('SHIPANY')) {

	/**
	 * Activation hook.
	 */
	function shipany_activate() {
		// Flag for permalink flushed
		update_option('shipany_permalinks_flushed', 0);
	}
	register_activation_hook(__FILE__, 'shipany_activate');

	function SHIPANY() {
		return SHIPANY_WC::instance();
	}

	$SHIPANY_WC = SHIPANY();
}