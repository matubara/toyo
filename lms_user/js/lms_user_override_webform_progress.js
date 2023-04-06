(function ($, Drupal, drupalSettings) {
    'use strict';
    $(document).ready(function () {
        var progress = drupalSettings.form_progress;
        var webform_progress = $('.webform-progress').find('ul li');
        var step = 0;
        webform_progress.each(function () {
            var progress_title = $(this).find('span:nth-child(2)');
            progress_title.html(progress[step]);
            step++;
        })
    })
})(jQuery, Drupal, drupalSettings);
