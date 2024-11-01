function shipanyblocks() {
  if (typeof pickupRadioButton == 'undefined') {
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

    var att = document.createAttribute('onclick')
    att.value = "trigger_list()"
    pickupRadioButton = document.querySelector('[id^="shipping_method_0_local_pickup"]')
    if (!pickupRadioButton) {
      pickupRadioButton = document.querySelector('[id*="local_pickup"]')
    }
    if (!pickupRadioButton) {
      pickupRadioButton = document.getElementsByClassName('wc-block-components-shipping-rates-control')[0]
    }
    if(pickupRadioButton){
        pickupRadioButton.setAttributeNode(att)
    }

    var Tags = document.getElementsByTagName("span");
    var searchText = "Local pickup";
    var found;
    
    for (var i = 0; i < Tags.length; i++) {
      if (Tags[i].textContent == searchText) {
        found = Tags[i];
        break;
      }
    }

    if (found != undefined) {
      found.style.color = 'blue'
      found.style.textDecoration = 'underline'
    }
  }
}

function trigger_list() {
  jQuery('input[name="shipany_locker_collect"]').click();
}
window.addEventListener('mouseover', shipanyblocks)