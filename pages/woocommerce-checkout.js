var __ = window.wp.i18n.__;
var createElement = window.wp.element.createElement;
var registerPlugin = window.wp.plugins.registerPlugin;
var ExperimentalOrderShippingPackages = window.wc.blocksCheckout.ExperimentalOrderShippingPackages;
var select = window.wp.data.select;
var useDispatch = window.wp.data.useDispatch;
var wcBlocksData = window.wc.wcBlocksData;

function trigger_list() {
    jQuery('.wc-proceed-to-checkout').css('pointer-events', 'none');
    jQuery('.wc-proceed-to-checkout').css('opacity', '0.5');
    setTimeout(function () {
        jQuery('.wc-proceed-to-checkout').css('pointer-events', '');
        jQuery('.wc-proceed-to-checkout').css('opacity', '1');
    }, 5000);
    on_change("SHIPANY_LOCKER")
}

// TODO: lock input field

if (window.location.href.includes('checkout') || window.location.href.includes('cart')) {
    createChangeLocationElement = function () {
        // jQuery('#shipany-woo-plugin-change-location').remove();
        let defaultLabelName = __('Change address', 'shipany');
        if (window?.shipany_setting?.shipany_enable_locker_list2_1) {
            defaultLabelName = shipany_setting.shipany_enable_locker_list2_1
        }

        return createElement('div', {
            id: 'shipany-woo-plugin-change-location',
            // onClick: trigger_list,
            style: {
                marginLeft: '6px',
                display: 'inline',
                cursor: 'pointer'
            }
        }, createElement('div', null,
            createElement('a', {
                style: {
                    cursor: 'pointer'
                },
                onClick: trigger_list
            }, defaultLabelName)
        ));
    }
}
/**
 * `locker_list_shown` is to prevent multiple locker list trigger
 *  - true is to prevent the first trigger when page loaded
 *  - false is to allow the first trigger when page loaded
 */
var first_render = true;
var locker_list_shown = false;
var setShippingAddress;
var setBillingAddress;
var __internalSetUseShippingAsBilling;
var getUseShippingAsBilling;
registerPlugin('sa-change-address-btn', {
    render: () => {
        if (jQuery('[data-block-name="woocommerce/checkout"]').length) {
            const _first_render = first_render;
            first_render = false;
            setShippingAddress = useDispatch(wcBlocksData.CART_STORE_KEY).setShippingAddress;
            setBillingAddress = useDispatch(wcBlocksData.CART_STORE_KEY).setBillingAddress;
            __internalSetUseShippingAsBilling = useDispatch(wcBlocksData.CHECKOUT_STORE_KEY).__internalSetUseShippingAsBilling;
            getUseShippingAsBilling = select(wcBlocksData.CHECKOUT_STORE_KEY).getUseShippingAsBilling;
            let shipping_rates = select(wcBlocksData.CART_STORE_KEY).getShippingRates();
            let cart_data = select(wcBlocksData.CART_STORE_KEY).getCartData();
            const shipping_address_line_1 = (cart_data && cart_data.shippingAddress && cart_data.shippingAddress.address_1) || '';
            shipping_rates = shipping_rates && shipping_rates[0] && shipping_rates[0].shipping_rates;
            if (shipping_rates) {
                const selected_shipping_method = shipping_rates.find(x => x.selected);
                if (selected_shipping_method.rate_id === shipping_rates.find(x => x.method_id.startsWith('local_pickup')).rate_id) {
                    if(!locker_list_shown && !shipping_address_line_1.startsWith('[')) {
                        locker_list_shown = true;
                        if(_first_render){
                            setTimeout(function(){
                                trigger_list();
                            }, 1000);
                        } else {
                            trigger_list();
                        }
                    }
                    return createElement(ExperimentalOrderShippingPackages, null, [
                        createChangeLocationElement(),
                    ]);
                } else {
                    locker_list_shown = false;
                }
            }
        }
    },
    scope: 'woocommerce-checkout',
});

let renderd = false;

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
shipany_modalContainer.className = "shipany-woo-plugin-modal";
shipany_modalContainer.id = "shipany-woo-plugin-modal";
shipany_modalContainer.appendChild(shipany_mapWrapper)
if (window.location.href.includes('checkout') || window.location.href.includes('basket') || window.location.href.includes('cart')) {
    shipany_modalContainer.appendChild(shipany_loader);
}

if (!document.getElementById("shipany-woo-plugin-modal")) {
    document.body.appendChild(shipany_modalContainer);
}

var shipany_selection = ""

var modal = document.getElementById("shipany-woo-plugin-modal");

function on_change(val) {
    val = val.toUpperCase();
    modal.classList.add("shipany-woo-plugin-showModal");
    // if (val === 'SHIPANY_LOCKER') {
    //     document.getElementById('shipany_locker_collect').checked = true;
    // }
    initEasyWidget(val);
}


document.addEventListener("DOMContentLoaded", function (event) {
    let data;
    var getData = (arg) => {

        let dataContainer = document.getElementById("dataContainer");
        dataContainer.innerHTML = JSON.stringify(arg, censor(arg));
    };
    function censor(censor) {
        var i = 0;

        return function (key, value) {
            if (i !== 0 && typeof (censor) === 'object' && typeof (value) == 'object' && censor == value)
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
    jQuery(document).on('change', '.mapFilter', function () {
        jQuery(".shipany-woo-plugin-modal .mapLeftWrapper #filterLoader").show();
        jQuery(".shipany-woo-plugin-modal .mapLeftWrapper div.containerParentTextDiv").hide();
    });
});


function initEasyWidget(locationType) {
    var getData = (arg) => {
        console.error("Deprecated function");
        // jQuery.ajax({
        //     url: document.URL,
        //     type: "POST",
        //     processData: true,
        //     data: {
        //         shippingAddress1: arg.address1En,
        //         shippingAddress2: arg.address2En,
        //         locationId: arg.locationId,
        //         region: arg.province,
        //         district: arg.district,
        //         country: arg.country,
        //         locationType: arg.locationType
        //     },
        //     success: function (data) {
        //         console.log("data");
        //     }
        // });
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