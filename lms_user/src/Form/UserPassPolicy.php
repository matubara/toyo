<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * @TODO
 */
class UserPassPolicy extends FormBase {

  public function getFormId() {
    return 'hmm';
  }

  public function getEntity() {
    return $this->user ? $this->user : User::create([]);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Optional pw
    if (!function_exists('_password_policy_show_policy')) {
      return $form;
    }
    // Attach password policy
    $show_password_policy_status = _password_policy_show_policy();
    $form['#validate'][] = [$this, 'changePassField'];
    // Custom LMS registration form
    if (TRUE || $show_password_policy_status) {
      $form['#validate'][] = '_password_policy_user_profile_form_validate';
      $form['#after_build'][] =
        '_password_policy_user_profile_form_after_build';
    }

    $form['#validate'][] = [$this, 'updateFormErrors'];

    // Add the submit handler.
    if ($form['#password_policy_store'] ?? FALSE) {
      $form['submit']['#submit'][] =
        '_password_policy_user_profile_form_submit';
    }
    $form['#force_password_policy'] = TRUE;
    return $form;
  }

  public function changePassField(
    array &$form,
    FormStateInterface $form_state
  ) {
    $form_state->setValue('pass', $form_state->getValue('password', ''));
  }

  public function updateFormErrors(
    array &$form,
    FormStateInterface $form_state
  ) {
    $errors = $form_state->getErrors();
    $form_state->clearErrors();
    if ($errors['pass'] ?? FALSE) {
      // Overwrite the errors messages
      // $errors['pass'];
      $errors['password'][] = $this->t('* 8 to 20 single-byte alphanumeric characters<br>* It is recommended to set a more complicated password.');
    }
    unset($errors['pass']);
    foreach ($errors as $key => $err) {
      $form_state->setErrorByName($key, $err);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
