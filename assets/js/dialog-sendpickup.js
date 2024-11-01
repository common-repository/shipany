const template_sendpickup = '<div class="shipany-dialog-overlay shipany-main-dialog-sendpickup" style="display: none;">' +
'<div class="shipany-dialog">' +
'<h2 class="title"></h2>' +
'<p class="detail"></p>' +
'<button type="button" class="shipany-yes-btn">Yes</button>' +
'<button type="button" class="shipany-close-btn">No</button>' +
'</div>' +
'</div>'


jQuery(function ($){
    $('body').append(template_sendpickup)
    $('.shipany-close-btn').click(function (){
        // reset text
        $('.shipany-main-dialog-sendpickup > .title').text('');
        $('.shipany-main-dialog-sendpickup > .detail').text('');
        $('.shipany-main-dialog-sendpickup').hide();
    })
})