const {
  Observable,
  from,
  defer,
  BehaviourSubject,
  pluck,
  of,
  range,
  merge,
  Subject,
} = rxjs;
const {
  flatMap,
  flatMapLatest,
  toArray,
  finalize,
  takeLast,
  findIndex,
  takeUntil,
  defaultIfEmpty,
} = rxjs.operators;
//import Layer from 'ol/layer/Layer';
const Layer = ol.layer.Layers;
const { Source } = ol.source;

var submitPress = false;
var lockerLocations = [];
let districtFilterData = [],
  regionFilterData = [],
  areaFilterData = [];
let reactiveFilterData,
  reactiveDistrictData,
  reactiveRegionData,
  reactiveAreaData;
let filtDis = [];

let filteredRegionName = "";
let filteredDistrictName = "";
let filteredDistrict = [];
let filteredArea = [];
let dropDownSelectedItemContainer;
let locDataObservable = getResponse('', _userAuthObject);

let multiFilterObject = {};

//NEW CODE
var numberOfFilteredItems = [];

let dropDownElement;
let dropDownElementList = [];
let clickedIndex = 0;
let selectedLocationNameId;

let callBackObject = {};

let popup = new ol.Overlay.Popup();

//check if need to add dropDown

if (_mode === "dropdown") {
  // create custom dropdown component
  dropDownElement = document.createElement("div");
  dropDownElement.className = "customDropDown";
  let customDropDownSelect = document.createElement("div");
  Object.assign(customDropDownSelect, {
    className: "customDropDownSelect",
    onclick: (e) => {
      // onClick function: toggle dropdown open
      document
        .getElementsByClassName("customDropDownContainer")[0]
        .classList.toggle("customDropDownOpen");
    },
  });

  // dropdown placeholder
  let customDropDownSelectText = document.createElement("p");
  if (_locale === "en" || 1) {
    Object.assign(customDropDownSelectText, {
      className: "customDropDownSelectText",
      innerHTML: "Shipany Click & Collect Options",
    });
  } else if (_locale === "zh") {
    Object.assign(customDropDownSelectText, {
      className: "customDropDownSelectText",
      innerHTML: "Shipany智能櫃地點",
    });
  }

  customDropDownSelect.appendChild(customDropDownSelectText);
  dropDownElement.appendChild(customDropDownSelect);

  // Function on window click, click anywhere on window and close the dropdown
  window.onclick = function (e) {
    let box = document.getElementsByClassName("customDropDownContainer")[0];
    let selectBox = document.getElementsByClassName("customDropDownSelect")[0];
    let selectText = document.getElementsByClassName(
      "customDropDownSelectText"
    )[0];

    if (e.target != selectBox && e.target != selectText) {
      if (Object.values(box.classList).indexOf("customDropDownOpen") > -1) {
        box.classList.remove("customDropDownOpen");
      }
    }
  };
}

function isEmptyObject(obj) {
  return !!obj && Object.keys(obj).length === 0 && obj.constructor === Object;
}

function translateRegion(val) {
  if (val === 'Hongkong Island') val = '香港';
  else if (val === 'Kowloon') val = '九龍';
  else if (val === 'New Territories') val = '新界';
  else if (val === 'Outlying Islands') val = '離島';
  else if (val === 'Macao') val = '澳門';
  else if (val === 'All Regions') val = '全部區域';
  return val;
}

function translateDistrict(val) {
  if (val === 'Central and Western District') val = '中西區';
  else if (val === 'Cheung Chau District') val = '長洲區';
  else if (val === 'Eastern District') val = '東區';
  else if (val === 'Kowloon City District') val = '九龍城區';
  else if (val === 'Kwai Tsing District') val = '葵青區';
  else if (val === 'Kwun Tong District') val = '觀塘區';
  else if (val === 'Lamma Island District') val = '南丫島區';
  else if (val === 'Lantau District') val = '大嶼山區';
  else if (val === 'North District') val = '北區';
  else if (val === 'Peng Chau District') val = '坪洲區';
  else if (val === 'Sai Kung District') val = '西貢區';
  else if (val === 'Sha Tin District') val = '沙田區';
  else if (val === 'Sham Shui Po District') val = '深水埗區';
  else if (val === 'Southern District') val = '南區';
  else if (val === 'Tai Po District') val = '大埔區';
  else if (val === 'Tsuen Wan District') val = '荃灣區';
  else if (val === 'Tuen Mun District') val = '屯門區';
  else if (val === 'Wan Chai District') val = '灣仔區';
  else if (val === 'Wong Tai Sin District') val = '黃大仙區';
  else if (val === 'Yau Tsim Mong District') val = '油尖旺區';
  else if (val === 'Yuen Long District') val = '元朗區';
  else if (val === 'Islands District') val = '離島區';
  else if (val === 'Peninsula de Macao') val = '澳門半島';
  else if (val === 'Ilha da Taipa') val = '氹仔';
  else if (val === 'Taipa Island"') val = '路氹城';
  else if (val === 'Coloane') val = '路環';
  else if (val === 'Macao') val = '澳門';
  else if (val === 'All Districts') val = '全部地區';
  return val;
}

function translateArea(val) {
  if (val === 'Admiralty') val = '金鐘'
  else if (val === 'Kennedy Town') val = '堅尼地城'
  else if (val === 'Central') val = '中環'
  else if (val === 'Lugard Road') val = '山頂盧吉道'
  else if (val === 'Mid-levels') val = '半山'
  else if (val === 'Sai Wan') val = '西環'
  else if (val === 'Sai Ying Pun') val = '西營盤'
  else if (val === 'Sheung Wan') val = '上環'
  else if (val === 'The Peak') val = '山頂'
  else if (val === 'Cheung Chau') val = '長洲'
  else if (val === 'Chai Wan') val = '柴灣'
  else if (val === 'Fortress Hill') val = '炮台山'
  else if (val === 'Heng Fa Chuen') val = '杏花邨'
  else if (val === 'North Point') val = '北角'
  else if (val === 'Quarry Bay') val = '鰂魚涌'
  else if (val === 'Sai Wan Ho') val = '西灣河'
  else if (val === 'Shau Kei Wan') val = '筲箕灣'
  else if (val === 'Siu Sai Wan') val = '小西灣'
  else if (val === 'Tai Koo') val = '太古'
  else if (val === 'Taikoo Shing') val = '太古城'
  else if (val === 'Tin Hau') val = '天后'
  else if (val === 'Ho Man Tin') val = '何文田'
  else if (val === 'Hung Hom') val = '紅磡'
  else if (val === 'Kai Tak') val = '啟德'
  else if (val === 'Kowloon City') val = '九龍城'
  else if (val === 'Kowloon Tong') val = '九龍塘'
  else if (val === 'Ma Tau Wai') val = '馬頭圍'
  else if (val === 'To Kwa Wan') val = '土瓜灣'
  else if (val === 'Whampoa') val = '黃埔'
  else if (val === 'Kwai Chung') val = '葵涌'
  else if (val === 'Kwai Fong') val = '葵芳'
  else if (val === 'Kwai Hing') val = '葵興'
  else if (val === 'Kwai Tsing Container Terminals') val = '葵青貨櫃碼頭'
  else if (val === 'Lai King') val = '荔景'
  else if (val === 'Tsing Yi') val = '青衣'
  else if (val === 'Tsing Yi Goodman Interlink') val = '青衣嘉民領達中心'
  else if (val === 'Kowloon Bay') val = '九龍灣'
  else if (val === 'Kwun Tong') val = '觀塘'
  else if (val === 'Lam Tin') val = '藍田'
  else if (val === 'Ngau Tau Kok') val = '牛頭角'
  else if (val === 'Sau Mau Ping') val = '秀茂坪'
  else if (val === 'Shun Lee') val = '順利'
  else if (val === 'Yau Tong') val = '油塘'
  else if (val === 'Lamma Island') val = '南丫島'
  else if (val === 'Chek Lap Kok') val = '赤鱲角'
  else if (val === 'Cheung Sha') val = '長沙'
  else if (val === 'Discovery Bay') val = '愉景灣'
  else if (val === 'Hong Kong Disneyland') val = '香港迪士尼'
  else if (val === 'Hong Kong International Airport') val = '香港國際機場'
  else if (val === 'Lantau Island') val = '大嶼山'
  else if (val === 'Ma Wan') val = '馬灣'
  else if (val === 'Mui Wo') val = '梅窩'
  else if (val === 'Mui Wo Pier') val = '梅窩碼頭'
  else if (val === 'Ngong Ping') val = '昂坪'
  else if (val === 'Penny\'s Bay') val = '竹篙灣'
  else if (val === 'Pui O') val = '貝澳'
  else if (val === 'Shibi') val = '石壁'
  else if (val === 'Tai O') val = '大澳'
  else if (val === 'Tai O Pier') val = '大澳碼頭'
  else if (val === 'The Big Buddha') val = '天壇大佛'
  else if (val === 'Tong Fuk') val = '塘福'
  else if (val === 'Tung Chung') val = '東涌'
  else if (val === 'Fanling') val = '粉嶺'
  else if (val === 'Kwu Tung') val = '古洞'
  else if (val === 'Man Kam To') val = '文錦渡'
  else if (val === 'North District') val = '北區'
  else if (val === 'Ping Che') val = '坪輋'
  else if (val === 'Sha Tau Kok') val = '沙頭角'
  else if (val === 'Sheung Shui') val = '上水'
  else if (val === 'Ta Kwu Ling') val = '打鼓嶺'
  else if (val === 'Peng Chau') val = '坪洲'
  else if (val === 'Clearwater Bay') val = '清水灣'
  else if (val === 'Hang Hau') val = '坑口'
  else if (val === 'LOHAS Park') val = '康城'
  else if (val === 'Po Lam') val = '寶琳'
  else if (val === 'Sai Kung') val = '西貢'
  else if (val === 'Tiu Keng Leng') val = '調景嶺'
  else if (val === 'Tseung Kwan O') val = '將軍澳'
  else if (val === 'Fo Tan') val = '火炭'
  else if (val === 'Kau To Shan/Cove Hill') val = '九肚山'
  else if (val === 'Ma Liu Shui') val = '馬料水'
  else if (val === 'Ma On Shan') val = '馬鞍山'
  else if (val === 'Sha Tin') val = '沙田'
  else if (val === 'Shek Mun') val = '石門'
  else if (val === 'Siu Lek Yuen') val = '小瀝源'
  else if (val === 'Tai Wai') val = '大圍'
  else if (val === 'Wo Che') val = '禾輋'
  else if (val === 'Cheung Sha Wan') val = '長沙灣'
  else if (val === 'Lai Chi Kok') val = '荔枝角'
  else if (val === 'Mei Foo') val = '美孚'
  else if (val === 'Nam Cheong') val = '南昌'
  else if (val === 'Ngong Shuen Chau') val = '昂船洲'
  else if (val === 'Sham Shui Po') val = '深水埗'
  else if (val === 'Shek Kip Mei') val = '石硤尾'
  else if (val === 'Aberdeen') val = '香港仔'
  else if (val === 'Ap Lei Chau') val = '鴨脷洲'
  else if (val === 'Chung Hom Kok') val = '舂坎角'
  else if (val === 'Deep Water Bay') val = '深水灣'
  else if (val === 'Pok Fu Lam') val = '薄扶林'
  else if (val === 'Repulse Bay') val = '淺水灣'
  else if (val === 'Shek O') val = '石澳'
  else if (val === 'Stanley') val = '赤柱'
  else if (val === 'Tai Tam') val = '大潭'
  else if (val === 'Wah Fu') val = '華富'
  else if (val === 'Wong Chuk Hang') val = '黃竹坑'
  else if (val === 'Tai Po') val = '大埔'
  else if (val === 'Tai Wo') val = '太和'
  else if (val === 'Sham Tseng') val = '深井'
  else if (val === 'Tai Wo Hau') val = '大窩口'
  else if (val === 'Ting Kau') val = '汀九'
  else if (val === 'Tsuen Wan') val = '荃灣'
  else if (val === 'Lam Tei') val = '藍地'
  else if (val === 'Lung Kwu Tan') val = '龍鼓灘'
  else if (val === 'San Hui') val = '新墟'
  else if (val === 'Siu Lam') val = '小欖'
  else if (val === 'So Kwun Wat') val = '掃管笏'
  else if (val === 'Tai Lam') val = '大欖'
  else if (val === 'Tai Lam Chung') val = '大欖涌'
  else if (val === 'Tuen Mun') val = '屯門'
  else if (val === 'Tuen Mun River Trade Terminal') val = '屯門內河碼頭'
  else if (val === 'Causeway Bay') val = '銅鑼灣'
  else if (val === 'Happy Valley') val = '跑馬地'
  else if (val === 'Hong Kong Convention and Exhibition Centre') val = '香港會議展覽中心'
  else if (val === 'Tai Hang') val = '大坑'
  else if (val === 'Wan Chai') val = '灣仔'
  else if (val === 'Choi Hung') val = '彩虹'
  else if (val === 'Diamond Hill') val = '鑽石山'
  else if (val === 'Lok Fu') val = '樂富'
  else if (val === 'Ngau Chi Wan') val = '牛池灣'
  else if (val === 'San Po Kong') val = '新蒲崗'
  else if (val === 'Tsz Wan Shan') val = '慈雲山'
  else if (val === 'Wang Tau Hom') val = '橫頭磡'
  else if (val === 'Wong Tai Sin') val = '黃大仙'
  else if (val === 'Austin') val = '柯士甸'
  else if (val === 'Jordan') val = '佐敦'
  else if (val === 'Mong Kok') val = '旺角'
  else if (val === 'Prince Edward') val = '太子'
  else if (val === 'Tai Kok Tsui') val = '大角咀'
  else if (val === 'Tsim Sha Tsui') val = '尖沙咀'
  else if (val === 'Yau Ma Tei') val = '油麻地'
  else if (val === 'Ha Tsuen') val = '厦村'
  else if (val === 'Hung Shui Kiu') val = '洪水橋'
  else if (val === 'Kam Sheung Road') val = '錦上路'
  else if (val === 'Kam Tin') val = '錦田'
  else if (val === 'Lam Tsuen') val = '林村'
  else if (val === 'Lau Fau Shan') val = '流浮山'
  else if (val === 'Lok Ma Chau') val = '落馬洲'
  else if (val === 'Mai Po') val = '米埔'
  else if (val === 'Ngau Tam Mei') val = '牛潭尾'
  else if (val === 'Pat Heung') val = '八鄉'
  else if (val === 'Ping Shan') val = '屏山'
  else if (val === 'San Tin') val = '新田'
  else if (val === 'Shek Kong') val = '石崗'
  else if (val === 'Tai Tong') val = '大棠'
  else if (val === 'Tin Shui Wai') val = '天水圍'
  else if (val === 'Yuen Long') val = '元朗'
  else if (val === 'Peninsula de Macao"') val = '澳門半島'
  else if (val === 'Ilha Verde') val = '青洲'
  else if (val === 'Toi San') val = '台山區'
  else if (val === 'Areia Preta') val = '黑沙環'
  else if (val === 'Iao Hon') val = '祐漢'
  else if (val === 'Povoacao de Mong-Ha"') val = '望廈'
  else if (val === 'Fai Chi Kei') val = '筷子基'
  else if (val === 'Hipodromo') val = '馬場'
  else if (val === 'Lam Mau Tong') val = '林茂塘'
  else if (val === 'Costa') val = '高士德'
  else if (val === 'San Kiu') val = '新橋'
  else if (val === 'Zona de Aterros do Porto Exterior') val = '新口岸填海區'
  else if (val === 'Centro') val = '中區'
  else if (val === 'Colina da Guia') val = '松山'
  else if (val === 'Sao Lourenco') val = '下環'
  else if (val === 'Ilha da Taipa') val = '氹仔'
  else if (val === 'Taipa Island') val = '路氹城'
  else if (val === 'Coloane') val = '路環'
  else if (val === 'Vila De Coloane') val = '路環市區'
  else if (val === 'Seac Pai Va') val = '石排灣'
  else if (val === 'Povoacao de Ka Ho') val = '九澳村'
  else if (val === 'Povoacao Hac Sa') val = '黑沙村'
  else if (val === 'Macao') val = '澳門'
  else if (val === 'All Areas') val = '全部範圍';
  return val;
}

function translateType(val) {
  if (val === 'Convenience Store') val = '便利店';
  else if (val === 'Store') val = '店鋪';
  else if (val === 'Locker') val = '儲物櫃';
  else if (val === 'Post Office') val = '郵局';
  else if (val === 'Petrol Station') val = '加油站';
  return val;
}

function convertRegion(val) {
  if (val === 'Kowloon' || val === '九龍') val = 'KOWLOON';
  else if (val === 'New Territories' || val === '新界' || val === 'Outlying Islands' || val === '離島') val = 'NEW TERRITORIES';
  else val = 'HONG KONG';
  return val;
}

const closeModalSelection = () => {
  //console.log(callBackObject);
  //console.log("submit pressed");
  if (!isEmptyObject(callBackObject)) {
    //

    delete multiFilterObject["province"];
    delete multiFilterObject["city"];
    delete multiFilterObject["district"];

    const newLogic = (callBackObject) => new Promise((resolve, reject) => { // new logic for woocommerce blocks logic
      let updateAddress = (arg) => new Promise((resolve, reject) => {
        jQuery.ajax({
          url: updateShippingAddressEndpoint,
          type: "POST",
          processData: true,
          headers: {
            "X-WP-Nonce": wp_rest_nonce
          },
          data: {
            shippingAddress1: arg.address1En,
            shippingAddress2: arg.address2En,
            locationId: arg.locationId,
            region: arg.province,
            district: arg.district,
            country: arg.country,
            locationType: arg.locationType
          },
          success: function (data) {
            resolve(data);
          }
        });
      });

      jQuery(".shipany-woo-plugin-modal .mapLeftWrapper #filterLoader").show();
      jQuery(".shipany-woo-plugin-modal .mapLeftWrapper div.mapFiltersWrapper").hide();
      jQuery(".shipany-woo-plugin-modal .mapLeftWrapper div.containerParentTextDiv").hide();
      jQuery(".shipany-woo-plugin-modal .modalCloseBtn").hide();
      jQuery(".shipany-woo-plugin-modal .modalConfirmBtnWrapper").hide();
      const overlay = jQuery('<div id="overlay" style="background-color: rgba(128, 128, 128, 0.5);position: fixed;inset: 0px;top: 0;bottom: 0;left: 0;right: 0;"></div>');
      const overflow = jQuery("body").css("overflow");
      jQuery("body").append(overlay);
      jQuery("body").css("overflow", "hidden");
      updateAddress(callBackObject).then((resp) => {
        jQuery(".shipany-woo-plugin-modal .mapLeftWrapper #filterLoader").hide();
        jQuery(".shipany-woo-plugin-modal .mapLeftWrapper div.mapFiltersWrapper").show();
        jQuery(".shipany-woo-plugin-modal .mapLeftWrapper div.containerParentTextDiv").show();
        jQuery(".shipany-woo-plugin-modal .modalCloseBtn").show();
        jQuery(".shipany-woo-plugin-modal .modalConfirmBtnWrapper").show();
        overlay.remove();
        jQuery("body").css("overflow", overflow);
        const modal = document.getElementById("shipany-woo-plugin-modal");
        modal.classList.remove("shipany-woo-plugin-showModal");
        resolve(resp);
      })
    });

    const oldLogic = (callBackObject) => new Promise((resolve, reject) => { // old logic for woocommerce using shortcodes
      var final_address_line = lang.includes('zh') ? callBackObject.address1 : callBackObject.address1En
      if (final_address_line.charAt(0) !== "[") {
        final_address_line = '[' + callBackObject.locationId + '] ' + final_address_line
      }
      jQuery('#shipping_country').val(callBackObject.country).trigger('change')
      if (shipany_setting.shipany_bypass_billing_address == 'no' || shipany_setting.shipany_bypass_billing_address == null ) {
        jQuery('#billing_country').val(callBackObject.country).trigger('change')
        if (shipany_setting.shipany_locker_length_truncate > 11 && final_address_line.length > 11) {
          jQuery('#billing_address_1').val(final_address_line.substring(0, shipany_setting.shipany_locker_length_truncate))
        } else {
          jQuery('#billing_address_1').val(final_address_line)
        }
        jQuery('#billing_city').val(callBackObject.district)
        jQuery('select#billing_state.state_select.select2-hidden-accessible').val(convertRegion(callBackObject.province))
        jQuery('span#select2-billing_state-container.select2-selection__rendered').text(callBackObject.province)
        jQuery('input#billing_state').val(callBackObject.province)
        if(region_idx == 2){
          jQuery('#billing_postcode').val('000000');
        }
      } else {
        if(jQuery('#ship-to-different-address-checkbox').get(0) && !jQuery('#ship-to-different-address-checkbox').get(0).checked){
          jQuery('#ship-to-different-address-checkbox').click();
        }
      }
      if (shipany_setting.shipany_locker_length_truncate > 11 && final_address_line.length > 11) {
        jQuery('#shipping_address_1').val(final_address_line.substring(0, shipany_setting.shipany_locker_length_truncate))
      } else {
        jQuery('#shipping_address_1').val(final_address_line)
      }
      jQuery('#shipping_city').val(callBackObject.district)
      jQuery('select#shipping_state.state_select.select2-hidden-accessible').val(convertRegion(callBackObject.province))
      jQuery('span#select2-shipping_state-container.select2-selection__rendered').text(callBackObject.province)
      jQuery('input#shipping_state').val(callBackObject.province)
      if(region_idx == 2){
        jQuery('#shipping_postcode').val('000000');
      }
      setTimeout(() => {
        resolve();
      }, 500);
    });

    if (jQuery('[data-block-name="woocommerce/checkout"], [data-block-name="woocommerce/cart"]').length) {
      callBackObject['useShippingAsBilling'] = getUseShippingAsBilling() ? 'yes' : 'no';
      newLogic(callBackObject).then((resp) => {
        setShippingAddress(resp.data.shipping);
        __internalSetUseShippingAsBilling(resp.data.useShippingAsBilling);
        setBillingAddress(resp.data.billing);
      });
    } else if (jQuery('.woocommerce-checkout').length) {
      oldLogic(callBackObject).then(() => {
        const modal = document.getElementById("shipany-woo-plugin-modal");
        modal.classList.remove("shipany-woo-plugin-showModal");
        jQuery('.woocommerce-checkout').trigger('update')
      });
    } else if (jQuery('.cart_totals').length) {
      callBackObject['useShippingAsBilling'] = 'yes';
      newLogic(callBackObject).then(() => {
        jQuery('.cart_totals').trigger('wc_update_cart')
      });
    }
  }
};

// Create modal unique elements
if (_mode === "modal") {
  var modal = document.getElementById("shipany-woo-plugin-modal");
  const closeModal = () => {
    delete multiFilterObject["province"];
    delete multiFilterObject["city"];
    delete multiFilterObject["district"];

    // console.log("multiFilterObject after close  ", multiFilterObject);
    const modal = document.getElementById("shipany-woo-plugin-modal");
    modal.classList.remove("shipany-woo-plugin-showModal");
    var radioBtns = document.querySelectorAll('input[type="radio"]');

    radioBtns.forEach((item) => {
      item.style.display = null;
      //console.log(item.style);
    });

    // location.reload();
  };

  const openModal = () => {
    const modal = document.getElementById("shipany-woo-plugin-modal");
    modal.classList.add("shipany-woo-plugin-showModal");
  };

  //var modalOpenBtn = document.getElementById("modalOpenBtn");
  //modalOpenBtn.addEventListener("click", openModal);

  // create top bar elements
  var topBar = document.createElement("div");
  topBar.className = "modalTopBar";

  let closeBtn = document.createElement("span");
  Object.assign(closeBtn, {
    className: "modalCloseBtn",
    onclick: closeModal,
    innerHTML: "✕",
  });

  /*
  var modalTitle = document.createElement("span");
  if (_locale === "en") {
    modalTitle.innerHTML = "Shipping method";
  } else if (_locale === "zh") {
    modalTitle.innerHTML = "郵寄方式";
  }
  topBar.appendChild(modalTitle);
  */

  topBar.appendChild(closeBtn);

  modal.insertBefore(topBar, modal.firstChild);
  // create top bar elements End

  // create confirm button
  var modalConfirmBtnWrapper = document.createElement("div");
  modalConfirmBtnWrapper.className = "modalConfirmBtnWrapper";
  var modalConfirmBtn = document.createElement("button");
  if (_locale === "en") {
    Object.assign(modalConfirmBtn, {
      className: "modalConfirmBtn",
      innerHTML: "Confirm",
      onclick: closeModalSelection,
      //onclick: confirm
      /*onclick: () => {
        console.log("confirm en");
      },*/
    });
  } else if (_locale === "zh") {
    Object.assign(modalConfirmBtn, {
      className: "modalConfirmBtn",
      innerHTML: "確定",
      onclick: closeModalSelection,
      /*onclick: () => {
        console.log("confirm dd");
      },*/
    });
  }

  modalConfirmBtnWrapper.appendChild(modalConfirmBtn);
  modal.appendChild(modalConfirmBtnWrapper);
  var modalPowerBy = document.createElement("div");
  Object.assign(modalPowerBy, {
    className: "copyright-text",
    innerHTML: "Powered by <a href='https://www.shipany.io' target='_blank' rel='noopener noreferrer' style='color:#15669d'>ShipAny</a>"
  });
  modal.appendChild(modalPowerBy)
}
// Create modal unique element End

// create top level container HTML
let containerDiv = document.createElement("div");
containerDiv.className = "containerDiv";
let containerMapBox = document.getElementById("mapBox");
if (_mode === "dropdown") {
  containerMapBox.style = "width: 100%";
}
containerDiv.appendChild(containerMapBox);

//Added from the onset so map can be displayed
if (_mode === "modal") {
  document.getElementById("mapWrapper").appendChild(containerDiv);
} else {
  document.body.appendChild(containerDiv);
}

// create left side dropdown and list
let leftWrapper = document.createElement("div");
leftWrapper.className = "mapLeftWrapper";
let filtersWrapper = document.createElement("div");
filtersWrapper.className = "mapFiltersWrapper";

let districtFilter = document.createElement("SELECT");
districtFilter.className = "districtFilter mapFilter";

let regionFilter = document.createElement("SELECT");
regionFilter.className = "regionFilter mapFilter";

let areaFilter = document.createElement("SELECT");
areaFilter.className = "areaFilter mapFilter";

// Create section wrapper:
// If dropdown, create top filters bar / If not dropdown, create left radio button list
if (_mode === "dropdown") {
  document.getElementById("mapBarWrapper").appendChild(filtersWrapper);

  //create div to show selected item details from the list
  dropDownSelectedItemContainer = document.createElement("div");
  dropDownSelectedItemContainer.className = "dropDownSelectedItemContainer";
  containerDiv.appendChild(dropDownSelectedItemContainer);
} else {
  leftWrapper.appendChild(filtersWrapper);
}

let containerParentDiv = document.createElement("div");
containerParentDiv.className = "containerParentTextDiv";

let searchBar;
// Create search bar
if (_withSearchBar) {
  searchBar = document.getElementById("searchBar");
}

let mapView;
if (_defaultLocation === "HK") {
  mapView = new ol.View({
    center: ol.proj.fromLonLat([114.177216, 22.302711]),
    zoom: 12,
  });
} else if (_defaultLocation === "AU") {
  mapView = new ol.View({
    center: ol.proj.fromLonLat([133.7751, 25.2744]),
    zoom: 12,
  });
}

function capitaliseString(str) {
  if (str.includes("_")) {
    var res = str.split("_");

    var first = res[0][0].toUpperCase() + res[0].slice(1).toLowerCase();
    var last = res[1][0].toUpperCase() + res[1].slice(1).toLowerCase();

    return first + " " + last;
  } else {
    return str;
  }
}

let counter = 0
locDataObservable.subscribe({
  next: (result) => {
    counter = counter + 1
    // console.log('print result')
    // console.log(counter);
    // console.log(result);

    let lat = result.latitude;
    let long = result.longitude;
    let coordinates = ol.proj.fromLonLat([long, lat]);

    var item = [];
    item.address1 = `[${result.code}] ${result.addr.lns_cht[0]}`;
    item.address1En = `[${result.code}] ${result.addr.lns[0]}`;
    item.address2 = `[${result.code}] ${result.addr.lns_cht[1]}`;
    item.address2En = `[${result.code}] ${result.addr.lns[1]}`;
    item.city = result.addr[region_idx == 2 ? 'distr' : 'state'];
    item.country = {
      'TWN': 'TW',
      'HKG': 'HK',
      'MAC': 'MO',
    }[result.addr.cnty],
    item.district = result.addr.distr;
    item.province = result.addr[region_idx == 2 ? 'city' : 'reg'];
    item.latitude = lat;
    item.longitude = long;
    item.locationEn = result.name;
    item.locationId = result.code;
    item.locationName = {
      'zh': result.name_cht,
      'en': result.name
    }[_locale] || result.name;
    item.locationType = capitaliseString(result.typ);
    item.operatingTime = result.open_hours;
    item.operatingTimeCN = result.open_hours_cht;
    item.geometry = new ol.geom.Point(coordinates);
    //numberOfFilteredItems.push(result);
    numberOfFilteredItems.push(item);

    lockerLocations.push(
      new ol.Feature({
        type: "click",
        address1: result.addr.lns_cht[0],
        address1En: result.addr.lns[0],
        address2: result.addr.lns_cht[1],
        address2En: result.addr.lns[1],
        city: result.addr[region_idx == 2 ? 'distr' : 'state'],
        district: result.addr.distr,
        province: result.addr[region_idx == 2 ? 'city' : 'reg'],
        country: {
          'TWN': 'TW',
          'HKG': 'HK',
          'MAC': 'MO',
        }[result.addr.cnty],
        latitude: lat,
        longitude: long,
        locationEn: result.name,
        locationId: result.code,
        locationName: _locale === 'zh' ? result.name_cht : result.name,
        locationType: capitaliseString(result.typ),
        operatingTime: result.open_hours,
        operatingTimeCN: result.open_hours_cht,
        geometry: new ol.geom.Point(coordinates),
      })
    );

    //console.log("before complete vectorlayer", vectorLayer)
    //Important: check answer here: https://stackoverflow.com/questions/56145477/how-to-delete-markers-from-osm
    /*
    vectorLayer.getSource().addFeature(
      new ol.Feature({
        type: "click",
        address1: result.addr.lns_cht[0],
        address1En: result.addr.lns[0],
        address2: result.addr.lns_cht[1],
        address2En: result.addr.lns[1],
        city: result.addr.state,
        district: result.addr.distr,
        province: result.addr.reg,
        latitude: lat,
        longitude: long,
        locationEn: result.name,
        locationId: result.code,
        locationName: result.name_cht,
        locationType: capitaliseString(result.typ),
        operatingTime: result.open_hours,
        operatingTimeCN: result.open_hours_cht,
        geometry: new ol.geom.Point(coordinates),
      })
    );
    */
    if (containerParentDiv.childElementCount >= 1000) {
      return;
    }

    var containerTextDiv = document.createElement("div");
    containerTextDiv.className = "containerChildTextDiv";

    var locationContainer = document.createElement("INPUT");
    Object.assign(locationContainer, {
      type: "radio",
      name: "list",
      id: result.code,
      className: "mapRadioBtn",
    });

    var locationContentDiv = document.createElement("label");
    Object.assign(locationContentDiv, {
      htmlFor: result.code,
      className: "shipany-label",
    });
    var locationContentText = document.createElement("div");
    if (result.name_cht || result.code) {
      var locationName = document.createElement("P"); // Create a <p> element
      locationName.setAttribute("class", "locationName");
      if (_locale === "en") {
        // First time load the list
        if (result.typ === "Convenience Store" || result.typ === "Petrol Station") {
          locationName.innerHTML =
            result.code + " - " + (result?.name ?? "").replace(result.code, '') + ' ' + result.addr.lns[0]; // Insert text //replace handled zto prefix id
        } else {
          locationName.innerHTML =
            result.code + " - " + (result?.name ?? "").replace(result.code, '') + ' '; // Insert text          
        }

      } else if (_locale === "zh") {
        if (result.typ === "Convenience Store" || result.typ === "Petrol Station") {
          locationName.innerHTML =
            result.code + " - " + (result?.name_cht ?? "").replace(result.code, '') + ' ' + result.addr.lns_cht; // Insert text
        } else {
          locationName.innerHTML =
            result.code + " - " + (result?.name_cht ?? "").replace(result.code, ''); // Insert text    
        }
        result.code + " - " + (result?.name_cht ?? "").replace(result.code, ''); // Insert text
      }
      locationContentText.appendChild(locationName);
    }

    /*if (result.operatingTime) {
                var operatingTime = document.createElement("P");
                operatingTime.setAttribute("class", "operatingTime")
                operatingTime.innerHTML = result.operatingTime; 
                locationContentText.appendChild(operatingTime);
            }*/

    if (result.open_hours) {
      var operatingTime = document.createElement("P");
      operatingTime.setAttribute("class", "operatingTime");

      if (_locale === "en") {
        operatingTime.innerHTML = result.open_hours;
      } else if (_locale === "zh") {
        operatingTime.innerHTML = result.open_hours_cht;
      }

      locationContentText.appendChild(operatingTime);
    }

    var address = document.createElement("P");
    if (_locale === "en") {
      address.innerHTML = result.addr.lns[0];
    } else if (_locale === "zh") {
      address.innerHTML = result.addr.lns_cht[0];
    }
    address.setAttribute("class", "contentAddress");
    locationContentText.appendChild(address);

    var locationType = document.createElement("P");
    if (result.typ) {
      if (_locale === "zh") {
        Object.assign(locationType, {
          innerHTML: translateType(capitaliseString(result.typ)),
          className: "contentLocationType",
        });
      } else {
        Object.assign(locationType, {
          innerHTML: capitaliseString(result.typ),
          className: "contentLocationType",
        });
      }

    }
    locationContentText.appendChild(locationType);

    var city = document.createElement("P");
    if (result.typ) {
      if (_locale === "zh") {
        Object.assign(city, {
          innerHTML:
            translateType(capitaliseString(result.typ)) + " | " + translateDistrict(result?.addr[region_idx == 2 ? 'distr' : 'state'] ?? ""),
          className: "contentCity",
        });
      } else {
        Object.assign(city, {
          innerHTML:
            capitaliseString(result.typ) + " | " + (result?.addr[region_idx == 2 ? 'distr' : 'state'] ?? ""),
          className: "contentCity",
        });
      }

    } else {
      Object.assign(city, {
        innerHTML: result?.addr.state ?? "",
        className: "contentCity",
      });
    }
    locationContentText.appendChild(city);

    var howToGetThere = document.createElement("a");
    if (_locale === "en" || 1) {
      Object.assign(howToGetThere, {
        href: "#",
        className: "howToGetThere",
        id: "howToGetThere",
        style: "display: none",

        innerHTML: "How to get there",
      });
    } else if (_locale === "zh") {
      Object.assign(howToGetThere, {
        href: "#",
        className: "howToGetThere",
        id: "howToGetThere",
        style: "display: none",
        innerHTML: "如何找到智能櫃？",
      });
    }

    //document.getElementById("howToGetThere").innerHTML.style.display = "none";
    locationContentText.appendChild(howToGetThere);
    //console.log( document.getElementById('howToGetThere'))//setAttribute("hidden",true)

    locationContentDiv.appendChild(locationContentText);

    containerTextDiv.appendChild(locationContainer);
    containerTextDiv.appendChild(locationContentDiv);
    containerParentDiv.appendChild(containerTextDiv);
    if (_mode !== "dropdown") {
      if (counter === 1) {
        leftWrapper.appendChild(containerParentDiv);
        const loader = document.createElement("div");
        loader.className = "loader";
        loader.id = "filterLoader";
        loader.style.display = "none";
        leftWrapper.appendChild(loader);
        containerDiv.appendChild(leftWrapper);
      }
    }

    containerDiv.appendChild(containerMapBox);

    //add to dropdownlist
    if (_mode === "dropdown") {
      var sb = new StringBuilder();
      sb.append(result.code || "empty");
      sb.append(" - ");
      if (_locale === "en") {
        sb.append(result.name);
        if (result.typ === "Convenience Store" || result.typ === "Store") {
          sb.append(' ' + result.addr.lns);
        }
      } else if (_locale === "zh") {
        sb.append(result.name_cht);
        if (result.typ === "Convenience Store" || result.typ === "Store") {
          sb.append(' ' + result.addr.lns_cht);
        }
      }
      var myString = sb.toString();

      let customDropDownItem = document.createElement("div");
      customDropDownItem.className = "customDropDownItem";
      customDropDownItem.id = result.code;
      let customDropDownItemLocationName = document.createElement("p");
      let customDropDownItemLocationTypeCity = document.createElement("p");
      let customDropDownItemCoords = document.createElement("p");

      Object.assign(customDropDownItemLocationName, {
        className: "customDropDownItemLocationName",
        innerHTML: myString,
      });

      Object.assign(customDropDownItemLocationTypeCity, {
        className: "customDropDownItemLocationTypeCity",
        innerHTML: capitaliseString(result.typ) + "  " + result.addr.state,
      });

      Object.assign(customDropDownItemCoords, {
        hidden: true,
        className: "customDropDownItemCoords",
        innerHTML: long + "-" + lat,
      });

      //    customDropDownItem.appendChild(customDropdownRadioHidden);
      customDropDownItem.appendChild(customDropDownItemLocationName);
      customDropDownItem.appendChild(customDropDownItemLocationTypeCity);
      customDropDownItem.appendChild(customDropDownItemCoords);

      dropDownElementList.push(customDropDownItem);

      console.log("1" + " " + new Date().getTime());
    }
  },
  complete: () => {
    console.log("2" + " " + new Date().getTime());
    if (_mode === "dropdown") {
      let customDropDownContainer = document.createElement("div");
      customDropDownContainer.className = "customDropDownContainer";

      let customDropDownBox = document.createElement("div");
      customDropDownBox.className = "customDropDownBox";

      for (var i = 0; i < dropDownElementList.length; i++) {
        customDropDownBox.appendChild(dropDownElementList[i]);
      }
      customDropDownContainer.appendChild(customDropDownBox);
      dropDownElement.appendChild(customDropDownContainer);
      document.getElementById("mapBarWrapper").appendChild(dropDownElement);
      document.getElementById("mapBox").style.display = "none";

      //dropDownSelectedItemContainer

      assignOnClickToDropDownItems();
    }
    if (_mode === "modal") {
      document.getElementById("mapWrapper").appendChild(containerDiv);
    } else {
      document.body.appendChild(containerDiv);
    }

    /*
       sort based on following:
             Naming and Ordering should be:
              7 Eleven
              4PX Pickup Point
              4PX Locker
              HKPost Locker

 
       */

    (districtFilterData = Array.from(new Set(districtFilterData))),
      (regionFilterData = Array.from(new Set(regionFilterData))),
      (areaFilterData = Array.from(new Set(areaFilterData)));

    reactiveRegionData = from(regionFilterData).subscribe({
      next: (result) => {
        if (result != '') {
          let regionFilterItem = document.createElement("option");

          regionFilterItem.innerHTML = result;
          regionFilterItem.label = (_locale === "zh") ? translateRegion(regionFilterItem.label) : (regionFilterItem.label);
          regionFilter.appendChild(regionFilterItem);
        }
      },
      complete: () => {
        var dropDownSelectionEmpty = document.createElement("OPTION");
        Object.assign(dropDownSelectionEmpty, {
          disabled: true,
          selected: true,
        });
        if (_locale === "en") {
          dropDownSelectionEmpty.innerHTML = "Region";
          // dropDownSelectionEmpty.innerHTML = "區域";
        } else if (_locale === "zh") {
          dropDownSelectionEmpty.innerHTML = "區域";
        }

        //console.log(regionFilter.firstChild);
        if (regionFilter.firstChild.innerHTML === "All Regions" || regionFilter.firstChild.innerHTML === "全部區域" || regionFilter.firstChild.innerHTML === "區域") {
          regionFilter.insertBefore(
            dropDownSelectionEmpty,
            regionFilter.firstChild
          );
        }

        let regionFilterTitle = document.createElement("p");
        if (_filterCriteria === null)
          regionFilterTitle.innerHTML = "2. Select Region";
        else {
          if (_locale === "en") {
            regionFilterTitle.innerHTML = "1. Select Region";
          } else if (_locale === "zh") {
            regionFilterTitle.innerHTML = "1. 選擇區域";
          }
        }
        regionFilterTitle.className = "regionFilterTitle";
        filtersWrapper.appendChild(regionFilterTitle);
        filtersWrapper.appendChild(regionFilter);

        console.log("4" + " " + new Date().getTime());
      },
    });

    reactiveDistrictData = from(districtFilterData).subscribe({
      next: (result) => {
        if (result != "") {
          let districtFilterItem = document.createElement("option");
          districtFilterItem.innerHTML = result;
          if (_locale === "zh") districtFilterItem.label = translateDistrict(districtFilterItem.label);
          districtFilter.appendChild(districtFilterItem);
        }
      },
      complete: () => {
        var dropDownSelectionEmpty = document.createElement("OPTION");
        Object.assign(dropDownSelectionEmpty, {
          disabled: true,
          selected: true,
        });
        if (_locale === "en") {
          dropDownSelectionEmpty.innerHTML = "District";
          // dropDownSelectionEmpty.innerHTML = "地區";
        } else if (_locale === "zh") {
          dropDownSelectionEmpty.innerHTML = "地區";
        }

        if (districtFilter.firstChild.innerHTML === "All Districts" || districtFilter.firstChild.innerHTML === "全部地區" || districtFilter.firstChild.innerHTML === "地區") {
          districtFilter.insertBefore(
            dropDownSelectionEmpty,
            districtFilter.firstChild
          );
        }

        let districtFilterTitle = document.createElement("p");
        if (_filterCriteria === null)
          districtFilterTitle.innerHTML = "3. Select District";
        else {
          if (_locale === "en") {
            districtFilterTitle.innerHTML = "2. Select District";
          } else if (_locale === "zh") {
            districtFilterTitle.innerHTML = "2. 選擇地區";
          }
        }

        districtFilterTitle.className = "districtFilterTitle";
        filtersWrapper.appendChild(districtFilterTitle);
        filtersWrapper.appendChild(districtFilter);

        console.log("5" + " " + new Date().getTime());
      },
    });

    reactiveAreaData = from(areaFilterData).subscribe({
      next: (result) => {
        if (result != '') {
          let areaFilterItem = document.createElement("option");
          areaFilterItem.innerHTML = result;
          if (_locale === "zh") areaFilterItem.label = translateArea(areaFilterItem.label);
          areaFilter.appendChild(areaFilterItem);
        }
      },
      complete: () => {
        var dropDownSelectionEmpty = document.createElement("OPTION");
        Object.assign(dropDownSelectionEmpty, {
          disabled: true,
          selected: true,
        });
        if (_locale === "en") {
          dropDownSelectionEmpty.innerHTML = "Area";
          // dropDownSelectionEmpty.innerHTML = "範圍";
        } else if (_locale === "zh") {
          dropDownSelectionEmpty.innerHTML = "範圍";
        }
        //console.log("area filter 90 ", areaFilter.firstChild);
        if (areaFilter.firstChild.innerHTML === "All Areas" || areaFilter.firstChild.innerHTML === "全部範圍" || areaFilter.firstChild.innerHTML === "範圍") {
          areaFilter.insertBefore(
            dropDownSelectionEmpty,
            areaFilter.firstChild
          );
        }

        let areaFilterTitle = document.createElement("p");
        if (_filterCriteria === null) {
          areaFilterTitle.innerHTML = "4. Select Area";
        } else {
          if (_locale === "en") {
            areaFilterTitle.innerHTML = "3. Select Area";
          } else if (_locale === "zh") {
            areaFilterTitle.innerHTML = "3. 選擇範圍";
          }
        }

        areaFilterTitle.className = "areaFilterTitle";
        filtersWrapper.appendChild(areaFilterTitle);
        filtersWrapper.appendChild(areaFilter);

        console.log("6" + " " + new Date().getTime());
      },
    });

    if (_withSearchBar) {
      document.getElementsByClassName("mapFiltersWrapper")[0].style.display =
        "none";
    }
    document.getElementById("loader").style.display = "none";

  },
});

console.log("0" + " " + new Date().getTime());

function refreshData() {
  //
  console.log("1" + " " + new Date().getTime());
  document.getElementsByClassName("mapFiltersWrapper")[0].innerHTML = "";
  document.getElementById("loader").style.display = "block";
  locDataObservable.subscribe({
    next: (result) => {
      let lat = result.latitude;
      let long = result.longitude;
      let coordinates = ol.proj.fromLonLat([long, lat]);

      var item = [];
      item.address1 = `[${result.code}] ${result.addr.lns_cht[0]}`;
      item.address1En = `[${result.code}] ${result.addr.lns[0]}`;
      item.address2 = `[${result.code}] ${result.addr.lns_cht[1]}`;
      item.address2En = `[${result.code}] ${result.addr.lns[1]}`;
      item.city = result.addr.state;
      item.country = {
        'TWN': 'TW',
        'HKG': 'HK',
        'MAC': 'MO',
      }[result.addr.cnty];
      item.district = result.addr.distr;
      item.province = result.addr.reg;
      item.latitude = lat;
      item.longitude = long;
      item.locationEn = result.name;
      item.locationId = result.code;
      item.locationName = _locale === 'zh' ? result.name_cht : result.name;
      item.locationType = capitaliseString(result.typ);
      item.operatingTime = result.open_hours;
      item.operatingTimeCN = result.open_hours_cht;
      item.geometry = new ol.geom.Point(coordinates);
      //numberOfFilteredItems.push(result);
      numberOfFilteredItems.push(item);

      lockerLocations.push(
        new ol.Feature({
          type: "click",
          address1: `[${result.code}] ${result.addr.lns_cht[0]}`,
          address1En: `[${result.code}] ${result.addr.lns[0]}`,
          address2: `[${result.code}] ${result.addr.lns_cht[1]}`,
          address2En: `[${result.code}] ${result.addr.lns[1]}`,
          city: result.addr.state,
          country: {
            'TWN': 'TW',
            'HKG': 'HK',
            'MAC': 'MO',
          }[result.addr.cnty],
          district: result.addr.distr,
          province: result.addr.reg,
          latitude: lat,
          longitude: long,
          locationEn: result.name,
          locationId: result.code,
          locationName: _locale === 'zh' ? result.name_cht : result.name,
          locationType: capitaliseString(result.typ),
          operatingTime: result.open_hours,
          operatingTimeCN: result.open_hours_cht,
          geometry: new ol.geom.Point(coordinates),
        })
      );

      //console.log("before complete vectorlayer", vectorLayer)
      //Important: check answer here: https://stackoverflow.com/questions/56145477/how-to-delete-markers-from-osm
      /*
      vectorLayer.getSource().addFeature(
        new ol.Feature({
          type: "click",
          address1: result.addr.lns_cht[0],
          address1En: result.addr.lns[0],
          address2: result.addr.lns_cht[1],
          address2En: result.addr.lns[1],
          city: result.addr.state,
          district: result.addr.distr,
          province: result.addr.reg,
          latitude: lat,
          longitude: long,
          locationEn: result.name,
          locationId: result.code,
          locationName: result.name_cht,
          locationType: capitaliseString(result.typ),
          operatingTime: result.open_hours,
          operatingTimeCN: result.open_hours_cht,
          geometry: new ol.geom.Point(coordinates),
        })
      );
      */

      console.log("2" + " " + new Date().getTime());

      var containerTextDiv = document.createElement("div");
      containerTextDiv.className = "containerChildTextDiv";

      // var locationContainer = document.createElement("div");
      // locationContainer.className = result.locationId
      var locationContainer = document.createElement("INPUT");
      Object.assign(locationContainer, {
        type: "radio",
        name: "list",
        id: result.code,
        className: "mapRadioBtn",
      });

      var locationContentDiv = document.createElement("label");
      Object.assign(locationContentDiv, {
        htmlFor: result.code,
        className: "shipany-label",
      });

      var locationContentText = document.createElement("div");

      if (result.name_cht || result.code) {
        var locationName = document.createElement("P"); // Create a <p> element
        locationName.setAttribute("class", "locationName");
        if (_locale === "en") {
          if (result.typ === "Convenience Store" || result.typ === "Store") {
            locationName.innerHTML =
              result.code + " - " + (result?.name ?? "") + ' ' + result.addr.lns; // Insert text
          } else {
            locationName.innerHTML =
              result.code + " - " + (result?.name ?? ""); // Insert text          
          }
        } else if (_locale === "zh") {
          if (result.typ === "Convenience Store" || result.typ === "Store") {
            locationName.innerHTML =
              result.code + " - " + (result?.name_cht ?? "") + ' ' + result.addr.lns_cht; // Insert text
          } else {
            locationName.innerHTML =
              result.code + " - " + (result?.name_cht ?? ""); // Insert text    
          }
        }
        locationContentText.appendChild(locationName);
      }

      /*if (result.operatingTime) {
                var operatingTime = document.createElement("P");
                operatingTime.setAttribute("class", "operatingTime")
                operatingTime.innerHTML = result.operatingTime; 
                locationContentText.appendChild(operatingTime);
            }*/

      if (result.open_hours) {
        var operatingTime = document.createElement("P");
        operatingTime.setAttribute("class", "operatingTime");

        if (_locale === "en") {
          operatingTime.innerHTML = result.open_hours;
        } else if (_locale === "zh") {
          operatingTime.innerHTML = result.open_hours_cht;
        }

        locationContentText.appendChild(operatingTime);
      }

      var address = document.createElement("P");
      if (_locale === "en") {
        address.innerHTML = `[${result.code}] ${result.addr.lns[0]}`;
      } else if (_locale === "zh") {
        address.innerHTML = `[${result.code}] ${result.addr.lns_cht[0]}`;
      }
      address.setAttribute("class", "contentAddress");
      locationContentText.appendChild(address);

      var locationType = document.createElement("P");
      if (result.typ) {
        Object.assign(locationType, {
          innerHTML: capitaliseString(result.typ),
          className: "contentLocationType",
        });
      }
      locationContentText.appendChild(locationType);

      var city = document.createElement("P");
      if (result.typ) {
        Object.assign(city, {
          innerHTML:
            capitaliseString(result.typ) +
            " | " +
            (result?.addr.state ?? ""),
          className: "contentCity",
        });
      } else {
        Object.assign(city, {
          innerHTML: result?.addr.state ?? "",
          className: "contentCity",
        });
      }
      locationContentText.appendChild(city);

      var howToGetThere = document.createElement("a");
      if (_locale === "en") {
        Object.assign(howToGetThere, {
          href: "#",
          className: "howToGetThere",
          id: "howToGetThere",
          style: "display: none",

          innerHTML: "How to get there",
        });
      } else if (_locale === "zh") {
        Object.assign(howToGetThere, {
          href: "#",
          className: "howToGetThere",
          id: "howToGetThere",
          style: "display: none",
          innerHTML: "如何找到智能櫃？",
        });
      }

      //document.getElementById("howToGetThere").innerHTML.style.display = "none";
      locationContentText.appendChild(howToGetThere);
      //console.log( document.getElementById('howToGetThere'))//setAttribute("hidden",true)

      locationContentDiv.appendChild(locationContentText);

      containerTextDiv.appendChild(locationContainer);
      containerTextDiv.appendChild(locationContentDiv);
      containerParentDiv.appendChild(containerTextDiv);
      if (_mode !== "dropdown") {
        if (counter === 1) {
          leftWrapper.appendChild(containerParentDiv);
          const loader = document.createElement("div");
          loader.className = "loader";
          loader.id = "filterLoader";
          loader.style.display = "none";
          leftWrapper.appendChild(loader);
          containerDiv.appendChild(leftWrapper);
        }
      }

      containerDiv.appendChild(containerMapBox);

      //add to dropdownlist
      if (_mode === "dropdown") {
        var sb = new StringBuilder();
        sb.append(result.code || "empty");
        sb.append(" - ");
        if (_locale === "en") {
          sb.append(result.name);
          if (result.typ === "Convenience Store" || result.typ === "Store") {
            sb.append(' ' + result.addr.lns);
          }
        } else if (_locale === "zh") {
          sb.append(result.name_cht);
          if (result.typ === "Convenience Store" || result.typ === "Store") {
            sb.append(' ' + result.addr.lns_cht);
          }
        }
        var myString = sb.toString();

        let customDropDownItem = document.createElement("div");
        customDropDownItem.className = "customDropDownItem";
        customDropDownItem.id = result.code;
        let customDropDownItemLocationName = document.createElement("p");
        let customDropDownItemLocationTypeCity = document.createElement("p");
        let customDropDownItemCoords = document.createElement("p");

        Object.assign(customDropDownItemLocationName, {
          className: "customDropDownItemLocationName",
          innerHTML: myString,
        });

        Object.assign(customDropDownItemLocationTypeCity, {
          className: "customDropDownItemLocationTypeCity",
          innerHTML: capitaliseString(result.typ) + "  " + result.addr.state,
        });

        Object.assign(customDropDownItemCoords, {
          hidden: true,
          className: "customDropDownItemCoords",
          innerHTML: long + "-" + lat,
        });

        //    customDropDownItem.appendChild(customDropdownRadioHidden);
        customDropDownItem.appendChild(customDropDownItemLocationName);
        customDropDownItem.appendChild(customDropDownItemLocationTypeCity);
        customDropDownItem.appendChild(customDropDownItemCoords);

        dropDownElementList.push(customDropDownItem);
      }
    },
    complete: () => {
      if (_mode === "dropdown") {
        let customDropDownContainer = document.createElement("div");
        customDropDownContainer.className = "customDropDownContainer";

        let customDropDownBox = document.createElement("div");
        customDropDownBox.className = "customDropDownBox";

        for (var i = 0; i < dropDownElementList.length; i++) {
          customDropDownBox.appendChild(dropDownElementList[i]);
        }
        customDropDownContainer.appendChild(customDropDownBox);
        dropDownElement.appendChild(customDropDownContainer);
        document.getElementById("mapBarWrapper").appendChild(dropDownElement);
        document.getElementById("mapBox").style.display = "none";

        //dropDownSelectedItemContainer

        assignOnClickToDropDownItems();
      }
      if (_mode === "modal") {
        document.getElementById("mapWrapper").appendChild(containerDiv);
      } else {
        document.body.appendChild(containerDiv);
      }

      (districtFilterData = Array.from(new Set(districtFilterData))),
        (regionFilterData = Array.from(new Set(regionFilterData))),
        (areaFilterData = Array.from(new Set(areaFilterData)));

      reactiveRegionData = from(regionFilterData).subscribe({
        next: (result) => {
          let regionFilterItem = document.createElement("option");
          regionFilterItem.innerHTML = result;
          // regionFilterItem.label = translateRegion(regionFilterItem.label)
          //regionFilter.appendChild(regionFilterItem);
        },
        complete: () => {
          var dropDownSelectionEmpty = document.createElement("OPTION");
          Object.assign(dropDownSelectionEmpty, {
            disabled: true,
            selected: true,
          });
          if (_locale === "en") {
            dropDownSelectionEmpty.innerHTML = "Region";
          } else if (_locale === "zh") {
            dropDownSelectionEmpty.innerHTML = "區域";
          }

          let regionFilterTitle = document.createElement("p");
          if (_filterCriteria === null)
            regionFilterTitle.innerHTML = "2. Select Region";
          else {
            regionFilterTitle.innerHTML = "1. Select Region";
          }
          regionFilterTitle.className = "regionFilterTitle";
          filtersWrapper.appendChild(regionFilterTitle);
          filtersWrapper.appendChild(regionFilter);
        },
      });

      reactiveDistrictData = from(districtFilterData).subscribe({
        next: (result) => {
          let districtFilterItem = document.createElement("option");
          districtFilterItem.innerHTML = result;
          // districtFilterItem.label = translateDistrict(districtFilterItem.label)
          //districtFilter.appendChild(districtFilterItem);
        },
        complete: () => {
          var dropDownSelectionEmpty = document.createElement("OPTION");
          Object.assign(dropDownSelectionEmpty, {
            disabled: true,
            selected: true,
          });
          if (_locale === "en") {
            dropDownSelectionEmpty.innerHTML = "District";
          } else if (_locale === "zh") {
            dropDownSelectionEmpty.innerHTML = "地區";
          }

          let districtFilterTitle = document.createElement("p");
          if (_filterCriteria === null)
            districtFilterTitle.innerHTML = "3. Select District";
          else districtFilterTitle.innerHTML = "2. Select District";
          districtFilterTitle.className = "districtFilterTitle";
          filtersWrapper.appendChild(districtFilterTitle);
          filtersWrapper.appendChild(districtFilter);
        },
      });

      reactiveAreaData = from(areaFilterData).subscribe({
        next: (result) => {
          let areaFilterItem = document.createElement("option");
          areaFilterItem.innerHTML = result;
          // areaFilterItem.label = translateArea(areaFilterItem.label)
          //areaFilter.appendChild(areaFilterItem);
        },
        complete: () => {
          var dropDownSelectionEmpty = document.createElement("OPTION");
          Object.assign(dropDownSelectionEmpty, {
            disabled: true,
            selected: true,
          });
          if (_locale === "en") {
            dropDownSelectionEmpty.innerHTML = "Area";
          } else if (_locale === "zh") {
            dropDownSelectionEmpty.innerHTML = "範圍";
          }

          let areaFilterTitle = document.createElement("p");
          if (_filterCriteria === null) {
            areaFilterTitle.innerHTML = "4. Select Area";
          } else {
            areaFilterTitle.innerHTML = "3. Select Area";
          }

          areaFilterTitle.className = "areaFilterTitle";
          filtersWrapper.appendChild(areaFilterTitle);
          filtersWrapper.appendChild(areaFilter);
        },
      });

      //console.log(regionFilterData.length);

      //<div class="loader" id="loader"></div>

      if (_withSearchBar) {
        document.getElementsByClassName("mapFiltersWrapper")[0].style.display =
          "none";
      }
      document.getElementById("loader").style.display = "none";

      /*
      //ShipAny
      console.log("inside complete after refresh");
      */
    },
  });
}

//sort location type data
function moveItem(from, to, data) {
  // remove `from` item and store it
  var f = data.splice(from, 1)[0];
  // insert stored item into position `to`
  return data.splice(to, 0, f);
}

// returns [1, 3, 2]
// Assign Onclick function to dropdown items
const assignOnClickToDropDownItems = () => {
  let dropdownItem = document.getElementsByClassName("customDropDownItem");
  Object.keys(dropdownItem).forEach((key, index) => {
    dropdownItem[key].addEventListener("click", () => {
      document
        .getElementsByClassName("customDropDownContainer")[0]
        .classList.toggle("customDropDownOpen");
      dropdownItem[clickedIndex].classList.remove("customDropDownItemSelected");
      dropdownItem[index].classList.add("customDropDownItemSelected");
      selectedLocationNameId = dropdownItem[key].getElementsByClassName(
        "customDropDownItemLocationName"
      )[0].innerHTML;
      document.getElementsByClassName(
        "customDropDownSelectText"
      )[0].innerHTML = selectedLocationNameId;
      clickedIndex = index;

      var selocationId = selectedLocationNameId.split(" - ")[0];
      for (let i = 0; i < lockerLocations.length; i++) {
        //console.log(lockerLocations[i].values_.locationId)
        //console.log(lockerLocations[i].values_);
        if (lockerLocations[i].values_.locationId === selocationId) {
          //console.log("match found");
          callBackObject = lockerLocations[i].values_;
          //console.log("callback object", callBackObject);
          showDropDownSelection(callBackObject);
          // _onSelect(callBackObject);
          showPopUp(callBackObject);
          let long = lockerLocations[i].values_.longitude;
          let lat = lockerLocations[i].values_.latitude;

          /*
          //ShipAny
          mapView.values_.center = ol.proj.fromLonLat([long, lat]);
          mapView.setZoom(18);
          if (mapBox !== undefined) mapBox.render();
          */
        } else {
          //console.log("match not found");
        }
      }
    });
  });
  //console.log(selectedLocationNameId, "selectedLocationNameId")
};

var reactiveList = from(lockerLocations);

//function filterListByRegion()

const filterByDistrictParams$ = fromEvent([districtFilter], "change").pipe(
  map(($event) => {
    filteredDistrict = [];
    filteredArea = [];
    let selectElement = $event.target;
    var optionIndex = selectElement.selectedIndex;
    var optionText = selectElement.options[optionIndex];
    //console.log(event)
    return optionText.innerHTML;
  }),
  debounceTime(1000),
  distinctUntilChanged(),
  filter(function (value) {
    return value.length > 1;
  })
  //flatMap(filterListByDistrictParams),
);

const filterByRegionParams$ = fromEvent([regionFilter], "change").pipe(
  map(($event) => {
    let selectElement = $event.target;
    var optionIndex = selectElement.selectedIndex;
    var optionText = selectElement.options[optionIndex];
    //console.log(event);
    return optionText.innerHTML;
  }),
  debounceTime(1000),
  distinctUntilChanged(),
  filter(function (value) {
    return value.length > 1;
  })
  //flatMap(filterListByRegionParams),
);

const filterByAreaParams$ = fromEvent([areaFilter], "change").pipe(
  map(($event) => {
    filteredArea = [];
    let selectElement = $event.target;
    var optionIndex = selectElement.selectedIndex;
    var optionText = selectElement.options[optionIndex];

    return optionText.innerHTML;
  }),
  debounceTime(1000),
  distinctUntilChanged(),
  filter(function (value) {
    return value.length > 1;
  })
  //flatMap(filterListByTypeParams),
);

//combine the above:
//var end$ = new Subject();  ....use this if you want to end the observable
const multiFilter = merge(

  filterByRegionParams$.pipe(
    map((x) => {
      //NEW CODE

      //console.log("hossa 20", x, completeResultArray);
      filteredDistrict = [];
      filteredArea = [];
      clickedIndex = 0;
      //vectorLayer.getSource().clear();
      hidePopUp();
      numberOfFilteredItems = [];
      containerParentDiv.innerHTML = ""; //HACK!!!!!!!!
      if (_mode === "dropdown") {
        document.getElementsByClassName("customDropDownBox")[0].innerHTML = "";
      }

      if (x !== "All Regions" && x !== "全部區域") {
        multiFilterObject["province"] = x;
        delete multiFilterObject["city"];
        delete multiFilterObject["district"];
        filteredRegionName = x;
      } else {
        delete multiFilterObject["province"];
        delete multiFilterObject["city"];
        delete multiFilterObject["district"];
        filteredRegionName = "All Regions";
      }
      return multiFilterObject;
    })
    //takeUntil(end$),
  ),
  filterByDistrictParams$.pipe(
    map((x) => {
      //NEW CODE
      filteredDistrict = [];
      filteredArea = [];
      clickedIndex = 0;
      //vectorLayer.getSource().clear();
      hidePopUp();
      numberOfFilteredItems = [];
      containerParentDiv.innerHTML = "";
      if (_mode === "dropdown") {
        document.getElementsByClassName("customDropDownBox")[0].innerHTML = "";
      }
      if (x !== "All Districts" && x !== "全部地區") {
        multiFilterObject["city"] = x;
        filteredDistrictName = x;
        //delete multiFilterObject["city"];
        delete multiFilterObject["district"];
      } else {
        filteredDistrictName = x;
        delete multiFilterObject["city"];
        delete multiFilterObject["district"];
      }

      return multiFilterObject;
      //return x
    })
    //takeUntil(end$)
  ),

  filterByAreaParams$.pipe(
    map((x) => {
      //NEW CODE
      filteredDistrict = [];
      filteredArea = [];
      clickedIndex = 0;
      //vectorLayer.getSource().clear();
      hidePopUp();
      numberOfFilteredItems = [];
      containerParentDiv.innerHTML = ""; //HACK!!!!!!!!
      if (_mode === "dropdown") {
        document.getElementsByClassName("customDropDownBox")[0].innerHTML = "";
      }

      if (x !== "All Areas" && x !== "全部範圍") {
        multiFilterObject["district"] = x;
      } else {
        delete multiFilterObject["district"];
      }
      return multiFilterObject;
    })
    //takeUntil(end$),
  )
).pipe(flatMap(multiFilterList));

//lodash function (src:https://github.com/lodash/lodash), to check if filter criteria matches location object
function isSubset(obj1, obj2) {
  let matched = true;
  _.forEach(obj1, (value, key) => {
    if (!_.isEqual(value, obj2[key])) {
      matched = false;
      return;
    }
  });
  return matched;
}

function multiFilterList(filterCriteria) {
  return reactiveList.pipe(
    filter((locationObject) => {
      let matchFound = isSubset(filterCriteria, locationObject.values_);
      if (matchFound) {
        //console.log("FemaleWriters", locationObject.values_.district);
        filteredDistrict.push(locationObject.values_.city);
        //filteredArea.push(locationObject.values_.district);

        return locationObject;
      }
    }),
    filter((locationObject) => {
      //let matchFound = isSubset(filterCriteria, locationObject.values_);
      //if (matchFound) {
      //console.log("FemaleWriters", locationObject.values_.district);
      //  filteredDistrict.push(locationObject.values_.city);
      filteredArea.push(locationObject.values_.district);

      return locationObject;
      //}
    }),
    //NEW CODE
    defaultIfEmpty(undefined)
  );
}

multiFilter.subscribe({
  next: (r) => {
    //NEW CODE
    //document.getElementById("loader").style.display = "block";
    //setTimeout(function updateMap(){
    if (r === null) {
      //console.log("inside null");
      let noResultText = document.createElement("p");
      noResultText.setAttribute("style", "text-align: center");
      if (_locale === "en") {
        noResultText.innerHTML =
          "The location you have searched for does not have any Shipany Locations available";
      } else if (_locale === "zh") {
        noResultText.innerHTML = "你搜尋的地方沒有任何智能櫃服務";
      }
      if (_mode === "dropdown") {
        document
          .getElementsByClassName("customDropDownBox")[0]
          .appendChild(noResultText);
      } else {
        containerParentDiv.appendChild(noResultText);
      }
      /*
      //ShipAny
      mapView.values_.center = ol.proj.fromLonLat([114.177216, 22.302711]);
      mapView.setZoom(12);
      mapBox.render();
      */
    } else {
      let result = r.values_;

      updateList(result);
      //ShipAny skip map update
      //updateMap(result);

      /*
      //ShipAny
      mapView.values_.center = ol.proj.fromLonLat([
        r.values_.longitude,
        r.values_.latitude,
      ]);
      //mapView.values_.center = ol.proj.fromLonLat([114.177216,  22.302711])
      mapView.setZoom(12);
      if (mapBox !== undefined) mapBox.render();
      */

      filteredDistrict = Array.from(new Set(filteredDistrict));
      filteredArea = Array.from(new Set(filteredArea));

      let disFilter = document.getElementsByClassName("districtFilter")[0];
      let areFilter = document.getElementsByClassName("areaFilter")[0];

      if (filteredRegionName == "") {
        //console.log("8901", filteredRegionName);

        if (filteredDistrictName === "") {
          //do nothing
        } else {
          removeOptions(areFilter);
          for (let j = 0; j < filteredArea.length; j++) {
            var option = document.createElement("option");
            option.text = filteredArea[j];
            // option.label = translateArea(option.label)
            if (_locale === "zh") option.label = translateArea(option.label);
            areFilter.add(option);
          }
        }
      } else {
        //console.log("8901", multiFilterObject);
        removeOptions(disFilter);
        removeOptions(areFilter);
        for (let j = 0; j < filteredDistrict.length; j++) {
          var option = document.createElement("option");
          option.text = filteredDistrict[j];
          if (_locale === "zh") option.label = translateDistrict(option.label)
          disFilter.add(option);
        }
        //console.log("multifilterObject", multiFilterObject);
        for (let j = 0; j < filteredArea.length; j++) {
          var option = document.createElement("option");
          option.text = filteredArea[j];
          if (_locale === "zh") option.label = translateArea(option.label)
          areFilter.add(option);
        }
      }

      let cityName = multiFilterObject["city"];
      let districtName = multiFilterObject["district"];

      if (cityName === undefined) {
        disFilter.options.selectedIndex = 1;
      } else {
        for (let j = 0; j < disFilter.options.length; j++) {
          if (disFilter.options[j].text === cityName) {
            disFilter.options.selectedIndex = j;
          }
        }
      }

      if (districtName === undefined) {
        areFilter.options.selectedIndex = 1;
      } else {
        for (let j = 0; j < areFilter.options.length; j++) {
          if (areFilter.options[j].text === districtName) {
            areFilter.options.selectedIndex = j;
          }
        }
      }

      //now remove all dups.
    }

    //}, 1000);
  },

  complete: () => { },
});

function removeOptions(selectElement) {
  var i,
    L = selectElement.options.length - 1;
  for (i = L; i >= 2; i--) {
    selectElement.remove(i);
  }
}

function updateMap(result) {
  let lat = result.latitude;
  let long = result.longitude;
  let coordinates = ol.proj.fromLonLat([long, lat]);

  //console.log("features", vectorLayer.getSource().features);
  /*
  vectorLayer.getSource().addFeature(
    new ol.Feature({
      type: "click",
      address1: result.addr.lns_cht[0],
      address1En: result.addr.lns[0],
      address2: result.addr.lns_cht[1],
      address2En: result.addr.lns[1],
      city: result.addr.state,
      district: result.addr.distr,
      province: result.addr.reg,
      latitude: lat,
      longitude: long,
      locationEn: result.name,
      locationId: result.code,
      locationName: result.name_cht,
      locationType: result.locationType,
      operatingTime: result.open_hours,
      operatingTimeCN: result.open_hours_cht,
      geometry: new ol.geom.Point(coordinates),
    })
  );
  */
  //console.log(lockerLocations.length)
}

//update LIST also hack, fix
function updateList(result) {
  //console.log("update list result", result);
  numberOfFilteredItems.push(result);
  let lat = result.latitude;
  let long = result.longitude;

  /****START**/
  // containerParentDiv
  //inner HTML elements
  var containerTextDiv = document.createElement("div");
  containerTextDiv.className = "containerChildTextDiv";

  var locationContainer = document.createElement("INPUT");
  Object.assign(locationContainer, {
    type: "radio",
    name: "list",
    id: result.locationId,
    className: "mapRadioBtn",
  });

  var locationContentDiv = document.createElement("label");
  Object.assign(locationContentDiv, {
    htmlFor: result.locationId,
    className: "shipany-label",
  });

  var locationContentText = document.createElement("div");

  // var locationName = document.createElement("P");                 // Create a <p> element
  // locationName.innerHTML = result.locationName;                // Insert text
  // locationContainer.appendChild(locationName);
  if (result.locationName || result.locationId) {
    var locationName = document.createElement("P"); // Create a <p> element
    locationName.setAttribute("class", "locationName");
    if (_locale === "en" || 1) {
      // Reselect Filter eg district area
      if (result.locationType === "Convenience Store" || result.typ === "Petrol Station") {
        locationName.innerHTML =
          result.locationId + " - " + (result?.locationName ?? "").replace(result.locationId, '') + result.address1; // Insert text //replace handled zto prefix id
      } else {
        locationName.innerHTML =
          result.locationId + " - " + (result?.locationName ?? "").replace(result.locationId, ''); // Insert text        
      }
    } else if (_locale === "zh") {
      if (result.locationType === "Convenience Store" || result.locationType === "Store") {
        locationName.innerHTML =
          result.locationId + " (- " + (result?.locationName ?? "").replace(result.locationId, '') + result.address1; // Insert text
      } else {
        locationName.innerHTML =
          result.locationId + " (- " + (result?.locationName ?? "").replace(result.locationId, ''); // Insert text        
      }
    } // Insert text
    locationContentText.appendChild(locationName);
  }

  // var operatingTime = document.createElement("P");                 // Create a <p> element
  // operatingTime.innerHTML = result.operatingTime;
  // locationContainer.appendChild(operatingTime);
  if (result.operatingTime) {
    var operatingTime = document.createElement("P");
    operatingTime.setAttribute("class", "operatingTime");
    //operatingTime.innerHTML = result.operatingTime;

    if (_locale === "en") {
      operatingTime.innerHTML = result?.operatingTime ?? "";
    } else if (_locale === "zh") {
      operatingTime.innerHTML = result?.operatingTimeCN ?? "";
    }
    locationContentText.appendChild(operatingTime);
  }

  var address = document.createElement("P");
  //console.log(result);
  if (_locale === "en") {
    address.innerHTML = result?.address1En ?? "";
  } else if (_locale === "zh") {
    address.innerHTML = result?.address1 ?? "";
  }
  address.setAttribute("class", "contentAddress");
  locationContentText.appendChild(address);

  // var city = document.createElement("P");                 // Create a <p> element
  // city.innerHTML = result.city;
  // locationContainer.appendChild(city);

  var city = document.createElement("P");
  if (result.locationType) {
    if (_locale === "zh") {
      Object.assign(city, {
        innerHTML:
          translateType(capitaliseString(result.locationType)) + " | " + translateDistrict((result?.city ?? "")),
        className: "contentCity",
      });
    } else {
      Object.assign(city, {
        innerHTML:
          capitaliseString(result.locationType) + " | " + (result?.city ?? ""),
        className: "contentCity",
      });
    }

  } else {
    Object.assign(city, {
      innerHTML: result.city,
      className: "contentCity",
    });
  }

  locationContentText.appendChild(city);

  var locationType = document.createElement("P");
  if (result.locationType) {
    if (_locale === 'zh') {
      Object.assign(locationType, {
        innerHTML: translateType(capitaliseString(result.locationType)),
        className: "contentLocationType",
      });
    } else {
      Object.assign(locationType, {
        innerHTML: capitaliseString(result.locationType),
        className: "contentLocationType",
      });
    }
  }
  locationContentText.appendChild(locationType);

  var howToGetThere = document.createElement("a");

  //How to get there popup
  if (_locale === "en") {
    Object.assign(howToGetThere, {
      href: "#",
      className: "howToGetThere",
      style: "display: none",

      innerHTML: "How to get there",
    });
  } else if (_locale === "zh") {
    Object.assign(howToGetThere, {
      href: "#",
      className: "howToGetThere",
      style: "display: none",

      innerHTML: "如何找到？",
    });
  }
  // var browseIcon = document.createElement("img");
  // browseIcon.style.width = "18px";
  // browseIcon.setAttribute(
  //   "src",
  //   word_press_path + "pages/easywidgetSDK/images/browse-icon-transparent.png"
  // );
  // howToGetThere.appendChild(browseIcon);
  locationContentText.appendChild(howToGetThere);
  //locationContentText.appendChild(howToGetThere);

  locationContentDiv.appendChild(locationContentText);

  // locationContainer.appendChild(document.createElement("hr"))
  // containerTextDiv.appendChild(locationContainer)
  // containerParentDiv.appendChild(containerTextDiv)
  // containerDiv.appendChild(containerParentDiv)
  containerTextDiv.appendChild(locationContainer);
  containerTextDiv.appendChild(locationContentDiv);
  containerParentDiv.appendChild(containerTextDiv);
  if (_mode !== "dropdown") {
    if (counter === 1) {
      leftWrapper.appendChild(containerParentDiv);
      const loader = document.createElement("div");
      loader.className = "loader";
      loader.id = "filterLoader";
      loader.style.display = "none";
      leftWrapper.appendChild(loader);
      containerDiv.appendChild(leftWrapper);
    }
  }
  containerDiv.appendChild(containerMapBox);

  //add to dropdownlist
  if (_mode === "dropdown") {
    var sb = new StringBuilder();
    sb.append(result.locationId || "empty");
    sb.append(" - ");
    //console.log(result.locationEn);
    if (_locale === "en") {
      sb.append(result.locationEn);
    } else if (_locale === "zh") {
      sb.append(result.locationName);
    }
    var myString = sb.toString();

    let customDropDownItem = document.createElement("div");
    customDropDownItem.className = "customDropDownItem";
    customDropDownItem.id = result.locationId;
    let customDropDownItemLocationName = document.createElement("p");
    let customDropDownItemLocationTypeCity = document.createElement("p");
    let customDropDownItemCoords = document.createElement("p");

    Object.assign(customDropDownItemLocationName, {
      className: "customDropDownItemLocationName",
      innerHTML: myString,
    });

    //console.log(customDropDownItemLocationName);

    Object.assign(customDropDownItemLocationTypeCity, {
      className: "customDropDownItemLocationTypeCity",
      innerHTML: capitaliseString(result.locationType) + "  " + result.city,
    });

    Object.assign(customDropDownItemCoords, {
      hidden: true,
      className: "customDropDownItemCoords",
      innerHTML: long + "-" + lat,
    });

    customDropDownItem.appendChild(customDropDownItemLocationName);
    customDropDownItem.appendChild(customDropDownItemLocationTypeCity);
    customDropDownItem.appendChild(customDropDownItemCoords);

    customDropDownItem.addEventListener("click", () => {
      let dropdownItem = document.getElementsByClassName("customDropDownItem");
      dropdownItem[clickedIndex].classList.remove("customDropDownItemSelected");
      customDropDownItem.classList.add("customDropDownItemSelected");
      selectedLocationNameId = customDropDownItemLocationName.innerHTML;
      callBackObject = result;

      showDropDownSelection(result);
      // _onSelect(callBackObject);
      showPopUp(callBackObject);
      document.getElementsByClassName(
        "customDropDownSelectText"
      )[0].innerHTML = selectedLocationNameId;
      let selectedItemIndex = numberOfFilteredItems.findIndex(
        (item) => item.locationId === result.locationId
      );
      clickedIndex = selectedItemIndex;

      var selocationId = selectedLocationNameId.split(" - ")[0];
      for (let i = 0; i < lockerLocations.length; i++) {
        //console.log(lockerLocations[i].values_.locationId)
        //console.log(lockerLocations[i].values_.locationId);
        if (lockerLocations[i].values_.locationId === selocationId) {
          //console.log("match found");
          let long = lockerLocations[i].values_.longitude;
          let lat = lockerLocations[i].values_.latitude;

          mapView.values_.center = ol.proj.fromLonLat([long, lat]);
          mapView.setZoom(18);
          if (mapBox !== undefined) mapBox.render();
        } else {
          //console.log("match not found uuuu");
        }
      }
    });

    document
      .getElementsByClassName("customDropDownBox")[0]
      .appendChild(customDropDownItem);
    // dropDownElementList.push(customDropDownItem)
    // assignOnClickToDropDownItems()
  }
  jQuery(".shipany-woo-plugin-modal .mapLeftWrapper #filterLoader").hide();
  jQuery(".shipany-woo-plugin-modal .mapLeftWrapper div.containerParentTextDiv").show();
}

function showDropDownSelection(result) {
  dropDownSelectedItemContainer.innerHTML = "";

  let containerTextDiv = document.createElement("div");
  containerTextDiv.className = "containerChildTextDiv";

  // var locationContainer = document.createElement("div");
  // locationContainer.className = result.locationId
  let locationContainer = document.createElement("INPUT");
  Object.assign(locationContainer, {
    type: "radio",
    name: "list",
    id: result.code,
    className: "mapRadioBtn",
  });

  let locationContentDiv = document.createElement("label");
  Object.assign(locationContentDiv, {
    htmlFor: result.code,
    className: "shipany-label",
  });

  let locationContentText = document.createElement("div");

  if (result.name_cht || result.code) {
    let locationName = document.createElement("P"); // Create a <p> element
    locationName.setAttribute("class", "locationName");
    if (_locale === "en") {
      if (result.typ === "Convenience Store" || result.typ === "Store") {
        locationName.innerHTML =
          result.code + " - " + (result?.name ?? "") + result.addr.lns; // Insert text
      } else {
        locationName.innerHTML =
          result.code + " - " + (result?.name ?? ""); // Insert text          
      }
    } else if (_locale === "zh") {
      if (result.typ === "Convenience Store" || result.typ === "Store") {
        locationName.innerHTML =
          result.code + " - " + (result?.name_cht ?? "") + result.addr.lns_cht; // Insert text
      } else {
        locationName.innerHTML =
          result.code + " - " + (result?.name_cht ?? ""); // Insert text    
      }
    }
    locationContentText.appendChild(locationName);
  }

  /*if (result.operatingTime) {
                var operatingTime = document.createElement("P");
                operatingTime.setAttribute("class", "operatingTime")
                operatingTime.innerHTML = result.operatingTime; 
                locationContentText.appendChild(operatingTime);
            }*/

  if (result.open_hours) {
    let operatingTime = document.createElement("P");
    operatingTime.setAttribute("class", "operatingTime display-block");

    if (_locale === "en") {
      operatingTime.innerHTML = result.open_hours;
    } else if (_locale === "zh") {
      operatingTime.innerHTML = result.open_hours_cht;
    }

    locationContentText.appendChild(operatingTime);
  }

  let address = document.createElement("P");
  if (_locale === "en") {
    address.innerHTML = `[${result.code}] ${result.addr.lns[0]}`;
  } else if (_locale === "zh") {
    address.innerHTML = `[${result.code}] ${result.addr.lns_cht[0]}`;
  }
  address.setAttribute("class", "contentAddress display-block");
  locationContentText.appendChild(address);

  /*let locationType = document.createElement("P");
    if (result.locationType) {
      Object.assign(locationType, {
        innerHTML: capitaliseString(result.locationType),
        className: "contentLocationType display-block",
      });
    }
    locationContentText.appendChild(locationType);*/

  let city = document.createElement("P");
  if (result.typ) {
    Object.assign(city, {
      innerHTML:
        capitaliseString(result.typ) + " | " + (result?.addr.state ?? ""),
      className: "contentCityDropdown",
    });
  } else {
    Object.assign(city, {
      innerHTML: result?.addr.state ?? "",
      className: "contentCityDropdown",
    });
  }
  locationContentText.appendChild(city);

  let howToGetThere = document.createElement("a");
  if (_locale === "en") {
    Object.assign(howToGetThere, {
      href: "#",
      className: "howToGetThere display-block",
      id: "howToGetThere",
      style: "display: none",

      innerHTML: "How to get there",
    });
  } else if (_locale === "zh") {
    Object.assign(howToGetThere, {
      href: "#",
      className: "howToGetThere",
      id: "howToGetThere",
      style: "display: none",
      innerHTML: "如何找到智能櫃？",
    });
  }

  //document.getElementById("howToGetThere").innerHTML.style.display = "none";
  locationContentText.appendChild(howToGetThere);
  //console.log( document.getElementById('howToGetThere'))//setAttribute("hidden",true)

  locationContentDiv.appendChild(locationContentText);

  //console.log("inside llll");
  dropDownSelectedItemContainer.appendChild(locationContentDiv);
}

// search bar filtering
function filterListByCity(filterVal) {
  return reactiveList.pipe(
    filter((x) => {
      //let cityLowerCase = x.values_.city.trim().toLowerCase();
      let filterValLowerCase = filterVal.trim().toLowerCase();

      let regionFilterValue = x.values_.province.trim().toLowerCase();
      let districtFilterValue = x.values_.city.trim().toLowerCase();
      let areaFilterValue = x.values_.district.trim().toLowerCase();
      let locationNameFilterVal = x.values_.locationName.trim().toLowerCase();
      let locationIdFilterVal = x.values_.locationId.trim().toLowerCase();
      let addressFilterVal = x.values_.address1En.trim().toLowerCase();
      //region, district, area, location name, location ID and address

      /*if (regionFilterValue.indexOf(filterVal) !== -1) { 
                return x;
            }*/
      if (
        stringSimilarity.compareTwoStrings(
          filterValLowerCase,
          regionFilterValue
        ) >= 0.6
      ) {
        //console.log(x.values_);
        return x;
      } else if (
        stringSimilarity.compareTwoStrings(
          filterValLowerCase,
          districtFilterValue
        ) >= 0.6
      ) {
        return x;
      } else if (
        stringSimilarity.compareTwoStrings(
          filterValLowerCase,
          areaFilterValue
        ) >= 0.6
      ) {
        return x;
      } else if (
        stringSimilarity.compareTwoStrings(
          filterValLowerCase,
          locationNameFilterVal
        ) >= 0.6
      ) {
        return x;
      } else if (
        stringSimilarity.compareTwoStrings(
          filterValLowerCase,
          locationIdFilterVal
        ) >= 0.6
      ) {
        return x;
      } else if (
        stringSimilarity.compareTwoStrings(
          filterValLowerCase,
          addressFilterVal
        ) >= 0.6
      ) {
        return x;
      }
    }),
    takeLast(1), //return only last value and set center to its coordinates
    defaultIfEmpty(undefined)
  );
}

if (_withSearchBar) {
  //filter data from search bar
  const filterData$ = fromEvent(searchBar, "input").pipe(
    map((e) => {
      return e.target.value;
    }),
    debounceTime(1000),
    distinctUntilChanged(),
    filter(function (value) {
      return value.length > 2;
    }),
    flatMap(filterListByCity)
  );

  filterData$.subscribe({
    next: (result) => {
      //    console.log(result);
      if (result === null) {
        searchBar.value = "";
        alert("No match found");
      } else {
        mapView.values_.center = ol.proj.fromLonLat([
          result.values_.longitude,
          result.values_.latitude,
        ]);
        mapView.setZoom(14);
        mapBox.render();
        let radioBtnList = document.getElementsByClassName("mapRadioBtn");
        // console.log(radioBtnList.length)
        Object.keys(radioBtnList).forEach((key, index) => {
          if (radioBtnList[key].id === result.values_.locationId) {
            setFocus(index);
          }
        });
      }
    },
    complete: () => {
      return console.log("done filter data");
    },
  });
}

function setFocus(num) {
  const textDiv = document.getElementsByClassName("containerChildTextDiv");
  Object.keys(textDiv).forEach((key, index) => {
    textDiv[key].classList.remove("selected-background");
    if (textDiv[key].getElementsByClassName("contentCity")[0] !== undefined) {
      textDiv[key]
        .getElementsByClassName("contentCity")[0]
        .classList.remove("display-none");
    }

    if (
      textDiv[key].getElementsByClassName("contentAddress")[0] !== undefined
    ) {
      textDiv[key]
        .getElementsByClassName("contentAddress")[0]
        .classList.remove("display-block");
    }
    if (textDiv[key].getElementsByClassName("operatingTime")[0] !== undefined) {
      textDiv[key]
        .getElementsByClassName("operatingTime")[0]
        .classList.remove("display-block");
    }
    if (textDiv[key].getElementsByClassName("howToGetThere")[0] !== undefined) {
      textDiv[key]
        .getElementsByClassName("howToGetThere")[0]
        .classList.remove("display-flex");
    }
  });
  // document.getElementsByTagName("input")[num].checked = true;
  // document.getElementsByTagName("input")[num].parentNode.classList.add("selected-background");
  textDiv[num].getElementsByClassName("mapRadioBtn")[0].checked = true;
  textDiv[num].classList.add("selected-background");
  textDiv[num]
    .getElementsByClassName("contentAddress")[0]
    .classList.add("display-block");
  if (textDiv[num].getElementsByClassName("operatingTime")[0]) {
    textDiv[num]
      .getElementsByClassName("operatingTime")[0]
      .classList.add("display-block");
  }
  textDiv[num]
    .getElementsByClassName("howToGetThere")[0]
    .classList.add("display-flex");
  textDiv[num]
    .getElementsByClassName("contentCity")[0]
    .classList.add("display-none");
  //textDiv[num].getElementsByClassName("contentLocationType")[0].classList.add("display-none");
  textDiv[num].getElementsByClassName("mapRadioBtn")[0].focus();
}

const onListClick$ = fromEvent(containerParentDiv, "click").pipe(
  map((event) => {
    hidePopUp();
    const textDiv = document.getElementsByClassName("containerChildTextDiv");
    const currentSelectedElements = document.getElementsByClassName('selected-background')
    for (let i = 0; i < currentSelectedElements.length; i++) {
      if (!currentSelectedElements[i]) continue;

      if (currentSelectedElements[i].getElementsByClassName("contentCity")[0] !== undefined) {
        currentSelectedElements[i]
          .getElementsByClassName("contentCity")[0]
          .classList.remove("display-none");
      }
      if (
        currentSelectedElements[i].getElementsByClassName("contentAddress")[0] !== undefined
      ) {
        currentSelectedElements[i]
          .getElementsByClassName("contentAddress")[0]
          .classList.remove("display-block");
      }
      if (
        currentSelectedElements[i].getElementsByClassName("contentLocationType")[0] !==
        undefined
      ) {
        currentSelectedElements[i]
          .getElementsByClassName("contentLocationType")[0]
          .classList.remove("display-block");
      }
      if (
        currentSelectedElements[i].getElementsByClassName("operatingTime")[0] !== undefined
      ) {
        currentSelectedElements[i]
          .getElementsByClassName("operatingTime")[0]
          .classList.remove("display-block");
      }
      if (
        currentSelectedElements[i].getElementsByClassName("howToGetThere")[0] !== undefined
      ) {
        currentSelectedElements[i]
          .getElementsByClassName("howToGetThere")[0]
          .classList.remove("display-flex");
      }

      currentSelectedElements[i].classList.remove('selected-background')
    }

    if (event.srcElement.classList.contains('containerChildTextDiv')) {
      event.srcElement.getElementsByClassName("mapRadioBtn")[0].click();
      return;
    }
    if (
      event.srcElement.parentNode.closest(".containerChildTextDiv") !== null
    ) {
      let selectedLocationId = event.srcElement.parentNode
        .getElementsByClassName("locationName")[0]
        .innerHTML.split(" - ")[0];
      let selectedItemIndex = numberOfFilteredItems.findIndex(
        (item) => item.locationId === selectedLocationId
      );
      callBackObject = numberOfFilteredItems[selectedItemIndex];
      showPopUp(callBackObject);
      // _onSelect(callBackObject);

      event.srcElement.parentNode
        .closest(".containerChildTextDiv")
        .classList.add("selected-background");
      event.srcElement.parentNode
        .getElementsByClassName("contentAddress")[0]
        .classList.add("display-block");
      if (
        event.srcElement.parentNode.getElementsByClassName(
          "operatingTime"
        )[0] !== undefined
      ) {
        event.srcElement.parentNode
          .getElementsByClassName("operatingTime")[0]
          .classList.add("display-block");
      }
      event.srcElement.parentNode
        .getElementsByClassName("howToGetThere")[0]
        .classList.add("display-flex");
      event.srcElement.parentNode
        .getElementsByClassName("contentLocationType")[0]
        .classList.add("display-block");

      //event.srcElement.parentNode.getElementsByClassName("contentCity")[0].classList.add("selected-background");
      event.srcElement.parentNode
        .getElementsByClassName("contentCity")[0]
        .classList.add("display-none");
    }
    return null;
  })
);

onListClick$.subscribe({
  next: (result) => {
    if (result === undefined || result === null) return false;
    //format => [long, lat]
    let coordArray = result.split("-");
    let long = coordArray[0];
    let lat = coordArray[1];

    /*
    //long lat are string so convert to float first
    mapView.values_.center = ol.proj.fromLonLat([Number(long), Number(lat)]);
    //console.log("selection",Number(long))
    let features = vectorLayer.getSource().getFeatures();
    features.forEach(function (feature, index) {
      var coord = feature.getGeometry().getCoordinates();
      if (JSON.stringify(coord) === JSON.stringify(mapView.values_.center)) {
        feature.setStyle(styleRed);
      } else {
        feature.setStyle(styleOriginal);
      }
    });
    */

    /*
    //ShipAny
    var center = mapView.getCenter()
    var resolution = mapView.getResolution()
    mapView.setCenter([center[0] + 100 * resolution, center[1] + 100 * resolution])
    mapView.setZoom(18);
    mapBox.render();
    */
  },
});

const hidePopUp = () => {
  popup.hide();
  popup.setOffset([0, 0]);
};

const showPopUp = (result) => {
  let coordinates = ol.proj.fromLonLat([result.longitude, result.latitude]);
  let location;
  let howToGetThere;
  let address;

  var operatingTime;

  if (_locale === "en") {
    location = result?.locationEn ?? "";
    howToGetThere = "How to get there";
    address = result?.address1En ?? "";
    operatingTime = result?.operatingTime ?? "";
  } else if (_locale === "zh") {
    location = result?.locationName ?? "";
    howToGetThere = "如何找到？";
    address = result?.address1 ?? "";
    operatingTime = result?.operatingTimeCN ?? "";
  }

  var info =
    '<div style="width:220px; margin-top:3px">' +
    (result?.locationId ?? "") +
    "</div>" +
    '<div style="width:220px; margin-top:3px">' +
    capitaliseString(result?.locationType ?? "") +
    " | " +
    (result?.district ?? "") +
    "</div>" +
    '<div style="width:220px; margin-top:3px">' +
    location +
    "</div>" +
    '<div style="width:220px; margin-top:3px; color: grey">' +
    address +
    "</div>" +
    '<div style="width:220px; margin-top:3px; color: grey">' +
    operatingTime +
    "</div>" +
    '<div style="width:220px; margin-top:3px; color: grey">' +
    (result?.city ?? "") +
    "</div>"; //+


  // Offset the popup so it points at the middle of the marker not the tip
  popup.setOffset([0, -32]);
  popup.show(coordinates, info);
};
