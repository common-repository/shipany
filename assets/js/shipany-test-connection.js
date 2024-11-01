/**
 * Ajax call to sync orders!
 */
function shipanyTestConnection(btn_id) {
  // document.getElementById(btn_id).style.background='red';
  document.getElementById(btn_id).innerHTML='Loading...';
  document.getElementById(btn_id).style.cursor = 'default';
  document.getElementById(btn_id).style.pointerEvents = 'none';
  // $( '#shipany-label-print').css('opacity', '0.5');
  var $ = jQuery;
  var btn = $(btn_id);
  $api_key = document.getElementById("woocommerce_shipany_ecs_asia_shipany_api_key").value;
  // Remove elements after button
  btn.nextAll().remove();

  btn.attr('disabled', true);
  btn.text('Testing Connection...');

  var loaderContainer = $('<span/>', {
    'class': 'loader-image-container'
  }).insertAfter(btn);

  var shipany_region = $("#woocommerce_shipany_ecs_asia_shipany_region").find(":selected").val()
  var data = {
    'action': 'test_shipany_connection',
    'test_con_nonce': shipany_test_con_obj.test_con_nonce,
    'val': $api_key,
    'region': shipany_region
  };
  
  // We can also pass the url value separately from ajaxurl for front end AJAX implementations
  $.post(shipany_test_con_obj.ajax_url, data, function(response) {
    btn.attr('disabled', false);
    btn.text(response.button_txt);
    loaderContainer.remove();

    if (response.connection_success == 200) {
      alert('Connection success!');
    } else {
      alert('Connection failure.')
    }

    var success = response.connection_success;
    var test_connection_class = 'shipany_connection_' + (success ? 'succeeded' : 'error');
    var test_connection_text = success ? response.connection_success : response.connection_error;

    loaderContainer = $('<span/>', {
      'class': test_connection_class
    }).insertAfter(btn);

    loaderContainer.append(test_connection_text);
    // document.getElementById(btn_id).style.background='yellow';
    document.getElementById(btn_id).innerHTML='Test Connection';
    document.getElementById(btn_id).style.cursor = 'pointer';
    document.getElementById(btn_id).style.pointerEvents = '';
  });
}
