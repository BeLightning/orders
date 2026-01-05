(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        var $form = $('#wc-inquiry-form');
        var $messagesContainer = $('.wc-inquiry-form-messages');
        var $submitButton = $form.find('button[type="submit"]');
        
        $form.on('submit', function(e) {
            e.preventDefault();
            $messagesContainer.empty();
            $submitButton.prop('disabled', true);
            showMessage('processing', wcInquiryForm.messages.processing);
            
            var formData = {
                action: 'submit_inquiry_form',
                nonce: wcInquiryForm.nonce,
                product_id: $form.find('input[name="product_id"]').val(),
                full_name: $form.find('input[name="full_name"]').val(),
                quantity: $form.find('input[name="quantity"]').val(),
                email: $form.find('input[name="email"]').val(),
                phone: $form.find('input[name="phone"]').val()
            };
            
            var $variationId = $form.find('input[name="variation_id"]');
            if ($variationId.length > 0) {
                formData.variation_id = $variationId.val();
                
                if (!formData.variation_id) {
                    showMessage('error', 'Моля, изберете опции за продукта.');
                    $submitButton.prop('disabled', false);
                    return;
                }
            }
            
            $.ajax({
                url: wcInquiryForm.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    $messagesContainer.empty();
                    
                    if (response.success) {
                        showMessage('success', response.data.message);
                        
                        $form[0].reset();
                            $('html, body').animate({
                            scrollTop: $messagesContainer.offset().top - 100
                        }, 500);
                        
                        setTimeout(function() {
                            $submitButton.prop('disabled', false);
                        }, 3000);
                        
                    } else {
                        showMessage('error', response.data.message || wcInquiryForm.messages.error);
                        $submitButton.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    $messagesContainer.empty();
                    showMessage('error', wcInquiryForm.messages.error);
                    $submitButton.prop('disabled', false);
                    console.error('AJAX Error:', error);
                }
            });
        });
        
        function showMessage(type, message) {
            var $message = $('<div>', {
                'class': 'message ' + type,
                'text': message
            });
            
            $messagesContainer.append($message);
        }
        $form.find('input[name="quantity"]').on('input', function() {
            var value = parseInt($(this).val());
            
            if (value < 1) {
                $(this).val(1);
            } else if (value > 10) {
                $(this).val(10);
            }
        });
        
        var $variationsForm = $('.variations_form');
        if ($variationsForm.length > 0) {
            
            $variationsForm.on('found_variation', function(event, variation) {
                if (!$form.find('input[name="variation_id"]').length) {
                    $form.append('<input type="hidden" name="variation_id" value="">');
                }
                $form.find('input[name="variation_id"]').val(variation.variation_id);
            });
            
            $variationsForm.on('reset_data', function() {
                $form.find('input[name="variation_id"]').val('');
            });
            
            $variationsForm.wc_variation_form();
        }
        
       $form.find('input[name="phone"]').on('input', function() {
        var value = $(this).val().replace(/[^\d\+\-\s\(\)]/g, '');
        $(this).val(value);
        
        var digits = value.replace(/\D/g, '');
        var isValid = digits.length >= 10 && (value.startsWith('0') || value.startsWith('+359'));
        
        $(this).toggleClass('error', !isValid && value.length > 0);
    }).on('blur', function() {
        var value = $(this).val();
        var digits = value.replace(/\D/g, '');
        
        if (value && (digits.length < 10 || !(value.startsWith('0') || value.startsWith('+359')))) {
            $(this).addClass('error');
            $(this).siblings('.validation-message').remove();
            $(this).after('<span class="validation-message" style="color:#f56565;font-size:13px;margin-top:5px;display:block;">(мин. 10 цифри, да започва с 0 или +359)</span>');
        } else {
            $(this).removeClass('error');
            $(this).siblings('.validation-message').remove();
        }
    });
        
    });
    
})(jQuery);