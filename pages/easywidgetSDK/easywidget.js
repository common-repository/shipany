//init variables
let _mapBoxClassName,
  _defaultLocation,
  _mapType,
  _locale,
  _mode,
  _filterCriteria,
  _key,
  _onSelect,
  _userAuthObject,
  _withSearchBar;

//let completeResultArray = [];
let word_press_path = scriptParams.path;
let courier_id = scriptParams.courier_id;
let region_idx = scriptParams.region_idx;
let env = scriptParams.env;
let ver = scriptParams.ver;

let lang = (document.documentElement.lang.includes('zh')?'zh':'en') ?? (scriptParams.lang.includes('zh')?'zh':'en');


//initialise widget
var easyWidget =
  easyWidget ||
  (function () {
    return {
      init: async function (obj) {
        lang = (document.documentElement.lang.includes('zh')?'zh':'en') ?? (scriptParams.lang.includes('zh')?'zh':'en');
        let {
          mapType,
          locale,
          defaultLocation,
          mapBoxClassName,
          mode,
          filter,
          apiKey,
          onSelect,
          userAuthObject,
          searchBar,
        } = obj;
        //set variable names
        _mapBoxClassName = mapBoxClassName || "mapBox";
        _defaultLocation = defaultLocation || "HK";
        _mapType = mapType || "osm";
        _locale = lang || "en";
        _mode = mode || "basic";
        _filterCriteria = filter || null;
        _key = apiKey || "";
        _onSelect = onSelect;
        // _onSelectionCloseModal = onSelectionCloseModal
        _userAuthObject = userAuthObject || null;
        _withSearchBar = searchBar || false;
        // load courier config
        // const data = await jQuery.getJSON('https://apps.shipany.io/woocommerce/locationList.json')
        // window.couriers = data.couriers
        onLoadComplete();
        //console.log("hello inside here obj is ", obj);
      },
      changeLanguage: function (lang) {
        localStorage.setItem("language", lang);
        let script = document.getElementsByTagName("script");
        console.log(script, typeof script);
      },
      reset: function (obj) {
        let { filter } = obj;
        //set variable names
        console.log("inside filter", filter);
        _filterCriteria = filter || null;

        //onLoadComplete();
      },
    };
  })();

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
//loadscripts
async function saLoadScripts(scriptURL, obj) {
  await new Promise(function (resolve, reject) {
    var link = document.createElement("script");

    link.src = scriptURL;
    link.id = scriptURL;
    Object.assign(link, obj);
    document.body.appendChild(link);

    link.onload = function () {
      resolve();
    };
  });
}

async function LoadCSS(cssURL) {
  // 'cssURL' is the stylesheet's URL, i.e. /css/styles.css

  await new Promise(function (resolve, reject) {
    var link = document.createElement("link");

    link.rel = "stylesheet";

    link.href = cssURL;

    document.head.appendChild(link);

    link.onload = function () {
      resolve();
    };
  });
}

function removejscssfile(filename, filetype) {
  var targetelement =
    filetype == "js" ? "script" : filetype == "css" ? "link" : "none"; //determine element type to create nodelist from
  var targetattr =
    filetype == "js" ? "src" : filetype == "css" ? "href" : "none"; //determine corresponding attribute to test for
  var allsuspects = document.getElementsByTagName(targetelement);
  for (var i = allsuspects.length; i >= 0; i--) {
    //search backwards within nodelist for matching elements to remove
    if (
      allsuspects[i] &&
      allsuspects[i].getAttribute(targetattr) != null &&
      allsuspects[i].getAttribute(targetattr).indexOf(filename) != -1
    )
      allsuspects[i].parentNode.removeChild(allsuspects[i]); //remove element by calling parentNode.removeChild()
  }
}

async function onLoadComplete() {
  //add search bar
  let mapBarWrapper = document.createElement("div");
  mapBarWrapper.className = "mapBarWrapper";
  mapBarWrapper.id = "mapBarWrapper";
  document.body.appendChild(mapBarWrapper);
  const oldPointerEvents = document.body.style.pointerEvents;
  document.body.style.pointerEvents = "none";

  Promise.all([
    saLoadScripts("//unpkg.com/string-similarity/umd/string-similarity.min.js"),
    saLoadScripts("https://cdnjs.cloudflare.com/ajax/libs/rxjs/6.6.7/rxjs.umd.min.js"),
    saLoadScripts("https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.15/lodash.core.min.js"),
    saLoadScripts("https://api.mapbox.com/mapbox-gl-js/v1.10.1/mapbox-gl.js"),
    LoadCSS("https://api.mapbox.com/mapbox-gl-js/v1.10.1/mapbox-gl.css"),
    saLoadScripts(word_press_path + "pages/easywidgetSDK/lib/ol_v5.2.0.js?" + ver),
    saLoadScripts(word_press_path + "pages/easywidgetSDK/lib/stringBuilder.js?" + ver),
    saLoadScripts(word_press_path + "pages/easywidgetSDK/lib/createHTMLElement.js?" + ver),
    saLoadScripts(word_press_path + "pages/easywidgetSDK/service/paths.js?" + ver),
    LoadCSS(word_press_path + "pages/easywidgetSDK/styles/styles.css?" + ver),
  ]).then(async (values) => {
    //_mapType = "osm";
    Promise.all([
      saLoadScripts(word_press_path + "pages/easywidgetSDK/lib/olms.js?" + ver),
      saLoadScripts(word_press_path + "pages/easywidgetSDK/lib/ol-popup.js?" + ver),
      saLoadScripts(word_press_path + "pages/easywidgetSDK/service/apiservice.js?" + ver),
    ]).then(async (values) => {
      switch (_mapType) {
        case "osm":
          await saLoadScripts(word_press_path + "pages/easywidgetSDK/components/osm-map-merge.js?" + ver);
          break;
        case "gmap":
          console.error("Droped")
      }
    }).finally(() => {
      document.body.style.pointerEvents = oldPointerEvents;
    });
  }).catch(() => {
    document.body.style.pointerEvents = oldPointerEvents;
  });
}

