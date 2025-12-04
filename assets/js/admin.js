(function($){
    $(document).on('submit', '.trtai-form', function(){
        var $btn = $(this).find('input[type="submit"], button[type="submit"], .button-primary');
        $btn.prop('disabled', true).addClass('disabled');
    });
})(jQuery);
