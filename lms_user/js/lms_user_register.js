(function ($, Drupal, drupalSettings) {
  'use strict';
  $(document).ready(function () {
    let confirm_pass = drupalSettings.confirm_pass;
    let edit_pass = $('#edit-password');
    if (edit_pass.length > 0) {
      let label_pass = $('[for="edit-password-pass1"]');
      if (label_pass.length > 0) {
        label_pass.hide();
      }
      let label_confirm_pass = $('[for="edit-password-pass2"]');
      if (label_confirm_pass.length > 0) {
        label_confirm_pass.html(confirm_pass);
        label_confirm_pass.removeClass('js-form-required');
        label_confirm_pass.removeClass('form-required');
      }
    }

    if (drupalSettings.errors) {
      let errors = JSON.parse(drupalSettings.errors);
      for (var field in errors) {
        setError(field, errors[field]);
      }
      var error_message = drupalSettings.error_message;

      var error =
        '<div class="region region-highlighted message-error-default">';
      error += '<div data-drupal-messages="">';
      error += '<div role="contentinfo" class="messages messages--error">';
      error += '<div role="alert">' + error_message + '</div>';
      error += '</div> </div></div>';
      var resgister = $('#user-register-form .lms-user-register-step');
      if (resgister.length >= 1) {
        resgister.after(error);
      } else {
        var edit = $('.lms-user-edit-profile');
        edit.after(error);
      }
    }

    $('.block-register-button .register-now-button')
      .once()
      .click(function (event) {
        event.preventDefault();
        var speed = 500;
        var href = $(this).attr('href');
        var target = $(
          !href || href[0] !== '#' || href === ''
            ? '#block-lms-theme-content'
            : href
        );
        var position = target.offset().top - 50;
        $('html, body').animate({ scrollTop: position }, speed, 'swing');
        return false;
      });

    function setError(name, error_label) {
      var element = $('[name^="' + name + '"]');
      element.addClass('error');
      const method = error_label['#type'] || 'prepend';
      delete error_label['#type'];
      error_label = typeof error_label === 'object' ? error_label[0] : error_label;
      if (element.length > 0) {
        let error_html =
          '<div class="text_error"><p>' + error_label + '</p></div>';
        var type = $(element[0]).attr('type');
        if (type === 'checkbox') {
          $(element[0]).parent()[method](error_html);
        } else if (type === 'radio') {
          $(element[0]).closest('.form-radios').before(error_html);
        } else {
          $(element[0]).parent()[method](error_html);
        }
      }
    }
  });
})(jQuery, Drupal, drupalSettings);
