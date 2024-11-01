<?php
use Utils\ShipanyHelper;

/**
 * @deprecated 1.1.42 // whole file
 */


// 'HK' => array( // Hong Kong states.
//     'HONG KONG'       => __( 'Hong Kong Island', 'woocommerce' ),
//     'KOWLOON'         => __( 'Kowloon', 'woocommerce' ),
//     'NEW TERRITORIES' => __( 'New Territories', 'woocommerce' ),
// ),

if (get_option('woocommerce_shipany_is_contain_location_list') === 'false') {
    echo '<script>window.locker = []; console.log("woocommerce_shipany_is_contain_location_list: false")</script>';
} else {
    $locker = ShipanyHelper::get_latest_locker_list();
    if (!$locker || $locker == '[]') {
        echo '<script>window.locker = undefined; console.log("woocommerce_shipany_is_contain_location_list: true");</script>';
    } else {
        echo '<script>window.locker = undefined;</script>';
    }
}
$k = uniqid();
$get_key = $k . hash('sha256', $k . 'shipany');
echo '<script>';
echo 'var wp_rest_nonce = "' . wp_create_nonce('wp_rest') . '";' . PHP_EOL;
echo 'var locationListEndpoint = "' . get_home_url() . '/wp-json/shipany/v1/get-latest-locker-list?k=' . wp_create_nonce('shipany_get_latest_locker_list') . '";' . PHP_EOL;
echo '</script>';

// FIXME: should not be here
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST["shippingAddress1"])) {


    // handle region checkout page region field empty
    // $region = 'HONG KONG';
    // if (wc_clean($_POST["region"]) !== 'Hongkong Island') {
    // if (wc_clean($_POST["region"]) == 'New Territories' || wc_clean($_POST["region"]) == 'Kowloon') {
    $region = strtoupper(wc_clean($_POST["region"]));
    // }

    $bypass_billing_address = SHIPANY()->get_shipping_shipany_settings()['shipany_bypass_billing_address'];
    $locker_length_truncate = SHIPANY()->get_shipping_shipany_settings()['shipany_locker_length_truncate'];

    $locate = get_locale();
    $is_chinese = strpos($locate, 'zh') === 0;
    if ($is_chinese) {
        $address1 = wc_clean($_POST["shippingAddress2"]);
    } else {
        $address1 = wc_clean($_POST["shippingAddress1"]);
    }
    //set_billing_state
    if ($bypass_billing_address != 'yes') {
        if ($locker_length_truncate > 15) {
            WC()->customer->set_billing_address_1(substr($address1, 0 , $locker_length_truncate));
        } else {
            WC()->customer->set_billing_address_1($address1);
        }
        WC()->customer->set_billing_city(wc_clean($_POST["district"]));
        WC()->customer->set_billing_country(SHIPANY()->get_shipping_shipany_settings()['shipany_region'] == 2 ? 'TW' : 'HK');
        WC()->customer->set_billing_state($region);
    }

    if ($locker_length_truncate > 15) {
        WC()->customer->set_shipping_address_1(substr($address1, 0 , $locker_length_truncate));
    } else {
        WC()->customer->set_shipping_address_1($address1);
    }
    WC()->customer->set_shipping_city(wc_clean($_POST["district"]));
    WC()->customer->set_shipping_country(SHIPANY()->get_shipping_shipany_settings()['shipany_region'] == 2 ? 'TW' : 'HK');
    WC()->customer->set_shipping_state($region);

    if (!get_option('shipany-selected-locationtype')) {
        add_option('shipany-selected-locationtype', wc_clean($_POST["locationType"]));
    } else {
        update_option('shipany-selected-locationtype', wc_clean($_POST["locationType"]));
    }

    if (!get_option('shipany-selected-locationID')) {
        add_option('shipany-selected-locationID', wc_clean($_POST['locationId']));
    } else {
        update_option('shipany-selected-locationID', wc_clean($_POST['locationId']));
    }

    WC()->customer->save();

    die();
}
?>
<div style='display:none !important'>
<style>
.outlineShipany {
    margin: 0;
    padding: 0;
    background-color: rgba(255,0,0,0);
    display: flex;
    flex-direction: column;
}

#firstRadio, #secondRadio, #fifthRadio, #sixthRadio {
    text-align: left;
    width: 100%;
}

@media only screen and (max-width: 767px) and (min-width: 442px) {
    .outlineShipany {
        flex-direction: row;
    }
}
.shipany-woo-plugin-modal .loader::before {
    background: unset !important;
}
</style>
</div>

<div class="outlineShipany">
    <div id="firstRadio" style="display:none">
        <input type="radio" id="shipany_locker_collect" name="shipany_locker_collect" value="shipany_locker_collect" onclick="on_change('shipany_locker')" <?php if(get_option('shipany-selected-locationtype') === 'SHIPANY_LOCKER'){echo "checked";}?>>
        <label for="shipany_locker_collect">
            <?php
            echo "Collect at Locker/Store";
            ?>
        </label>
        </input>
    </div>

    <div id="secondRadio" style="display:none">
        <input type="radio" id="shipany_locker_point" name="shipany_locker_point" value="shipany_locker_point" onclick="on_change('shipany_point')" <?php if(get_option('shipany-selected-locationtype') === 'SHIPANY_POINT'){echo "checked";}?>>
        <label for="shipany_locker_point">
            <?php
            echo "Collect at Store";
            ?>
        </label>
        </input>
    </div>
</div>

<div style='display:none !important'>
<script>
//inject mapbox 
var shipany_loader = document.createElement("div");
shipany_loader.className = "loader";
shipany_loader.id = "loader";
shipany_loader.style.display = "none";

var shipany_mapBoxWP = document.createElement("div");
shipany_mapBoxWP.className = "mapBox";
shipany_mapBoxWP.id = "mapBox";
//mapBoxWP.appendChild(loader);

var shipany_mapWrapper = document.createElement("div");
shipany_mapWrapper.className = "mapWrapper";
shipany_mapWrapper.id = "mapWrapper";
shipany_mapWrapper.appendChild(shipany_mapBoxWP);


var shipany_modalContainer = document.createElement("div");
shipany_modalContainer.className = "shipany-woo-plugin-modal notranslate";
shipany_modalContainer.id = "shipany-woo-plugin-modal";
shipany_modalContainer.appendChild(shipany_mapWrapper)
if (window.location.href.includes('checkout') || window.location.href.includes('basket') || window.location.href.includes('cart')) {
	shipany_modalContainer.appendChild(shipany_loader);
}

if (!document.getElementById("shipany-woo-plugin-modal")) {
    document.body.appendChild(shipany_modalContainer);
}
</script>
</div>

<div style='display:none !important'>
<script>

var shipany_selection = ""

var modal = document.getElementById("shipany-woo-plugin-modal");

function on_change(val) {
    val = val.toUpperCase();
    modal.classList.add("shipany-woo-plugin-showModal");
    if (val === 'SHIPANY_LOCKER') {
        document.getElementById('shipany_locker_collect').checked = true;
    } 
    initEasyWidget(val);
}
</script>
</div>


<div style='display:none !important'>
<script>
document.addEventListener("DOMContentLoaded", function(event) {
    let data;
    var getData = (arg) => {

        let dataContainer = document.getElementById("dataContainer");
        dataContainer.innerHTML = JSON.stringify(arg, censor(arg));
    };
    function censor(censor) {
        var i = 0;

        return function(key, value) {
            if (i !== 0 && typeof(censor) === 'object' && typeof(value) == 'object' && censor == value)
                return '[Circular]';

            if (i >= 29) // seems to be a harded maximum of 30 serialized objects?
                return '[Unknown]';

            ++i; // so we know we aren't using the original object anymore

            return value;
        }
    }
    const toggleShowData = () => {
        let dataContainer = document.getElementById("dataContainer");
        if (dataContainer.style.display === "none") {
            dataContainer.style.display = "block"
        } else {
            dataContainer.style.display = "none"
        }
    };
    jQuery(document).on('change', '.mapFilter', function() {
        jQuery( ".shipany-woo-plugin-modal .mapLeftWrapper #filterLoader" ).show();
        jQuery( ".shipany-woo-plugin-modal .mapLeftWrapper div.containerParentTextDiv" ).hide();
    });
});
</script>
</div>

<div style='display:none !important'>
<script>
function initEasyWidget(locationType) {
    var getData = (arg) => {
        jQuery.ajax({
            url: document.URL,
            type: "POST",
            processData: true,
            data: {
                shippingAddress1: arg.address1En,
                shippingAddress2: arg.address2En,
                locationId: arg.locationId,
                region: arg.province,
                district: arg.district,
                country: arg.country,
                locationType: arg.locationType
            },
            success: function(data) {
                console.log("data");
            }

        });
    }
    if (_mapType !== undefined) {
        easyWidget.reset({
            filter: {
                locationType: [locationType],
                onSelect: getData

            }
        })
    } else {
        //all properties are null at first
        easyWidget.init({
            mapType: "osm",
            defaultLocation: "HK",
            mode: "modal",
            locale: "en",
            /* userAuthObject:{
                username: "appit",
                password: "CFMah7f5dLYXuwQZ"
            },*/
            filter: {
                locationType: [locationType],

            },
            onSelect: getData
        });

    }

}

// call list cart page
// if (document.querySelector('[id^="shipping_method_0_local_pickup"]').checked && !window.location.href.includes('checkout')) {
//     on_change('shipany_locker');
// }
</script>
</div>