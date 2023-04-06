<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the csv_import_user_course_log entity edit forms.
 */
class CsvImportUserCourseLogForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.csv_import_user_course_log.collection');
    $entity = $this->getEntity();
    $entity->save();
  }

}
