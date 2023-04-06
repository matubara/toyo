<?php

namespace Drupal\lms_user\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Display date of event.
 *
 * @ViewsField("lms_user_date_of_event")
 */
class DateOfEvent extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $product = $this->getEntity($values);
    $start_date_format = NULL;
    $date_of_event_values = $product->get('field_day_of_the_event')->getValue();
    if ($date_of_event_values) {
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $user_manager = \Drupal::service('lms_user.manager');
      $start_date_with_timezone = $user_manager->getTimeStampWithTimezone($date_of_event_values[0]['value'], FALSE);
      $start_date_data = $user_manager->getDateData($start_date_with_timezone);
      $start_date_format = $user_manager->getDateFormatResponse($langcode, $start_date_data, 'date_of_event');
    }
    return [
      '#type' => 'markup',
      '#markup' => '<span class="custom-date-of-event">' . $start_date_format . '</span>',
    ];
  }

}
