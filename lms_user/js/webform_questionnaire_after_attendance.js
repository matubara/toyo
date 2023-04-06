(function ($, Drupal, drupalSettings) {
  'use strict';

  function setError(name, error_label) {
    var element = null;
    var node_name = null;
    var element_second = null;
    var node_name_second = null;
    if (name.search('reason') === -1 && name.indexOf('[') === -1) {
      if (name === 'email_reenter') {
        element = $('[name^="' + name + '[mail_1]"]');
        node_name = element[0].nodeName;
        setErrorElement(name, element, node_name, error_label);

        element_second = $('[name^="' + name + '[mail_2]"]');
        node_name_second = element_second[0].nodeName;
        setErrorElement(name, element_second, node_name_second, error_label);
      } else {
        if (name === 'email') {
          element = $('[name=email]');
        } else {
          element = $('[name^="' + name + '"]');
        }
        node_name = element[0].nodeName;
        setErrorElement(name, element, node_name, error_label);
      }
    } else if (
      name === 'current_area_of_stay][other' ||
      name === 'attribute][other' ||
      name === 'contact_type][other' ||
      name === 'about_the_equipment_used][other' ||
      name === 'how_i_learned_about_the_course][other' ||
      name === 'motivation_for_application][other' ||
      name === 'current_japanese_ability][other' ||
      name.search('reason') !== -1
    ) {
      if (name === 'current_area_of_stay][other') {
        name = 'current_area_of_stay[other]';
      } else if (name === 'attribute][other') {
        name = 'attribute[other]';
      } else if (name === 'contact_type][other') {
        name = 'contact_type[other]';
      } else if (name === 'about_the_equipment_used][other') {
        name = 'about_the_equipment_used[other]';
      } else if (name === 'how_i_learned_about_the_course][other') {
        name = 'how_i_learned_about_the_course[other]';
      } else if (name === 'motivation_for_application][other') {
        name = 'motivation_for_application[other]';
      } else if (name === 'current_japanese_ability][other') {
        name = 'current_japanese_ability[other]';
      }
      element = $('[name^="' + name + '"]');
      node_name = element[0].nodeName;
      setErrorElement(name, element, node_name, error_label);
    }
  }

  function setErrorElement(name, element, node_name, error_label) {
    if (element.length > 0) {
      var error_html =
        '<div class="text_error"><p>' + error_label + '</p></div>';
      var type = $(element[0]).attr('type');
      if (type === 'checkbox') {
        if (
          name === 'i_checked_the_terms_of_use' ||
          name === 'i_agree_to_the_handling_of_personal_information'
        ) {
          $(element[0]).parent().find('input').before(error_html);
        } else {
          $(element[0]).parent().before(error_html);
        }
      } else if (type === 'radio') {
        $(element[0]).closest('.form-radios').before(error_html);
      } else if (type === 'text') {
        $(element[0]).before(error_html);
      } else if (node_name === 'SELECT') {
        $(element[0]).closest('.form-no-label').before(error_html);
      } else if (node_name === 'INPUT' || node_name === 'TEXTAREA') {
        $(element[0]).before(error_html);
      }
    }
  }

  var errors = drupalSettings.errors;
  if (errors) {
    // Hide moved errors
    $(".messages.messages--error").hide();
  }
  $(document).ready(function () {
    var errors = drupalSettings.errors;
    if (errors) {
      errors = JSON.parse(drupalSettings.errors);
      for (var name in errors) {
        setError(name, errors[name]);
      }


      var error_message = drupalSettings.error_message;
      if (error_message != null) {
        var error =
          '<div class="region region-highlighted message-error-default">';
        error += '<div data-drupal-messages="">';
        error += '<div role="contentinfo" class="messages messages--error">';
        error +=
          '<div role="alert">' + error_message + '</div></div></div></div>';
        var progress = $('.webform-progress');
        if (progress) {
          progress.after(error);
        }
      }
    }
    var contact_block = drupalSettings.contact_us_add_form_input;
    if (contact_block === false) {
      $('#block-headercontact').remove();
    }
  });
})(jQuery, Drupal, drupalSettings);
