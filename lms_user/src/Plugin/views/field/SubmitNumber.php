<?php

namespace Drupal\lms_user\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Display course status.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("lms_user_submit_number_pre_survey")
 */
class SubmitNumber extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function elementType($none_supported = FALSE, $default_empty = FALSE, $inline = FALSE) {
    return 'div';
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    /** @var \Drupal\Node\NodeInterface $class */
    $class = $this->getEntity($row);
    /** @var \Drupal\lms_user\UserManagerInterface $user_manager */
    $user_manager = \Drupal::service('lms_user.manager');
    $number_presurvey_class = $user_manager->getNumberOfPreSurveySubmission($class);
    $build = [
      '#markup' => '<span class="submit-number-pre-survey>' . $number_presurvey_class . '</span>',
    ];
    return \Drupal::service('renderer')->render($build);
  }

}
