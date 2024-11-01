/**
 * Ajax call to sync orders!
 */
function shipanyUpdateCourier(btn_id) {
  // document.getElementById(btn_id).style.background='red';
  $api_key = document.getElementById("woocommerce_shipany_ecs_asia_shipany_api_key").value;
  var data = {
    'action': 'update_courier',
    'val': $api_key
  };
  jQuery.post('//localhost/appcider/wp-admin/admin-ajax.php', data, function(response) {
  });
}
