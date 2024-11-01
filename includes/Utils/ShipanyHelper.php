<?php

namespace Utils;

include_once(ABSPATH . "/wp-content/plugins/shipany/includes/REST_API/Auths/Function_Auth.php");

use Exception;
use SHIPANY\Utils\CommonUtils;
use SHIPANY_API_eCS_Asia;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;

use PR\REST_API\API_Client;
use PR\REST_API\Request;
use PR\REST_API\Drivers\WP_API_Driver;
use PR\REST_API\Drivers\Logging_Driver;
use PR\REST_API\Drivers\JSON_API_Driver;
use PR\REST_API\Auths\Function_Auth;

class ShipanyHelper {
    public function __construct() {
        // Ready $driver
        $driver = new WP_API_Driver();
        $driver = new Logging_Driver(SHIPANY(), $driver);
        $driver = new JSON_API_Driver($driver);
        $auth = new Function_Auth(function (Request $request) {
            $request->headers['api-tk'] = static::get_settings('shipany_api_key');
            if (empty($request->headers['api-tk'])) {
                unset($request->headers['api-tk']);
            }
            $request->headers['order-from'] = "Woocommerce";
            $request->headers['order-from-ver'] = SHIPANY_VERSION;
            $request->headers['write_permission_enough'] = get_option('woocommerce_shipany_write_permission_enough');
            return $request;
        });
        $api_config = static::getApiUrlAndRealTk('api', static::get_settings('shipany_api_key'), static::get_settings('shipany_region'));
        $this->api_client = new API_Client($api_config['url'], $driver, $auth);
        $this->shipany_obj = SHIPANY()->get_shipany_factory();
    }

    public static function reload() {
        static::$instance = null;
        static::$cache = array();
        static::getInstance();
    }

    private $api_client;
    private $shipany_obj;
    private static $wc_payments_localization_service;
    private static $cache = array();

    private static $region = array(
        0 => "Hong Kong",
        1 => "Singapore",
        2 => "Taiwan",
        3 => "Thailand",
    );

    private static $environments = array(
        'SHIPANYDEV' => 'dev1',
        'SHIPANYDEMO' => 'demo1',
        'SHIPANYSBX1' => 'sbx1',
        'SHIPANYSBX2' => 'sbx2'
    );

    private static $instance = null;

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new ShipanyHelper();
        }
        return self::$instance;
    }

    public static function getRegions($beta = false) {
        $region = static::$region;
        if (!$beta && isset($region[1])) {
            unset($region[1]);
        }
        return $region;
    }

    public static function extractApiKeyEnvironment($api_key_temp) {
        foreach (self::$environments as $key => $value) {
            if (strpos($api_key_temp, $key) !== false) {
                return array(
                    'env' => $value,
                    'api-tk' => str_replace($key, "", $api_key_temp)
                );
            }
        }
        return array(
            'env' => '',
            'api-tk' => str_replace($key, "", $api_key_temp)
        );
    }

    public static function getApiUrlAndRealTk($interface, $api_key_temp, $region_idx = 0) {
        $env = '';
        $region = '';
        if (!in_array($interface, ['api', 'portal'])) {
            $interface = 'api';
        }

        $result = static::extractApiKeyEnvironment($api_key_temp);
        $api_key_temp = $result['api-tk'];
        $env = $result['env'] ? '-' . $result['env'] : $env;

        // sometime $region_idx is string or int, sometime is array of string or int, if it is array, we need to loop it, if hit at least one, then set region to that value
        if (is_array($region_idx)) {
            foreach ($region_idx as $idx) {
                if ($idx == 1) {
                    $region = "-sg";
                    break;
                } else if ($idx == 2) {
                    $region = "-tw";
                    break;
                } else if ($idx == 3) {
                    $region = "-th";
                    break;
                }
            }
        } else if ($region_idx == 1) {
            $region = "-sg";
        } else if ($region_idx == 2) {
            $region = "-tw";
        } else if ($region_idx == 3) {
            $region = "-th";
        }

        return array(
            "url" => "https://{$interface}{$region}{$env}.shipany.io/",
            "api-tk" => $api_key_temp
        );
    }

    protected static function convert_wc_weight_to_kg($x) {
        $weight_units = get_option('woocommerce_weight_unit', 'kg');
        if (!is_numeric($x)) {
            $x = 0;
        }
        $x = floatval($x);
        return weight_convert($x, $weight_units);
    }

    /**
     * Build the payload for the order creation
     * @param mixed $wc_order_id
     * @param array $shipany_data {
     *    @type string $mode - query or create, default is query
     *    @type string $cour_uid
     *    @type string $cour_svc_pl
     *    @type string $storage_type
     *    @type bool $paid_by_rcvr
     *    @type bool $auto
     * 
     * }
     * @return array
     * $shipany_data = 
     *  
     *  
     *  
     */
    public static function build_shipany_order_payload($wc_order_id, $shipany_data = array()) {
        $shipany_data = array_merge(
            array(
                'mode' => 'query',
                'cour_uid' => '',
                'cour_svc_pl' => '',
                'storage_type' => static::get_settings('set_default_storage_type'),
                'paid_by_rcvr' => static::get_settings('shipany_paid_by_rec') == 'yes',
                'self_drop_off' => static::get_settings('shipany_self_drop_off') == 'yes',
                'auto' => false,
                'add-ons' => array(), // for lalamove, keep the structure of the add-ons
                'package_weight' => 0,
            ),
            $shipany_data
        );
        if (!in_array($shipany_data['mode'], ['query', 'create'])) {
            $shipany_data['mode'] = 'query';
        }
        $wc_order = wc_get_order($wc_order_id);

        $merchant_info = static::get_merchant_info();
        $sndr_ctc['ctc'] = json_decode(json_encode($merchant_info->co_info->ctc_pers[0]->ctc), true);
        $sndr_ctc['addr'] = json_decode(json_encode($merchant_info->co_info->org_ctcs[0]->addr), true);
        $avaliable_couriers = json_decode(json_encode(array_filter(static::get_couriers(), function ($courier) use ($merchant_info) {
            return in_array($courier->uid, $merchant_info->desig_cours);
        })), true);

        $courier_svc_pl = false;
        if (empty($shipany_data['cour_uid'])) {
            $shipany_data['cour_svc_pl'] = '';
        } else {
            $courier = array_filter($avaliable_couriers, function ($courier) use ($shipany_data) {
                return $courier["uid"] == $shipany_data['cour_uid'];
            });
            if (empty($courier)) {
                $shipany_data['cour_uid'] = '';
                $shipany_data['cour_svc_pl'] = '';
            } else {
                $courier = array_values($courier)[0];
                if (!empty($shipany_data['cour_svc_pl'])) {
                    $courier_svc_pl = array_filter($courier["cour_svc_plans"], function ($svc_pl) use ($shipany_data) {
                        return $svc_pl["cour_svc_pl"] == $shipany_data['cour_svc_pl'];
                    });
                    if (empty($courier_svc_pl)) {
                        $shipany_data['cour_svc_pl'] = '';
                    } else {
                        if (isset($courier_svc_pl['is_intl']) && $courier_svc_pl['is_intl']) {
                            if (!isset($courier_svc_pl['use_pickup_info']) || $courier_svc_pl['use_pickup_info']) {
                                $self_cour_conf = json_decode(json_encode(array_filter($merchant_info->configs->desig_cours, function ($cour) use ($shipany_data) {
                                    return $cour->cour_uid == $shipany_data['cour_uid'];
                                })[0]), true);
                                if (!$self_cour_conf) {
                                    $shipany_data['cour_uid'] = '';
                                    $shipany_data['cour_svc_pl'] = '';
                                } else {
                                    if (isset($self_cour_conf['pickup_info_ids']) && $self_cour_conf['pickup_info_ids']) {
                                        $pickup_info_id = $self_cour_conf['pickup_info_ids'][0];
                                    }
                                    // $obj.co_info.pickup_info
                                    $sndr_ctc = json_decode(json_encode(array_filter($merchant_info->co_info->pickup_info, function ($pickup_info) use ($pickup_info_id) {
                                        return $pickup_info->addr_ctc_uid == $pickup_info_id;
                                    })[0]), true);
                                }
                            }
                        }

                        // Please keep it for more easy to read
                        // $courier_svc_pl['collect_from_door'] // if not isset, default to true
                        // $courier_svc_pl['self_drop_off'] // if not isset, default to false
                        // if $courier_svc_pl['collect_from_door'] is false and $courier_svc_pl['self_drop_off'] is true, then $data['self_drop_off'] = true;
                        if (!isset($courier_svc_pl['collect_from_door']) || $courier_svc_pl['collect_from_door']) {
                            $shipany_data['self_drop_off'] = false;
                        } else {
                            if (!isset($courier_svc_pl['self_drop_off']) || !$courier_svc_pl['self_drop_off']) {
                                $shipany_data['self_drop_off'] = false;
                            } else {
                                $shipany_data['self_drop_off'] = true;
                            }
                        }

                    }
                }
            }
        }

        if (!isset($shipany_data['description'])) {
            $shipany_data['description'] = $wc_order->get_customer_note();
        }

        // temp fix for hkpost
        if (in_array($shipany_data['cour_uid'], [
            '93562f05-0de4-45cb-876b-c1e449c09d77', '83b5a09a-a446-4b61-9634-5602bf24d6a0', 'd8b52c46-53fc-49e2-8dbf-5b64c7f03b05', '167ba23f-199f-41eb-90b9-a231f5ec2436', // HKPOST
            '0ba0c102-4fb1-4266-ac1e-83487705adcb', // TWN DEMO1 FamilyMart
            'ffd0ac4e-1493-484c-9026-7b7847d93983', // TWN PROD1 FamilyMart
        ])) {
            $shipany_data['self_drop_off'] = true;
        }

        // Consider to pick out as a function
        $items = array();
        $items_value_total = 0;
        foreach ($wc_order->get_items() as $item) {
            $product = wc_get_product($item['product_id']);
            $item_value = $item['line_total'] / $item['qty'];
            if (empty($product) || $product->is_virtual()) {
                continue;
            }
            $item_weight = 0;
            $name = $product->get_title();
            $attributes = '';
            if (!empty($item['variation_id'])) {
                $product_variation = wc_get_product($item['variation_id']);
                if (empty($product_variation) || $product_variation->is_virtual()) {
                    continue;
                }
                $sku = $product_variation->get_sku();
                if (empty($sku)) {
                    $sku = $item['variation_id'];
                }
                if (empty($new_item['item_value'])) {
                    $new_item['item_value'] = $product_variation->get_price();
                }
                $item_weight = $product_variation->get_weight();
                $product_attribute = wc_get_product_variation_attributes($item['variation_id']);
                $name .= current($product_attribute) ? (' : ' . current($product_attribute)) : '';
                if (static::get_settings('shipany_send_product_attrs_to_shipany') == 'yes') {
                    $attributes = implode(" ; ", array_map(function ($x) {
                        return $x->key . ': ' . $x->value;
                    }, array_values($item->get_all_formatted_meta_data(''))));
                }
            } else {
                // place 'sku' in a variable before validating using 'empty' to be compatible with PHP v5.4
                $sku = $product->get_sku();
                if (empty($sku)) {
                    $sku = $item['product_id'];
                }
                if (empty($new_item['item_value'])) {
                    $new_item['item_value'] = $product->get_price();
                }
                $item_weight = $product->get_weight();
            }
            $sa_item_weight = floatval(static::convert_wc_weight_to_kg($item_weight));
            $items_value_total += floatval($item_value) * floatval($item['qty']);
            $item_array = array(
                "sku" => "" . $sku,
                "name" => $name,
                "descr" => $attributes,
                "typ" => "",
                "ori" => "",
                "unt_price" => array(
                    "val" => empty($item_value) ? 0.01 : floatval($item_value),
                    "ccy" => $wc_order->get_currency()
                ),
                "qty" => floatval($item['qty']),
                "wt" => array(
                    "val" => $sa_item_weight > 0 ? $sa_item_weight : 0,
                    "unt" => "kg"
                ),
                "dim" => array(
                    "len" => floatval($product->get_length()) ?: 1,
                    "wid" => floatval($product->get_width()) ?: 1,
                    "hgt" => floatval($product->get_height()) ?: 1,
                    "unt" => "cm"
                ),
                "stg" => $shipany_data['storage_type'] ?: 'Normal'
            );
            array_push($items, $item_array);
        }

        $alpha_three_country_code = CommonUtils::convert_country_code($wc_order->get_shipping_country());
        $shipping_address = $wc_order->get_address('shipping');
        $shipping_address['email'] = $wc_order->get_billing_email();
        if(!$shipping_address['phone']){
            $shipping_address['phone'] = $wc_order->get_billing_phone();
        }
        // If not USA or Australia, then change state from ISO code to name
        if ($shipping_address['country'] != 'US' && $shipping_address['country'] != 'AU') {
            // Get all states for a country
            $states = WC()->countries->get_states($shipping_address['country']);

            // If the state is empty, it was entered as free text
            if (!empty($states) && !empty($shipping_address['state'])) {
                // Change the state to be the name and not the code
                $shipping_address['state'] = $states[$shipping_address['state']];

                // Remove anything in parentheses (e.g. TH)
                $ind = strpos($shipping_address['state'], " (");
                if (false !== $ind) {
                    $shipping_address['state'] = substr($shipping_address['state'], 0, $ind);
                }
            }
        }

        $wrong_rcvr_addr_format = static::get_settings('shipany_region') == 0;

        $calced_weight = static::calculate_order_weight($wc_order);
        $shipany_data['package_weight'] = static::convert_wc_weight_to_kg($shipany_data['package_weight']);
        if($shipany_data['package_weight'] < $calced_weight) {
            $shipany_data['package_weight'] = $calced_weight;
        }

        $lst_states = WC()->countries->get_states($shipping_address['country']);
        if($lst_states && isset($lst_states[$shipping_address['state']]) && $lst_states[$shipping_address['state']]){
            $shipping_address['state'] = $lst_states[$shipping_address['state']];
        }

        $payload = array(
            "self_drop_off" => $shipany_data['self_drop_off'],
            "cour_svc_pl" => $shipany_data['cour_svc_pl'],
            "cour_uid" => $shipany_data['cour_uid'],
            'mch_uid' => $merchant_info->uid,
            "add-ons" => $shipany_data['add-ons'],
            'paid_by_rcvr' => $shipany_data['paid_by_rcvr'],
            'stg' => $shipany_data['storage_type'] ?: 'Normal',
            'order_from' => "woocommerce",
            'woocommerce_default_create' => $shipany_data['auto'],
            'ext_order_ref' => $wc_order->get_order_number() . (static::get_settings("shipany_customize_order_id") ? static::get_settings("shipany_customize_order_id") : ''),
            "wt" => array(
                "val" => floatval($shipany_data['package_weight']) ?: 1,
                "unt" => "kg"
            ),
            "dim" => array(
                "len" => count($items) > 1 ? 1 : (floatval($items[0]['dim']['len']) ?: 1),
                "wid" => count($items) > 1 ? 1 : (floatval($items[0]['dim']['wid']) ?: 1),
                "hgt" => count($items) > 1 ? 1 : (floatval($items[0]['dim']['hgt']) ?: 1),
                "unt" => 'cm'
            ),
            "items" => $items,
            "mch_ttl_val" => array(
                "val" => $items_value_total,
                "ccy" => $wc_order->get_currency()
            ),
            'cour_ttl_cost' => array(
                'val' => 1,
                'ccy' => $wc_order->get_currency()
            ),
            "sndr_ctc" => $sndr_ctc,
            "rcvr_ctc" => array(
                "ctc" => array(
                    "tit" => "",
                    "f_name" => $shipping_address['first_name'],
                    "l_name" => $shipping_address['last_name'],
                    "phs" => array(
                        array(
                            "typ" => "Mobile",
                            'cnty_code' => str_replace('+', '', WC()->countries->get_country_calling_code($wc_order->get_shipping_country())),
                            'num' => $shipping_address["phone"]
                        )
                    ),
                    'email' => $shipping_address['email'],
                    "note" => "",
                    "co_name" => isset($shipping_address['company']) ? $shipping_address['company'] : ''
                ),
                "addr" => array(
                    "typ" => "Residential",
                    "ln" => $shipping_address["address_1"],
                    "ln2" => isset($shipping_address["address_2"]) ? $shipping_address["address_2"] : '',
                    "ln3" => "",
                    "distr" => $wrong_rcvr_addr_format ? (isset($shipping_address["district"]) ? $shipping_address["district"] : '') : $shipping_address["city"],
                    "cnty" => $alpha_three_country_code,
                    'state' => $wrong_rcvr_addr_format ? '' : (static::get_settings('shipany_region') == 2 ? '' : $shipping_address["state"]),
                    "city" => $wrong_rcvr_addr_format ? $shipping_address["city"] : $shipping_address["state"],
                    "zc" => $shipping_address["postcode"]
                )
            )
        );

        if (strval($wc_order->get_order_number()) != strval($wc_order_id)) {
            $payload['ext_order_id'] = strval($wc_order_id);
        }

        if ($shipany_data["description"] != " ") {
            $payload['mch_notes'] = array($shipany_data["description"]);
        }

        if ($shipany_data['mode'] == 'create') {
            // $payload is passed by reference
            static::build_for_create_order($payload, $wc_order, $shipany_data);
        }

        return \SHIPANY\Utils\Args_Parser::unset_empty_values($payload);
    }

    public static function build_for_create_order(&$payload, $wc_order, $shipany_data) {
        $couruer_service_plan = '';
        $shipany_label_items = $wc_order->get_meta('_pr_shipment_shipany_label_items');
        if (!empty($shipany_label_items[0]['pr_shipany_couier_service_plan'])) {
            // UPS, lalamove
            $couruer_service_plan = json_decode($shipany_label_items[0]['pr_shipany_couier_service_plan']);
            $payload['cour_ttl_cost'] = array('ccy' => $couruer_service_plan->cour_ttl_cost->ccy, 'val' => $couruer_service_plan->cour_ttl_cost->val);
            $payload['cour_svc_pl'] = $couruer_service_plan->cour_svc_pl;
            $payload['cour_type'] = $couruer_service_plan->cour_type;

            if (in_array($payload['cour_uid'], $GLOBALS['Courier_uid_mapping']['Zeek'])) {
                $payload['ext_cl_mch_id'] = $couruer_service_plan->ext_cl_mch_id;
                $payload['rcvr_ctc']['addr']['gps']['long'] = explode(',', $couruer_service_plan->recipient_location)[1];
                $payload['rcvr_ctc']['addr']['gps']['lat'] = explode(',', $couruer_service_plan->recipient_location)[0];
            }

            if (!empty($couruer_service_plan->quot_uid)) {
                $payload['quot_uid'] = $couruer_service_plan->quot_uid;
            }
        } else {
            if ($shipany_data['auto'] == true && strpos(static::get_settings("shipany_default_courier_additional_service"), 'Lalamove') != false) {
                $payload["cour_svc_pl"] = static::get_settings("shipany_default_courier_additional_service");
                $query_rate_list = static::query_rate($payload, array('cour-uid' => static::get_settings("shipany_default_courier")));
            } else if ($shipany_data['auto'] == true && in_array(static::get_settings("shipany_default_courier"), $GLOBALS['Courier_uid_mapping']['Zeek'])) {
                $query_rate_list = static::query_rate($payload, array('cour-uid' => static::get_settings("shipany_default_courier")));
                $payload['ext_cl_mch_id'] = $query_rate_list[0]->ext_cl_mch_id;
                $payload['rcvr_ctc']['addr']['gps']['long'] = explode(',', $query_rate_list[0]->recipient_location)[1];
                $payload['rcvr_ctc']['addr']['gps']['lat'] = explode(',', $query_rate_list[0]->recipient_location)[0];
                $payload['cour_svc_type'] = 3;
                $payload['pod_type'] = 3;
            } else {
                $query_rate_list = static::query_rate($payload, array());
            }
            foreach ($query_rate_list as $query_rate) {
                if ($query_rate->cour_uid == $payload['cour_uid']) {
                    $payload['cour_ttl_cost'] = array('ccy' => $query_rate->cour_ttl_cost->ccy, 'val' => $query_rate->cour_ttl_cost->val);
                    $payload['cour_svc_pl'] = $query_rate->cour_svc_pl;
                    // $payload['cour_svc_type'] = $query_rate->cour_svc_type;
                    $payload['cour_type'] = $query_rate->cour_type;
                    if (!empty($query_rate->quot_uid)) {
                        $payload['quot_uid'] = $query_rate->quot_uid;
                    }
                    break;
                }
            }
        }

        if (in_array($payload['cour_uid'], $GLOBALS['Courier_uid_mapping']['Zeek'])) {
            $payload['cour_svc_type'] = 3;
            $payload['pod_type'] = 3;
        }
    }

    public static function get_merchant_info() {
        if (isset(static::$cache['merchant_info']) && static::$cache['merchant_info']) {
            return static::$cache['merchant_info'];
        }
        $response = static::getInstance()->api_client->get('merchants/self/');
        $merchant_info = $response->body->data->objects[0];
        static::$cache['merchant_info'] = $merchant_info;
        return $merchant_info;
    }

    public static function get_couriers() {
        if (isset(static::$cache['couriers']) && static::$cache['couriers']) {
            return static::$cache['couriers'];
        }
        $response = static::getInstance()->api_client->get('couriers/');
        if ($response->status == 401) {
            return false;
        }
        if ($response->status != 200) {
            return [];
        }
        $courier_list = $response->body->data->objects;
        static::$cache['couriers'] = $courier_list;
        return $courier_list;
    }

    public static function get_courier_service_location() {
        if (isset(static::$cache['courier_service_location']) && static::$cache['courier_service_location']) {
            return static::$cache['courier_service_location'];
        }
        $response = static::getInstance()->api_client->get('courier-service-location/published-locations');
        if ($response->status != 200) {
            return [];
        }
        $courier_service_location_list = $response->body->data->objects;
        static::$cache['courier_service_location'] = $courier_service_location_list;
        return $courier_service_location_list;
    }

    public static function get_courier_service_point_locations() {
        if (isset(static::$cache['courier_service_point_locations']) && static::$cache['courier_service_point_locations']) {
            return static::$cache['courier_service_point_locations'];
        }
        $response = static::getInstance()->api_client->get('courier-service-point-locations');
        update_option('woocommerce_shipany_service_locations_forbidden', false);
        if ($response->status != 200) {
            if ($response->status == 403) {
                update_option('woocommerce_shipany_service_locations_forbidden', true);
            }
            return [];
        }
        $courier_service_point_locations_list = $response->body->data->objects;
        static::$cache['courier_service_point_locations'] = $courier_service_point_locations_list;
        return $courier_service_point_locations_list;
    }

    public static function query_rate($payload, $header) {
        $request_headers = array(
            // "cour-uid" => isset($payload['cour_uid']) ? $payload['cour_uid'] : ''
        );
        // if (empty($request_headers["cour-uid"])) {
        //     $request_headers["cour-uid"] = $header['cour-uid'];
        // }
        $response = static::getInstance()->api_client->post('couriers-connector/query-rate/', $payload, $request_headers);
        if ($response->status === 200) {
            // the trick here we need to consider to empty rate return but have error
            if (isset($response->body->data) && isset($response->body->data->objects) && count($response->body->data->objects) && isset($response->body->data->objects[0]->quots)) {
                $quots = $response->body->data->objects[0]->quots; //$response->body->data->objects[0]->quots[0]->quot_uid
                if (!empty($quots)) {
                    return $quots;
                }
            }
            if (count($response->body->result->details) > 0) {
                return $response->body->result->details[0];
            } else {
                return $response->body->result->descr;
            }
        }
    }

    public static function create_order($payload, $wc_order_id) {
        $wc_order = wc_get_order($wc_order_id);
        $response = static::_create_order($payload);
        if ($response == false) {
            return false;
        }
        $response_object = $response->body->data->objects[0];

        $shipment_id = $response_object->uid;
        $lab_url = $response_object->lab_url;
        if ($lab_url != "") {
            $response = wp_remote_get($lab_url, array('sslverify' => false));
            $label_pdf_data = wp_remote_retrieve_body($response);
        } else {
            $label_pdf_data = "";
        }

        if ($label_pdf_data != "") {
            static::getInstance()->shipany_obj->save_shipany_label_file('item', $shipment_id, $label_pdf_data);
        }


        if ($response_object->pay_stat == 'Insufficient balance' || $response_object->pay_stat == 'Insufficient Credit') {
            $wc_order->update_meta_data('_pr_shipment_shipany_order_state', 'Order_Drafted');
            $wc_order->save();
            sprintf(__('Failed to create ShipAny order: %s', 'shipany'), 'NO CREDIT');
            return array(
                'label_path' => static::getInstance()->shipany_obj->get_shipany_label_file_info('item', $shipment_id)->path,
                //'label_path' 			=> $lab_url,
                'shipment_id' => $shipment_id,
                'tracking_number' => $shipment_id,
                'tracking_status' => '',
                'insufficient_balance' => true,
                'courier_service_plan' => __($response_object->cour_svc_pl, 'shipany') . '-' . static::better_wc_price($response_object->cour_ttl_cost->val, $response_object->cour_ttl_cost->ccy),
                'asn_id' => $response_object->asn_id
            );
        } else if ($response_object->ext_order_not_created == 'x') {
            $wc_order->update_meta_data('_pr_shipment_shipany_order_state', 'Order_Drafted');
            $wc_order->save();
            $response_details = $response->body->result->details[0];
            sprintf(__('Failed to create ShipAny order: %s', 'shipany'), 'ERROR');
            return array(
                'label_path' => static::getInstance()->shipany_obj->get_shipany_label_file_info('item', $shipment_id)->path,
                //'label_path' 			=> $lab_url,
                'shipment_id' => $shipment_id,
                'tracking_number' => $shipment_id,
                'tracking_status' => '',
                'courier_service_plan' => __($response_object->cour_svc_pl, 'shipany') . '-' . static::better_wc_price($response_object->cour_ttl_cost->val, $response_object->cour_ttl_cost->ccy),
                'asn_id' => $response_object->asn_id,
                'ext_order_not_created' => $response_object->ext_order_not_created,
                'response_details' => $response_details
            );
        } else {
            $wc_order->update_meta_data('_pr_shipment_shipany_order_state', 'Order_Created');
            $wc_order->save();
        }
        return array(
            'label_path' => static::get_shipany_label_file_info($shipment_id)->path,
            'label_path_s3' => $lab_url,
            'shipment_id' => $shipment_id,
            'tracking_number' => $shipment_id,
            'tracking_status' => '',
            'courier_service_plan' => __($response_object->cour_svc_pl, 'shipany') . ' - ' . static::better_wc_price($response_object->cour_ttl_cost->val, $response_object->cour_ttl_cost->ccy),
            'asn_id' => $response_object->asn_id,
            'courier_tracking_number' => $response_object->trk_no,
            'courier_tracking_url' => $response_object->trk_url,
            'commercial_invoice_url' => $response_object->comm_invoice_url
        );
    }

    public static function get_shipany_label_file_info($shipany_order_uid) {
        return static::getInstance()->shipany_obj->get_shipany_label_file_info('item', $shipany_order_uid);
    }

    private static function _create_order($payload) {
        $auto = isset($payload['woocommerce_default_create']) && $payload['woocommerce_default_create'];
        $response = static::getInstance()->api_client->post('orders/ ', $payload);
        if ($response->status === 201 || $response->status === 200) {
            return $response;
        }
        if ($response->status === 403 && isset($response->body->data->objects[0]->uid)) {
            return static::getInstance()->shipany_obj->api_client->get_order_info($response->body->data->objects[0]->uid);
        }
        if ($auto == true) {
            return false;
        }
        throw new Exception(
            sprintf(
                __('Failed to create ShipAny order: %s', 'shipany'),
                static::getInstance()->shipany_obj->api_client->generate_error_details($response)
            )
        );
    }

    public static function get_supported_storage_types($cour_uid) {
        $merchant_info = static::get_merchant_info();
        $couriers = static::get_couriers();
        $courier = array_filter($couriers, function ($courier) use ($cour_uid) {
            return $courier->uid == $cour_uid;
        });
        if (empty($courier)) {
            return [];
        }
        $courier = json_decode(json_encode(array_values($courier)[0]), true);

        $desig_cour = array_filter($merchant_info->configs->desig_cours, function ($courier) use ($cour_uid) {
            return $courier->cour_uid == $cour_uid;
        });
        $desig_cour_cour_svc_plans = json_decode(json_encode(array_values($desig_cour)[0]->extras->cour_svc_plans), true);
        $avaliable_courier_svc_pl = array_filter($courier["cour_svc_plans"], function ($svc_pl) use ($desig_cour_cour_svc_plans) {
            return $desig_cour_cour_svc_plans[$svc_pl["cour_svc_pl_act"]]['enbl'];
        });
        $avaliable_courier_svc_pl = array_map(function ($svc_pl) use ($courier) {
            if (isset($svc_pl['supported_storage_types']) && $svc_pl['supported_storage_types']) {
                return $svc_pl['supported_storage_types'];
            }
            if (isset($courier['cour_props']['delivery_services']['supported_storage_types']) && $courier['cour_props']['delivery_services']['supported_storage_types']) {
                return $courier['cour_props']['delivery_services']['supported_storage_types'];
            }
            return ['Normal'];
        }, $avaliable_courier_svc_pl);
        // flatten the array
        $supported_storage_types = array_reduce($avaliable_courier_svc_pl, function ($carry, $item) {
            return array_merge($carry, $item);
        }, []);
        $supported_storage_types = array_unique($supported_storage_types);
        return array_values($supported_storage_types);
    }

    public static function get_settings($key = '') {
        $settings = get_option('woocommerce_shipany_ecs_asia_settings');
        return isset($settings[$key]) ? $settings[$key] : null;
    }

    protected static function calculate_order_weight($wc_order) {
        $order_id = $wc_order->get_id();
        if (static::get_settings("default_weight") == 'yes') {
            $total_weight = 1;
        } else {
            $total_weight = 0;
            if (false === $wc_order) {
                return apply_filters('shipping_shipany_order_weight', $total_weight, $order_id);
            }
            $ordered_items = $wc_order->get_items();
            if (is_array($ordered_items) && count($ordered_items) > 0) {
                foreach ($ordered_items as $key => $item) {
                    if (!empty($item['variation_id'])) {
                        $product = wc_get_product($item['variation_id']);
                    } else {
                        $product = wc_get_product($item['product_id']);
                    }
                    if ($product) {
                        $product_weight = $product->get_weight();
                        if ($product_weight) {
                            $total_weight += ($item['qty'] * $product_weight);
                        }
                    }
                }
            }

            $total_weight = static::convert_wc_weight_to_kg($total_weight);
            if (!empty(static::get_settings('shipany_add_weight'))) {
                if (static::get_settings('shipany_add_weight_type') == 'absolute') {
                    $total_weight += static::get_settings('shipany_add_weight');
                } elseif (static::get_settings('shipany_add_weight_type') == 'percentage') {
                    $total_weight += $total_weight * (static::get_settings('shipany_add_weight') / 100);
                }
            }
        }

        return apply_filters('shipping_shipany_order_weight', $total_weight, $order_id);
    }

    public static function get_cloudflare_url(string $url, bool $is_gzip = true) {
        $obj = parse_url($url);
        if (!$obj) {
            return $url;
        }
        if (static::extractApiKeyEnvironment(static::get_settings('shipany_api_key'))['env'] !== '') {
            return $url;
        }
        $cloudflare_url_mapping = [
            'hk' => 'pickup-location-list.shipany.io',
            'tw' => 'pickup-location-list-tw.shipany.io'
        ];

        if (isset($obj['path']) && strpos($obj['path'], '/location/tw') === 0) {
            $obj['host'] = $cloudflare_url_mapping['tw'];
            $obj['path'] = substr($obj['path'], strlen('/location/tw'));
        } else {
            $obj['host'] = $cloudflare_url_mapping['hk'];
            $obj['path'] = substr($obj['path'], strlen('/location'));
        }
        $link = static::unparse_url($obj);
        if ($is_gzip) {
            $link = str_replace('.json', '.json.gz', $link);
        }
        return $link;
    }

    protected static function unparse_url($parsed_url) {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }


    public static function get_wc_payments_localization_service() {
        if (static::$wc_payments_localization_service == null && class_exists('WC_Payments_Localization_Service')) {
            static::$wc_payments_localization_service = new \WC_Payments_Localization_Service();
        }
        return static::$wc_payments_localization_service;
    }

    public static function better_wc_price($price, $currency = '', $html_format = false) {
        if (empty($currency)) {
            $currency = get_woocommerce_currency();
        }
        if (static::get_wc_payments_localization_service() && class_exists('WC_Payments_Utils')) {
            $opt = static::get_wc_payments_localization_service()->get_currency_format($currency);
            $result = wc_price($price, array(
                'currency' => $currency,
                'decimal_separator' => $opt['decimal_sep'],
                'thousand_separator' => $opt['thousand_sep'],
                'decimals' => $opt['num_decimals'],
                'price_format' => \WC_Payments_Utils::get_woocommerce_price_format($opt['currency_pos'])
            ));
        } else {
            $result = wc_price($price, array('currency' => $currency));
        }
        if (!$html_format) {
            // remove all html tags
            $result = strip_tags($result);
            // turn back the html entities // unknow reason, but it works
            $result = html_entity_decode($result);
        }
        return $result;
    }

    public static function get_latest_locker_list($default_courier_id = '', $force = false) {
        // load shipany location list in to cache file
        $write_permission_enough = true;
        $shipping_shipnay_settings = SHIPANY()->get_shipping_shipany_settings();
        if (empty($default_courier_id)) {
            $default_courier_id = $shipping_shipnay_settings['shipany_default_courier'];
        }
        if (!file_exists(ABSPATH . '/wp-content/plugins/shipany/cache')) {
            if (!mkdir(ABSPATH . '/wp-content/plugins/shipany/cache', 0777, true)) {
                // I would like to throw an error here, but it will break the checkout page, and the client f**k us up
                // die('Failed to create folders...');
                $write_permission_enough = false;
            }
        } else {
            if (!is_writable(ABSPATH . '/wp-content/plugins/shipany/cache')) {
                // I would like to throw an error here, but it will break the checkout page, and the client f**k us up
                // die('Failed to create folders...');
                if (!chmod(ABSPATH . '/wp-content/plugins/shipany/cache', 0644)) {
                    $write_permission_enough = false;
                }
            }
        }
        update_option('woocommerce_shipany_write_permission_enough', $write_permission_enough ? 'true' : 'false');
        $lock_for = $default_courier_id; // USE BELOW IF NEED MULTIPLE COURIER
        // $lock_for = $shipping_shipnay_settings['shipany_region'];

        if ($write_permission_enough) {
            $last_updated_file_path = ABSPATH . '/wp-content/plugins/shipany/cache/location-last-updated-' . $lock_for . '.json';

            $everyday_force_update_time = isset($shipping_shipnay_settings['shipany_everyday_force_update_time']) ? $shipping_shipnay_settings['shipany_everyday_force_update_time'] : "06:00:00";
            $last_updated = file_exists($last_updated_file_path) ? json_decode(file_get_contents($last_updated_file_path), true) : null;

            $last_updated = $last_updated && isset($last_updated['last_updated']) ? ($last_updated['last_updated'] + (8 * 60 * 60)) : null;
            $now_datetime = time() + 8 * 60 * 60;
            $force_update_time = strtotime(date('Y-m-d', time() + 8 * 60 * 60) . ' ' . $everyday_force_update_time);
        } else {
            $last_updated = false;
        }
        $locker = @file_get_contents(ABSPATH . '/wp-content/plugins/shipany/cache/location-' . $lock_for . '.json');
        $service_locations_forbidden = get_option('woocommerce_shipany_service_locations_forbidden') || $force;
        if ($service_locations_forbidden || !$locker || !$last_updated ||
            ($last_updated < $force_update_time && ($now_datetime >= $force_update_time || $now_datetime - $last_updated > 60 * 60 * 24))) {
            $locker = '[]';
            if ($write_permission_enough) {
                $last_updated_file = fopen($last_updated_file_path, 'w');
            }
            if (!$write_permission_enough || flock($last_updated_file, LOCK_EX)) {
                $found = false;
                foreach ([
                    ['Utils\ShipanyHelper', 'get_courier_service_point_locations'],
                    ['Utils\ShipanyHelper', 'get_courier_service_location']
                ] as $func) {
                    $resp = call_user_func_array($func, []);
                    $service_locations_forbidden = get_option('woocommerce_shipany_service_locations_forbidden');
                    if ($service_locations_forbidden) {
                        break;
                    }
                    $couriers = json_decode(json_encode($resp), true);
                    foreach ($couriers as $courier) {
                        if ($courier['cour_uid'] == $default_courier_id) { // REMOVE IT IF NEED MULTIPLE COURIER
                            foreach ([ShipanyHelper::get_cloudflare_url($courier['url']), ShipanyHelper::get_cloudflare_url($courier['url'], false), // TODO: remove in future (get_cloudflare_url, gz = false)
                                $courier['url'],] as $link) {
                                $resp = wp_remote_get($link);
                                if (!is_wp_error($resp) && $resp['response']['code'] >= 200 && $resp['response']['code'] < 300) {
                                    $locker = $resp['body'];
                                    $blank_html = "<html>\n<body>\n</body>\n</html>\n";
                                    if($locker === $blank_html){
                                        $locker = '[]';
                                    }
                                    if (substr($link, -3) === '.gz') {
                                        $locker = gzdecode($locker);
                                    }
                                    if ($locker && $locker != '[]') {
                                        $found = true;
                                        if ($write_permission_enough) {
                                            $file = fopen(ABSPATH . '/wp-content/plugins/shipany/cache/location-' . $courier['cour_uid'] . '.json', 'w');
                                            fwrite($file, $locker);
                                            fclose($file);
                                        }
                                        break;
                                    }
                                }
                            }
                            break; // REMOVE IT IF NEED MULTIPLE COURIER
                        } // REMOVE IT IF NEED MULTIPLE COURIER
                    }
                    if ($found) {
                        break;
                    }
                }
                if($write_permission_enough){
                    if ($locker && $locker != '[]') {
                        // always store last updated time in UNIX timestamp int (UTC+0)
                        fwrite($last_updated_file, json_encode(array('last_updated' => strtotime(date('Y-m-d H:i:s')), 'readable' => date('Y-m-d H:i:s'))));
                        flock($last_updated_file, LOCK_UN);
                    } else {
                        $found = true;
                        if($now_datetime - $last_updated <= 60 * 60 * 24 * 7){ // within 7 days
                            $locker = @file_get_contents(ABSPATH . '/wp-content/plugins/shipany/cache/location-' . $lock_for . '.json');
                            if(!$locker){
                                $locker = '[]';
                            }
                        } else {
                            $locker = '[]';
                        }
                        fwrite($last_updated_file, json_encode(array('last_updated' => $last_updated - (8 * 60 * 60))));
                        flock($last_updated_file, LOCK_UN);
                    }
                }
                if(!$found){
                    $locker = false;
                }
            }
            if ($write_permission_enough) {
                fclose($last_updated_file);
            }
        }
        return $locker;
    }

    public static function get_tracking_note_by_order_id($wc_order_id) {
        $wc_order = wc_get_order($wc_order_id);
        $label_info = $wc_order->get_meta('_pr_shipment_shipany_label_tracking', true);
        if ($label_info != "") {
            return static::get_tracking_note(
                isset($label_info['tracking_number']) ? $label_info['tracking_number'] : $label_info['shipment_id'],
                $label_info['courier_tracking_number'],
                strtok($label_info['courier_service_plan'], '-'),
                $label_info['courier_tracking_url']
            );
        }
        return '';
    }

    public static function get_tracking_note($shipany_order_uid, $courier_tracking_no, $courier_name, $courier_url) {

        $tracking_note_txt = 'ShipAny Tracking Number:';
        if (!empty(static::get_settings('shipany_tracking_note_txt'))) {
            $tracking_note_txt = static::get_settings('shipany_tracking_note_txt');
        }
        if (empty($courier_tracking_no) || static::get_settings('show_courier_tracking_number_enable') == 'no') {
            $tracking_note = sprintf(__($tracking_note_txt . ' {tracking-link}', 'shipany'));
        } else {
            $tracking_note = sprintf(__($tracking_note_txt . ' {tracking-link}' . "\n" . $courier_name . 'Tracking Number: <a href="' . $courier_url . '" target="_blank">' . $courier_tracking_no . '</a>', 'shipany'));
        }

        $tracking_link = static::get_tracking_url($shipany_order_uid, 'html');

        if (empty($tracking_link)) {
            return '';
        }

        $tracking_note_new = str_replace('{tracking-link}', $tracking_link, $tracking_note, $count);
        if ($count == 0) {
            $tracking_note_new = $tracking_note . ' ' . $tracking_link;
        }

        return $tracking_note_new;
    }

    public static function get_tracking_url_by_order_id($wc_order_id, $output = '') {
        $wc_order = wc_get_order($wc_order_id);
        $label_info = $wc_order->get_meta('_pr_shipment_shipany_label_tracking', true);
        if (!empty($label_info)) {
            return static::get_tracking_url(
                isset($label_info['tracking_number']) ? $label_info['tracking_number'] : $label_info['shipment_id'],
                $output
            );
        }
        return '';
    }

    public static function get_tracking_url($shipany_order_uid, $output = '') {
        if (empty($shipany_order_uid))
            return '';
        $result = ShipanyHelper::getApiUrlAndRealTk('portal', static::get_settings('shipany_api_key'), static::get_settings('shipany_region'));
        $link = $result['url'] . 'tracking?id=' . $shipany_order_uid;
        if ($output == 'html') {
            return sprintf('<a href="%s" target="_blank">%s</a>', $link, $shipany_order_uid);
        }
        return $link;
    }

    public static function get_tracking_note_type() {
        if (static::get_settings('shipany_tracking_note') == 'yes') {
            return '';
        } else {
            return 'customer';
        }
    }

    public static function get_all_order_status() {
        return array(
            'Order Created' => __('Order Created', 'shipany'),
            'Delivery In Progress' => __('Delivery In Progress', 'shipany'),
            'Order Delivered' => __('Order Delivered', 'shipany'),
            'Failed To Deliver (Pending Retry)' => __('Failed To Deliver (Pending Retry)', 'shipany'),
            'Failed To Deliver Returning To Sender' => __('Failed To Deliver Returning To Sender', 'shipany'),
            'Returning In Progress' => __('Returning In Progress', 'shipany'),
            'Return Cancelled (Scheduling Next Delivery)' => __('Return Cancelled (Scheduling Next Delivery)', 'shipany'),
            'Order Returned' => __('Order Returned', 'shipany'),
            'Abnormal' => __('Abnormal', 'shipany'),
            'Arrived Transit Point' => __('Arrived Transit Point', 'shipany'),
            'Ready For Shipment' => __('Ready For Shipment', 'shipany'),
            'Collected By Courier' => __('Collected By Courier', 'shipany'),
            'Pickup Request Sent' => __('Pickup Request Sent', 'shipany'),
            'Order Drafted' => __('Order Drafted', 'shipany'),
            'Order Processing' => __('Order Processing', 'shipany'),
            'Preparing For Pickup' => __('Preparing For Pickup', 'shipany'),
            'Pickup Request Received' => __('Pickup Request Received', 'shipany'),
            'Ready For Delivery' => __('Ready For Delivery', 'shipany'),
            'Order Completed' => __('Order Completed', 'shipany'),
            'Order Cancelled' => __('Order Cancelled', 'shipany'),
            'Arrival' => __('Arrival', 'shipany'),
            'Returned' => __('Returned', 'shipany'),
            'Return Completed' => __('Return Completed', 'shipany'),
            'Ready For Pickup' => __('Ready For Pickup', 'shipany'),
            'Delivered To Locker' => __('Delivered To Locker', 'shipany'),
            'Delivering To Convenience Store' => __('Delivering To Convenience Store', 'shipany'),
            'Shipping' => __('Shipping', 'shipany'),
            'In Transit' => __('In Transit', 'shipany'),
            'Delivery Man Reached Destination Nearby' => __('Delivery Man Reached Destination Nearby', 'shipany'),
            'Delivery Man Reached Pickup Point' => __('Delivery Man Reached Pickup Point', 'shipany'),
            'Order Assigned To Delivery Man' => __('Order Assigned To Delivery Man', 'shipany'),
            'Delivered To Service Point' => __('Delivered To Service Point', 'shipany'),
            'Order Missorted' => __('Order Missorted', 'shipany'),
            'Collected By Customer' => __('Collected By Customer', 'shipany'),
            'Return To Warehouse' => __('Return To Warehouse', 'shipany'),
            'Waiting To Be Collected' => __('Waiting To Be Collected', 'shipany'),
            'Order Cancelled Before Collection' => __('Order Cancelled Before Collection', 'shipany'),
            'Custom Clearance In Progress' => __('Custom Clearance In Progress', 'shipany'),
            'Custom Clearance Release' => __('Custom Clearance Release', 'shipany'),
            'Departed Transit Point' => __('Departed Transit Point', 'shipany'),
            'Under Processing' => __('Under Processing', 'shipany')
        );
    }
}