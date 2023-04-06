<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a user_course entity.
 */
class UserCourseCancelForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to cancel course %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the courses list.
   */
  public function getCancelUrl() {
    return new Url('entity.user_course.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Cancel course');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Back');
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $user = User::load($entity->get('uid')->getValue()[0]['target_id']);
    $form = parent::buildForm($form, $form_state);
    $form['show'] = [
      '#type' => 'markup',
      '#title' => t('Confirm'),
      '#markup' => t("<br />@x<br/>
@y<br/>
@z<br/>", ['@x' => $user->get('field_full_name')->getValue()[0]['value'] ?? $user->get('name')->getValue()[0]['value'] ?? 'N/A', '@y' => $user->get('mail')->getValue()[0]['value'] ?? 'N/A', '@z' => $entity->get('name')->getValue()[0]['value'] ?? 'N/A']),
];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->set('status', 0);
    $entity->save();

    $this->logger('user_course')->notice('%title has been cancelled',
      [
        '%title' => $this->entity->label(),
      ]);
    $form_state->setRedirect('entity.user_course.collection');
  }

}
