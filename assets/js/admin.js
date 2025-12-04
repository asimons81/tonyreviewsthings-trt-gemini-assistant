(function($){
    $(document).on('submit', '.trtai-form', function(){
        var $btn = $(this).find('input[type="submit"], button[type="submit"], .button-primary');
        $btn.prop('disabled', true).addClass('disabled');
    });

    $(document).on('click', '.trtai-deal-preview-btn', function(event){
        event.preventDefault();

        var $button  = $(this);
        var $form    = $button.closest('form');
        var $preview = $('#trtai-deal-preview');
        var ajaxUrl  = (window.trtaiAdmin && window.trtaiAdmin.ajax_url) ? window.trtaiAdmin.ajax_url : (window.ajaxurl || '');

        if (!ajaxUrl) {
            $preview.html('<p class="trtai-error">' + (window.trtaiAdmin && window.trtaiAdmin.preview_error ? window.trtaiAdmin.preview_error : 'Missing AJAX URL.') + '</p>');
            return;
        }

        $button.prop('disabled', true);
        $preview.addClass('is-loading').html('<p>' + (window.trtaiAdmin && window.trtaiAdmin.preview_loading ? window.trtaiAdmin.preview_loading : 'Generating preview…') + '</p>');

        var formData = $form.serializeArray();
        formData.push({ name: 'action', value: 'trtai_preview_deal' });

        $.post(ajaxUrl, formData)
            .done(function(response){
                if (response && response.success && response.data) {
                    var html = '';
                    if (response.data.title) {
                        html += '<h3 class="trtai-preview-title">' + response.data.title + '</h3>';
                    }
                    if (response.data.excerpt) {
                        html += '<p class="trtai-preview-excerpt">' + response.data.excerpt + '</p>';
                    }
                    if (response.data.content) {
                        html += '<div class="trtai-preview-content">' + response.data.content + '</div>';
                    }

                    $preview.removeClass('is-loading').html(html || '<p class="description">No preview content returned.</p>');
                } else {
                    $preview.removeClass('is-loading').html('<p class="trtai-error">' + (response && response.data && response.data.message ? response.data.message : (window.trtaiAdmin && window.trtaiAdmin.preview_error ? window.trtaiAdmin.preview_error : 'Unable to generate preview.')) + '</p>');
                }
            })
            .fail(function(){
                $preview.removeClass('is-loading').html('<p class="trtai-error">' + (window.trtaiAdmin && window.trtaiAdmin.preview_error ? window.trtaiAdmin.preview_error : 'Unable to generate preview.') + '</p>');
            })
            .always(function(){
                $button.prop('disabled', false);
            });
    });
})(jQuery);
