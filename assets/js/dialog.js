const template = '<div class="shipany-dialog-overlay shipany-main-dialog" style="display: none;">' +
'<div class="shipany-dialog">' +
'<h2 class="title"></h2>' +
'<p class="detail"></p>' +
'<button type="button" class="shipany-close-btn">Close</button>' +
'</div>' +
'</div>'


jQuery(function ($){
    $('body').append(template)
    $('.shipany-close-btn').click(function (){
        // reset text
        $('.shipany-main-dialog > .title').text('');
        $('.shipany-main-dialog > .detail').text('');
        $('.shipany-main-dialog').hide();
    })
})