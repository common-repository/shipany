var { fromEvent, EMPTY, forkJoin } = rxjs;
var { fromFetch } = rxjs.fetch;
var { ajax } = rxjs.ajax;
var {
  catchError,
  debounceTime,
  distinctUntilChanged,
  filter,
  map,
  mergeMap,
  switchMap,
  tap,
  delay,
} = rxjs.operators;

var completeResultArray = [];
function getResponse(url, authUserObject) {
  // if locker in window
  let locationData$;
  if(typeof window.locker !== 'undefined'){
    locationData$ = new Promise((resolve, reject) => {
      resolve(window.locker);
    });
  } else {
    locationData$ = fromFetch(locationListEndpoint, {
      method: "get",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wp_rest_nonce,
        //Authorization: "Bearer " + localStorage.getItem("token"),
      },
    }).pipe(
      switchMap((response) => {
        //console.log("restoken value is "+localStorage.getItem('token'))
        if (response.ok) {
          return response.json();
        } else {
          // Server is returning a status requiring the client to try something else.
          return of({ error: true, message: `Error ${response.status}` });
        }
      })
    );
  }

  const data$ = fromFetch(sysparam, {
    method: "get",
    //body: JSON.stringify(authUserObject),
    headers: {
      "Content-Type": "application/json",
    },
  }).pipe(
    switchMap((response) => {
      if (response.ok) {
        // OK return data

        return response.json();
      } else {
        // Server is returning a status requiring the client to try something else.
        return of({ error: true, message: `Error ${response.status}` });
      }
    }),
    /*
    map((response) => {
      localStorage.setItem("token", response.token);
      //return response.token
    }),
    */
    switchMap(() => {
      return locationData$;
    }),
    mergeMap((locationObject) => locationObject),
    filter((locationObject) => {
      if (shipany_setting.shipany_locker_include_macuo != null && shipany_setting.shipany_locker_include_macuo == 'no') {
        if (locationObject.addr.cnty === 'MAC') {
          return ''
        }
      }
      if (_locale === "zh") {
        locationObject.addr.reg = translateRegion(locationObject.addr.reg)
        locationObject.addr.state = translateDistrict(locationObject.addr.state)
        locationObject.addr.distr = translateArea(locationObject.addr.distr)
      }

      //NEW CODE
      if (_locale === "en") {
        //districtfilterdata
        districtFilterData.push("All Districts");
      } else if (_locale === "zh") {
        districtFilterData.push("全部地區");
      }

      //NEW CODE
      if (_locale === "en") {
        districtFilterData.push("All Districts");
      } else if (_locale === "zh") {
        districtFilterData.push("全部地區");
      }
      if(region_idx == '2'){
        districtFilterData.push(locationObject.addr.distr);
      }else{
        districtFilterData.push(locationObject.addr.state);
      }

      //NEW CODE
      if (_locale === "en") {
        regionFilterData.push("All Regions");
      } else if (_locale === "zh") {
        regionFilterData.push("全部區域");
      }
      if(region_idx == '2'){
        regionFilterData.push(locationObject.addr.city);
      }else{
        regionFilterData.push(locationObject.addr.reg);
      }

      if (_locale === "en") {
        areaFilterData.push("All Areas");
      } else if (_locale === "zh") {
        areaFilterData.push("全部範圍");
      }
      if(region_idx == '2'){
        // pass
      } else {
        areaFilterData.push(locationObject.addr.distr);
      }
      //NEW CODE

      return locationObject;
    }),
    filter((locationObject) => {
      completeResultArray = Array.from(new Set(completeResultArray));
      completeResultArray.push(locationObject);
      return locationObject;
    }),
    catchError((err) => {
      // Network or other error, handle appropriately
      //console.error(err);
      return of({ error: true, message: err.message });
    })
  );

  return data$;
}

function capitaliseString(str) {
  var res = str.split("_");
  var first = res[0][0].toUpperCase() + res[0].slice(1).toLowerCase();
  var last = res[1][0].toUpperCase() + res[1].slice(1).toLowerCase();

  return first + " " + last;
}
