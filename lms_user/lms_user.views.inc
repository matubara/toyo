<?php

/**
 * @file
 * Views integration for Product.
 */

/**
 * Implements hook_views_data().
 *
 * * @see hook_views_data()
 */
function lms_user_views_data() {
  $data = [];
  $node = \Drupal::entityTypeManager()->getDefinition('node');
  $data[$node->getBaseTable()][$node->id() . '_course_number'] = [
    'title' => t('Application numbers (display)'),
    'help' => t('Provides numbers of students applied for courses under this class'),
    'field' => [
      'id' => 'lms_user_applied_course_class',
    ],
  ];

  $data[$node->getBaseTable()][$node->id() . 'submit_number_pre_survey'] = [
    'title' => t('Submit numbers (display)'),
    'help' => t('Provides numbers submit of pre survey'),
    'field' => [
      'id' => 'lms_user_submit_number_pre_survey',
    ],
  ];

  $commerce_product = \Drupal::entityTypeManager()->getDefinition('commerce_product');
  $data[$commerce_product->getBaseTable()][$commerce_product->id() . '_date_of_event'] = [
    'title' => t('Date of event custom'),
    'help' => t('Provides date of event'),
    'field' => [
      'id' => 'lms_user_date_of_event',
    ],
  ];
  return $data;
}
