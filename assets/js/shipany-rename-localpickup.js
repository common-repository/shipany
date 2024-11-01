function getElementRetry() {
  var target = document.querySelector('#onChangeLocation');
  if(!target) {
      window.setTimeout(getElementRetry,500);
      return;
  }
    if (window?.shipany_setting?.shipany_enable_locker_list2_1) {
      if (document.getElementById("shipping_method").getElementsByTagName("li").length != 1) {
        target.innerHTML = shipany_setting.shipany_enable_locker_list2_1
      }
    }
}
getElementRetry()