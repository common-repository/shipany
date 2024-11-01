

/**
 * InputIconHelper
 * @constructor
 * @param {string} input - id of input element
 * @method updatePaddingRight - update padding right of input element
 * @param {number} val - value to add to padding right
 * @method setAppendIcon - append icon to input element
 * @property {string} icon - icon to append (tick or cross)
 * @method removeAppendIcon - remove icon from input element
 * @throws {Error} - if icon is not tick or cross
 * @example
 * const obj = new InputIconHelper('input-id');
 * obj.updatePaddingRight(30);
 * obj.setAppendIcon('tick');
 * obj.removeAppendIcon();
 * @author Benedict Yiu
 */
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
var InputIconHelper = /*#__PURE__*/function () {
  function InputIconHelper(input) {
    var _this = this;
    _classCallCheck(this, InputIconHelper);
    _defineProperty(this, "el", null);
    _defineProperty(this, "span", null);
    _defineProperty(this, "paddingMoved", false);
    _defineProperty(this, "borderColor", '');
    _defineProperty(this, "input", input);
    this.initEl();
  }
  _createClass(InputIconHelper, [{
    key: "initEl",
    value: function initEl() {
      if(this.el) return;
      this.el = document.getElementById(this.input);
      if (this.el) {
        this.el.addEventListener('change', function () {
          _this.removeAppendIcon();
        });
        this.el.parentNode.style.position = 'relative';
      }
    }
  }, {
    key: "updatePaddingRight",
    value: function updatePaddingRight(val) {
      this.initEl();
      var currentPR = window.getComputedStyle(this.el).paddingRight;
      var newPR = parseInt(currentPR.replace('px', '')) + val + 'px';
      this.el.style.paddingRight = newPR;
    }
  }, {
    key: "setAppendIcon",
    value: function setAppendIcon(icon) {
      this.initEl();
      if (!['tick', 'cross'].includes(icon)) {
        throw new Error('icon must be tick or cross');
      }
      if (!this.span) {
        this.updatePaddingRight(30);
        this.paddingMoved = true;
        this.span = document.createElement('span');
        this.span.style.position = 'absolute';
        this.span.style.top = '0';
        // assume input width is 400px
        this.span.style.left = 'calc('+window.getComputedStyle(this.el).width+' - 40px)';
        this.span.style.padding = '0 10px';
        this.span.style.lineHeight = window.getComputedStyle(this.el).height;
        this.span.style.fontSize = '20px';
        this.span.style.fontWeight = 'bold';
        this.span.style.zIndex = '999';
        this.span.style.userSelect = 'none';
        window.onresize = function () {
            if(!this.span) return;
            this.span.style.left = 'calc('+window.getComputedStyle(this.el).width+' - 40px)';
            this.span.style.lineHeight = window.getComputedStyle(this.el).height;
        }
      }
      if (icon === 'tick') {
        this.span.innerHTML = '&#10004;';
        this.span.style.color = '#00b300';
      } else {
        this.updatePaddingRight(-30);
        this.paddingMoved = false;
        this.span.innerHTML = '';
        this.span.style.color = 'transparent';
        this.borderColor = window.getComputedStyle(this.el).borderColor;
        this.el.style.borderColor = '#dc3545';
      }
      this.el.parentNode.appendChild(this.span);
    }
  }, {
    key: "removeAppendIcon",
    value: function removeAppendIcon() {
      this.initEl();
      if (this.paddingMoved) {
        this.updatePaddingRight(-30);
        this.paddingMoved = false;
      }
      this.el.style.borderColor = this.borderColor;
      if (!this.span) {
        return;
      }
      this.span.remove();
      this.span = null;
    }
  }]);
  return InputIconHelper;
}();
var input_woocommerce_shipany_ecs_asia_shipany_api_key = new InputIconHelper('woocommerce_shipany_ecs_asia_shipany_api_key');

function getTrans(key){
    return this.trans[key] || key;
}

jQuery(function ($) {

    const commonUtils = {
        isValidUUID: function (paramString) {
            const uuidV4Regex = /^[A-F\d]{8}-[A-F\d]{4}-4[A-F\d]{3}-[89AB][A-F\d]{3}-[A-F\d]{12}$/i;
            return uuidV4Regex.test(paramString.replace('SHIPANYSBX2','').replace('SHIPANYSBX1','').replace('SHIPANYDEV','').replace('SHIPANYDEMO',''));
        },
        getOffset: function (elem){
            const rect = elem.getBoundingClientRect();
            return {
                left: rect.left + window.scrollX,
                top: rect.top + window.scrollY,
                width: rect.width,
                height: rect.height
            };
        }
    }
    const appendLoader = function (cssSelector){
        $(cssSelector).after('<div class="lds-dual-ring"></div>');
        let targetElementInDom = $.find(cssSelector)
        let loaderDom = $.find('.lds-dual-ring');
        if(Array.isArray(targetElementInDom) && targetElementInDom.length > 0){
            targetElementInDom = targetElementInDom[0]
            // const position = commonUtils.getOffset(targetElementInDom)
            // let loaderPosition = position.left + position.width - 176;
            // loaderDom[0].style.left = `${loaderPosition}px`;

            // now targetElementInDom is relative, so we can use absolute position
            loaderDom[0].style.position = 'absolute';
            loaderDom[0].style.left = window.getComputedStyle(targetElementInDom).width;
        }
    }
    const removeLoader = function (){
        let loaderDoms = $.find('.lds-dual-ring');
        if(Array.isArray(loaderDoms) && loaderDoms.length > 0){
            for(const elem of loaderDoms){
                elem.remove();
            }
        }
    }

    const hideDefaultWeight = function (){
        let keyVal = $("input[name='woocommerce_shipany_ecs_asia_shipany_api_key']").val()
        var keyValMd5 = MD5(keyVal)
        if (!['8241d0678fb9abe65a77fe6d69f7063c', '7df5eeebe4116acfefa81a7a7c3f12ed'].includes(keyValMd5)) {
            $('#woocommerce_shipany_ecs_asia_default_weight').prop('checked', false);
            $('label[for="woocommerce_shipany_ecs_asia_default_weight"]').parent().parent().hide()
        }
    }
    
    const appendRegisterLink = function (){
        let store_url = shipany_setting_val.store_url
        let textElem = $.find('.shipany-register-descr + p');
        let regionSuffix = ''
        if ($("#woocommerce_shipany_ecs_asia_shipany_region").find(":selected").text() == 'Singapore') {
            regionSuffix = '-sg'
        }
        if(Array.isArray(textElem) && textElem.length > 0){
            textElem = textElem[0];
            $('.shipany-register-descr + p').append('<a class="shipany-portal-link" target="_blank" href="https://portal'+ regionSuffix +'.shipany.io/user/register?referrer=woocommerce&store_url=' + store_url +'">'+getTrans('Register now')+'</a>')
            $('.shipany-register-descr').hide();
        }
    }
    const appendShippingMethodLink = function (){
        let elem = $('.shipany-enable-locker')
        let currentUrl = window.location.href
        let newUrl = currentUrl.replace('wc-settings&tab=shipping&section=shipany_ecs_asia', 'wc-settings&tab=shipping')

        if (elem[0] !== undefined) {
            // elem.parent().parent().append('To enable Locker/Store List, please add "Local pickup" in <a href="' + newUrl + '">Shipping zones</a>')
            let text = 'Add "Local pickup" in <a href="' + newUrl + '">Shipping zones</a> to enable Locker/Store List. If more than one Local pickup is defined, the first one will always be the one linking to the locker list.';
            if (document.documentElement.lang.startsWith('zh')) {
                if (document.documentElement.lang.includes('hk')) {
                    text = '請在<a href="' + newUrl + '">運送區域</a>中新增「本地自取」以啟用自取點列表。若有多個「本地自取」選項，第一個將會是連結至自取點列表的選項。';
                } else {
                    text = '請在<a href="' + newUrl + '">運送區域</a>中新增「自行取貨」以啟用自取點列表。若有多個「自行取貨」選項，第一個將會是連結至自取點列表的選項。';
                }
            }
            elem.parent().parent().append(text)

        }

        // disable the checkbox
        // $("label[for='"+'woocommerce_shipany_ecs_asia_enable_locker_list'+"']").css('cursor','not-allowed')
    }

    const appendGetTokenLink = function (){
        // rest_url => http://localhost/appcider/wp-json/ , trim the /wp-json/
        let currentUrl = window.location.href
        let rest_url = shipany_setting_val.rest_url.replace('/wp-json/', '')
        let callback_url_prefix = 'https://{api}.shipany.io/'
        let mch_uid = shipany_setting_val.mch_uid
        
        if (shipany_setting_val.shipany_api_key != null ) {
            if (shipany_setting_val.shipany_api_key.includes('SHIPANYDEV')) callback_url_prefix = 'https://{api}-dev3.shipany.io/'
            else if (shipany_setting_val.shipany_api_key.includes('SHIPANYDEMO')) callback_url_prefix = 'https://{api}-demo1.shipany.io/'
            else if (shipany_setting_val.shipany_api_key.includes('SHIPANYSBX2')) callback_url_prefix = 'https://{api}-sbx2.shipany.io/'
            else if (shipany_setting_val.shipany_api_key.includes('SHIPANYSBX1')) callback_url_prefix = 'https://{api}-sbx1.shipany.io/'
        }
        
        var shipany_region = $("#woocommerce_shipany_ecs_asia_shipany_region").find(":selected").val()
        callback_url_prefix = callback_url_prefix.replace('{api}', {
            '0': 'api',
            '1': 'api-sg',
            '2': 'api-tw'
        }[shipany_region])


        const endpoint = '/wc-auth/v1/authorize';
        const params = {
          app_name: 'ShipAny',
          scope: 'read_write',
          user_id: 1,
          return_url: currentUrl,
          callback_url: callback_url_prefix + 'woocommerce/webhooks/receive-rest-token/?mch_uid='+ mch_uid + '&store_url=' + rest_url
        };
        var queryString = $.param(params)

        // console.log(rest_url + endpoint + '?' + queryString );

        // let elem = $('.shipany-rest')
        // if (elem[0] !== undefined) {
        //     // elem.parent().parent().append('To enable Locker/Store List, please add "Local pickup" in <a href="' + newUrl + '">Shipping zones</a>')
        //     elem.parent().parent().append('Click <a href="' + rest_url + endpoint + '?' + queryString + '">Here</a> to register REST API token')
        // }
        if (document.getElementById('woocommerce_shipany_ecs_asia_shipany_rest_token').innerHTML != null) {
            document.getElementById('woocommerce_shipany_ecs_asia_shipany_rest_token').innerHTML = getTrans('Grant Permission')
        }
        

        if (mch_uid == null || shipany_setting_val.shipany_api_key == null) {
            document.getElementById("woocommerce_shipany_ecs_asia_shipany_rest_token").onclick = function () {
                alert('Save all changes including (API Token, Default Courier) before enable ShipAny Active Notification')
                return false;
            };              
        } else if (shipany_setting_val.has_token) {
            document.getElementById("woocommerce_shipany_ecs_asia_shipany_rest_token").onclick = function () {
                alert('Already enabled ShipAny Active Notification')
                return false;
            };              
        } else {
            document.getElementById("woocommerce_shipany_ecs_asia_shipany_rest_token").onclick = function () {
                location.href = rest_url + endpoint + '?' + queryString;
            };
        }
 
    }
    const updateStorageType = function (getTargetValue){
        // $( '.default-storage-type option' )
        // $( 'select[name="woocommerce_shipany_ecs_asia_set_default_storage_type"]' )
        // if supported_storage_types_courier is not undefined and supported_storage_types_courier[getTargetValue] is not undefined
        if(supported_storage_types_courier && supported_storage_types_courier[getTargetValue]){
            $('.default-storage-type option').remove()
            for(let stg_typ of supported_storage_types_courier[getTargetValue]){
                $( 'select[name="woocommerce_shipany_ecs_asia_set_default_storage_type"]' ).append(`<option value ="${stg_typ}">${getTrans(stg_typ)}</option>`);
            }
        // FIXME: should remove the following code after the supported_storage_types_courier is ready
        } else if (['c6e80140-a11f-4662-8b74-7dbc50275ce2','f403ee94-e84b-4574-b340-e734663cdb39','7b3b5503-6938-4657-acab-2ff31c3a3f45','2ba434b5-fa1d-4541-bc43-3805f8f3a26d','1d22bb21-da34-4a3c-97ed-60e5e575a4e5','1bbf947d-8f9d-47d8-a706-a7ce4a9ddf52','c74daf26-182a-4889-924b-93a5aaf06e19'].includes(getTargetValue)){
            $('.default-storage-type option').each(function() {
                $(this).remove();
            });
            $(".default-storage-type option[value='']").each(function() {$(this).remove();});
            var optionsAsString = "";
            optionsAsString +=`<option value ="Air Conditioned">${getTrans('Air Conditioned (17°C to 22°C)')}</option>`;
            optionsAsString +=`<option value ="Chilled">${getTrans('Chilled (0°C to 4°C)')}</option>`;
            optionsAsString +=`<option value ="Frozen">${getTrans('Frozen (-18°C to -15°C)')}</option>`;
            $( 'select[name="woocommerce_shipany_ecs_asia_set_default_storage_type"]' ).append( optionsAsString );               
        } else {
            $('.default-storage-type option').each(function() {
                $(this).remove();
            });
            $(".default-storage-type option[value='']").each(function() {$(this).remove();});
            var optionsAsString = "";
            optionsAsString +=`<option value ="" selected>${getTrans('Normal')}</option>`;
            $( 'select[name="woocommerce_shipany_ecs_asia_set_default_storage_type"]' ).append( optionsAsString );    
        }

    }
    const updateAdditionalServicePlan = function (targetText){
        if (targetText == 'Lalamove') {
            $('label[for="woocommerce_shipany_ecs_asia_shipany_default_courier_additional_service"]').parent().parent().show()
        } else {
            $('label[for="woocommerce_shipany_ecs_asia_shipany_default_courier_additional_service"]').parent().parent().hide()
        }
    }
    const updatePaidByRec = function (targetValue){
        if (shipany_setting_val['courier_show_paid_by_rec'].includes(targetValue)){
            $('label[for="woocommerce_shipany_ecs_asia_shipany_paid_by_rec"]').parent().parent().show()
        } else {
            $('#woocommerce_shipany_ecs_asia_shipany_paid_by_rec').prop('checked', false)
            $('label[for="woocommerce_shipany_ecs_asia_shipany_paid_by_rec"]').parent().parent().hide()
        }
    }
    const updateSelfDropOff = function (targetValue){
        if (shipany_setting_val['courier_show_self_drop_off'].includes(targetValue)){
            $('label[for="woocommerce_shipany_ecs_asia_shipany_self_drop_off"]').parent().parent().show()
        } else {
            $('#woocommerce_shipany_ecs_asia_shipany_self_drop_off').prop('checked', false)
            $('label[for="woocommerce_shipany_ecs_asia_shipany_self_drop_off"]').parent().parent().hide()
        }
    }
    var wc_shipping_setting = {
        // init Class
        init: function () {
            // hide paid by rec if the courier not support
            if (shipany_setting_val['courier_show_paid_by_rec'] != null) {
                if (!shipany_setting_val['courier_show_paid_by_rec'].includes($(".default-courier-selector option:selected").val())){
                    $('#woocommerce_shipany_ecs_asia_shipany_paid_by_rec').prop('checked', false);
                    $('label[for="woocommerce_shipany_ecs_asia_shipany_paid_by_rec"]').parent().parent().hide()
                }
            }
            if (shipany_setting_val['courier_show_self_drop_off'] != null) {
                if (!shipany_setting_val['courier_show_self_drop_off'].includes($(".default-courier-selector option:selected").val())){
                    $('#woocommerce_shipany_ecs_asia_shipany_self_drop_off').prop('checked', false);
                    $('label[for="woocommerce_shipany_ecs_asia_shipany_self_drop_off"]').parent().parent().hide()
                }
            }
            hideDefaultWeight();
            appendRegisterLink();
            appendShippingMethodLink();
            appendGetTokenLink();
            // hide additional service plan if the courier not support
            if ($('#select2-woocommerce_shipany_ecs_asia_shipany_default_courier-container')[0] != null) {
                if ($('#select2-woocommerce_shipany_ecs_asia_shipany_default_courier-container')[0].innerHTML != 'Lalamove') {
                    $('label[for="woocommerce_shipany_ecs_asia_shipany_default_courier_additional_service"]').parent().parent().hide()
                }
            } else if ($('#woocommerce_shipany_ecs_asia_shipany_default_courier').find(":selected").text() != 'Lalamove') {
                $('label[for="woocommerce_shipany_ecs_asia_shipany_default_courier_additional_service"]').parent().parent().hide()
            }
            let isLoadedCourierList = false;
            let isLoading = false;
            let currentInputToken = $("input[name='woocommerce_shipany_ecs_asia_shipany_api_key']").val();
            $("input[name='woocommerce_shipany_ecs_asia_shipany_api_key']").bind('onChangeAccessToken', function (e) {
                // $("button[name='save']").trigger('click');
                let targetTokenVal = e.target.value.trim();
                let shipanyRegion = $("#woocommerce_shipany_ecs_asia_shipany_region").find(":selected").val()
                // if(currentInputToken === targetTokenVal){
                //     return;
                // }else{
                //     isLoadedCourierList = false;
                // }
                isLoadedCourierList = false;
                if (commonUtils.isValidUUID(targetTokenVal)) {
                    console.log('is UUID');
                    if (!isLoadedCourierList) {
                        // isLoadedCourierList = true
                        if(isLoading){
                            return;
                        }
                        console.log('Going to trigger API call');
                        $('.default-courier-selector').prop('disabled', true);
                        appendLoader("input[name='woocommerce_shipany_ecs_asia_shipany_api_key");
                        isLoading = true;
                        $.ajax({
                            url: shipany_setting_val.ajax_url,
                            method: 'POST',
                            dataType: 'JSON',
                            data:{
                                // the value of data.action is the part AFTER 'wp_ajax_' in
                                // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                                action: 'on_change_load_couriers',
                                // ANY other properties of data are passed to your_function()
                                // in the PHP global $_REQUEST (or $_POST in this case)
                                api_tk : targetTokenVal,
                                region: shipanyRegion
                            },
                            success: function (response){
                                isLoading = false
                                if(response.success){
                                    console.log('get response')
                                    input_woocommerce_shipany_ecs_asia_shipany_api_key.setAppendIcon('tick');
                                    const courierList = {...response.data.data.cour_list}
                                    supported_storage_types_courier = {...response.data.data.supported_storage_types_courier}
                                    var optionsAsString = "";

                                    // check if need remove old mounted dom here
                                    $('.default-courier-selector option').each(function() {
                                        $(this).remove();
                                    });
                                    for(const courierUUID of Object.keys(courierList)){
                                        optionsAsString += "<option value='" + courierUUID + "'>" + courierList[courierUUID] + "</option>";
                                    }
                                    $( 'select[name="woocommerce_shipany_ecs_asia_shipany_default_courier"]' ).append( optionsAsString );

                                    $(".default-courier-selector option[value='']").each(function() {
                                        $(this).remove();
                                    });

                                    if (response.data.asn_mode != 'Disable'){
                                        $('label[for="woocommerce_shipany_ecs_asia_shipany_tracking_note_txt"]').hide()
                                        if (document.getElementById('woocommerce_shipany_ecs_asia_shipany_tracking_note_txt') != null) document.getElementById('woocommerce_shipany_ecs_asia_shipany_tracking_note_txt').style='display:none'
                                    }
                                }else{
                                    console.log('failed to get success')
                                    input_woocommerce_shipany_ecs_asia_shipany_api_key.setAppendIcon('cross');
                                    $('.default-courier-selector option').each(function() {
                                        $(this).remove();
                                    });
                                    let errorTitle = response.data.data.error_title;
                                    let errorDetail = response.data.data.error_detail;
                                    $('.shipany-main-dialog').show();
                                    $('.shipany-dialog > .title').text(errorTitle);
                                    $('.shipany-dialog > .detail').text(errorDetail);
                                    isLoadedCourierList = false;
                                    isLoading = false;
                                }
                                isLoadedCourierList = true;
                                currentInputToken = targetTokenVal;
                                removeLoader();
                                $('.default-courier-selector').prop('disabled', false);
                                $('.default-courier-selector option:first').attr('selected','selected');
                                $('.default-courier-selector').change();
                            },
                            error: function (xhr, ajaxOptions, thrownError){
                                console.log('error');
                                isLoadedCourierList = false;
                                isLoading = false;

                            }
                        })
                    }
                } else {
                    console.log('No an UUID, do nothing');
                    $('.default-courier-selector option').each(function() {
                        $(this).remove();
                    });
                }        
            }).change(function () {
                $(this).trigger('onChangeAccessToken'); //call onChangeAccessToken on blur
            }).keyup(function (e) {
                var code = (e.keyCode || e.which);
                if(code == 17) {
                    return;
                }
                // prevent arrow keys and shift key
                if(code == 37 || code == 38 || code == 39 || code == 40 || code == 16) {
                    return;
                }
                $(this).trigger('onChangeAccessToken'); //call onChangeAccessToken on blur
            });

            // handle in case end user select default courier from rendered options
            $('.default-courier-selector').bind('onChangeSelectDefaultCourier', function (e){
                const targetValue = e.target.value;
                const targetText = $(".default-courier-selector option:selected").text();
                const tempKey= document.getElementById('woocommerce_shipany_ecs_asia_shipany_api_key').value;
                updateStorageType(targetValue);
                updateAdditionalServicePlan(targetText);
                updatePaidByRec(targetValue);
                updateSelfDropOff(targetValue);
            }).change(function (){
                $(this).trigger('onChangeSelectDefaultCourier'); //call onChangeAccessToken on blur
            });

            // handle update address
            $('.update-address').click('onUpdateAddress', function (e){
                document.getElementById('woocommerce_shipany_ecs_asia_shipany_update_address').innerHTML='Loading...';
                document.getElementById('woocommerce_shipany_ecs_asia_shipany_update_address').style.cursor = 'default';
                document.getElementById('woocommerce_shipany_ecs_asia_shipany_update_address').style.pointerEvents = 'none';
                $.ajax({
                    url: shipany_setting_val.ajax_url,
                    method: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'on_click_update_address',
                    },
                    success: function (response){
                        if(response.success){
                            alert('Sender Address update to: ' + response.data.address_line1 + ' ' + response.data.distr + ' ' + response.data.cnty);
                        } else {
                            alert('Update failed.')
                        }
                        document.getElementById('woocommerce_shipany_ecs_asia_shipany_update_address').innerHTML='Refresh Sender Address';
                        document.getElementById('woocommerce_shipany_ecs_asia_shipany_update_address').style.cursor = 'pointer';
                        document.getElementById('woocommerce_shipany_ecs_asia_shipany_update_address').style.pointerEvents = '';
                    }
                })
            })

            function load_create_order_status_selector() {
                if (document.querySelector('[name="woocommerce_shipany_ecs_asia_set_default_create"]').checked) {
                    document.getElementById('woocommerce_shipany_ecs_asia_set_default_create_order_status').parentElement.parentElement.parentElement.style.display = 'table-row';
                } else {
                    document.getElementById('woocommerce_shipany_ecs_asia_set_default_create_order_status').parentElement.parentElement.parentElement.style.display = 'none';
                }
            }
            load_create_order_status_selector();
            $('[name="woocommerce_shipany_ecs_asia_set_default_create"]').click(load_create_order_status_selector);

            // Handle change region, empty api-tk and courier list
            $("#woocommerce_shipany_ecs_asia_shipany_region").bind('onChangeSelectRegion', function (e){
                // Clear the api-tk
                $('#woocommerce_shipany_ecs_asia_shipany_api_key').val('')

                // Update register portal url
                var shipany_region = $("#woocommerce_shipany_ecs_asia_shipany_region").find(":selected").val()
                var oldUrl = $(".shipany-portal-link").attr("href")
                var newUrl;
                newUrl = oldUrl.replace("portal-sg", "guirgniuert0");
                newUrl = newUrl.replace("portal-tw", "guirgniuert0");
                newUrl = newUrl.replace("portal", "guirgniuert0");
                if (shipany_region == 1) {
                    newUrl = newUrl.replace("guirgniuert0", "portal-sg");
                    $(".shipany-portal-link").attr("href", newUrl);
                    $('#woocommerce_shipany_ecs_asia_shipany_locker_include_macuo').parents('tr').hide()
                } else if (shipany_region == 0) {
                    newUrl = newUrl.replace("guirgniuert0", "portal");
                    $(".shipany-portal-link").attr("href", newUrl);
                    $('#woocommerce_shipany_ecs_asia_shipany_locker_include_macuo').parents('tr').show()
                } else if (shipany_region == 2) {
                    newUrl = newUrl.replace("guirgniuert0", "portal-tw");
                    $(".shipany-portal-link").attr("href", newUrl);
                    $('#woocommerce_shipany_ecs_asia_shipany_locker_include_macuo').parents('tr').hide()
                }

                // Clear Courier list
                $('.default-courier-selector option').each(function() {
                    $(this).remove();
                });
            }).change(function (){
                $(this).trigger('onChangeSelectRegion'); 
            })

            // add connect token button
            const clickConnectAccessToken = function () {
                event.preventDefault();
                let targetTokenVal = $("input[name='woocommerce_shipany_ecs_asia_shipany_api_key']").val();
                let shipanyRegion = $("#woocommerce_shipany_ecs_asia_shipany_region").find(":selected").val()
                // if(currentInputToken === targetTokenVal){
                //     return;
                // }else{
                //     isLoadedCourierList = false;
                // }
                isLoadedCourierList = false;
                if (commonUtils.isValidUUID(targetTokenVal)) {
                    console.log('is UUID');
                    if (!isLoadedCourierList) {
                        // isLoadedCourierList = true
                        if(isLoading){
                            return;
                        }
                        console.log('Going to trigger API call');
                        $('.default-courier-selector').prop('disabled', true);
                        appendLoader("input[name='woocommerce_shipany_ecs_asia_shipany_api_key");
                        isLoading = true;
                        $.ajax({
                            url: shipany_setting_val.ajax_url,
                            method: 'POST',
                            dataType: 'JSON',
                            data:{
                                // the value of data.action is the part AFTER 'wp_ajax_' in
                                // the add_action ('wp_ajax_xxx', 'yyy') in the PHP above
                                action: 'on_change_load_couriers',
                                // ANY other properties of data are passed to your_function()
                                // in the PHP global $_REQUEST (or $_POST in this case)
                                api_tk : targetTokenVal,
                                region: shipanyRegion
                            },
                            success: function (response){
                                isLoading = false
                                if(response.success){
                                    console.log('get response')
                                    input_woocommerce_shipany_ecs_asia_shipany_api_key.setAppendIcon('tick');
                                    const courierList = {...response.data.data.cour_list}
                                    supported_storage_types_courier = {...response.data.data.supported_storage_types_courier}
                                    var optionsAsString = "";
        
                                    // check if need remove old mounted dom here
                                    $('.default-courier-selector option').each(function() {
                                        $(this).remove();
                                    });
                                    for(const courierUUID of Object.keys(courierList)){
                                        optionsAsString += "<option value='" + courierUUID + "'>" + courierList[courierUUID] + "</option>";
                                    }
                                    $( 'select[name="woocommerce_shipany_ecs_asia_shipany_default_courier"]' ).append( optionsAsString );
        
                                    $(".default-courier-selector option[value='']").each(function() {
                                        $(this).remove();
                                    });
        
                                    if (response.data.asn_mode != 'Disable'){
                                        $('label[for="woocommerce_shipany_ecs_asia_shipany_tracking_note_txt"]').hide()
                                        if (document.getElementById('woocommerce_shipany_ecs_asia_shipany_tracking_note_txt') != null) document.getElementById('woocommerce_shipany_ecs_asia_shipany_tracking_note_txt').style='display:none'
                                    }
                                }else{
                                    console.log('failed to get success')
                                    input_woocommerce_shipany_ecs_asia_shipany_api_key.setAppendIcon('cross');
                                    $('.default-courier-selector option').each(function() {
                                        $(this).remove();
                                    });
                                    let errorTitle = response.data.data.error_title;
                                    let errorDetail = response.data.data.error_detail;
                                    $('.shipany-main-dialog').show();
                                    $('.shipany-dialog > .title').text(errorTitle);
                                    $('.shipany-dialog > .detail').text(errorDetail);
                                    isLoadedCourierList = false;
                                    isLoading = false;
                                }
                                isLoadedCourierList = true;
                                currentInputToken = targetTokenVal;
                                removeLoader();
                                $('.default-courier-selector').prop('disabled', false);
                                $('.default-courier-selector option:first').attr('selected','selected');
                                $('.default-courier-selector').change();
                            },
                            error: function (xhr, ajaxOptions, thrownError){
                                console.log('error');
                                isLoadedCourierList = false;
                                isLoading = false;
        
                            }
                        })
                    }
                } else {
                    console.log('No an UUID, do nothing');
                    $('.default-courier-selector option').each(function() {
                        $(this).remove();
                    });
                }
            }
            if (document.documentElement.lang.startsWith('zh')) {
                var buttonElement = $("<button>連接</button>").attr("id", "shipanyConnect")
            } else {
                var buttonElement = $("<button>Connect</button>").attr("id", "shipanyConnect")
            }
            buttonElement.css({
                "color": "#2271b1",
                "border-color": "#2271b1",
                "background": "#f6f7f7",
                "vertical-align": "top",
                "border-width": "1px",
                "border-radius": "3px",
                "border-style": "solid",
                "cursor": "pointer"
              });
            $("input[name='woocommerce_shipany_ecs_asia_shipany_api_key']").after(buttonElement)
              var buttonElement = document.getElementById("shipanyConnect");
              buttonElement.addEventListener("click", clickConnectAccessToken);
        },
    };

    wc_shipping_setting.init();
    console.log('test');

});
