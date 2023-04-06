(function ($, Drupal, drupalSettings) {
    'use strict';
    $(document).ready(function () {
        var error = drupalSettings.error;
        if (error) {
            $('.user-login-form').prepend(
                `<div class='text_error'>${error}</div>`
            )
            $('#user-pass-wrapper').prepend(
                `<div class='text_error'>${error}</div>`
            )
        }else{
            $('.user-login-form .text_error').remove()
        }
    })

})(jQuery, Drupal, drupalSettings);


