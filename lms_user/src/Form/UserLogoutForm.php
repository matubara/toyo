<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class UserLogoutForm extends FormBase {

  public function getFormId() {
    return 'user_logout_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    if ($current_user->isAuthenticated()) {
      user_logout();
    }

    $form['message'] = [
      '#markup' => "<div>" . t('You have successfully logged out.') . "</div>",
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'custom-group-button'],
    ];

    $form['actions']['to_page'] = [
      '#type' => 'link',
      '#title' => t('Top Page'),
      '#prefix' => '<div class="btn-to-page">',
      '#suffix' => '</div>',
      '#url' => Url::fromRoute('<front>'),
    ];

    $form['actions']['search_subject'] = [
      '#type' => 'link',
      '#title' => t('Find a course'),
      '#prefix' => '<div class="btn-search-subject">',
      '#suffix' => '</div>',
      '#url' => Url::fromRoute('view.lms_class.page_1'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

}
