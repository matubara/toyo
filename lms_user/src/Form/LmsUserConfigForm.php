<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class LmsUserConfigForm.
 * Provies config of translatable strings.
 */
class LmsUserConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['lms_user.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lms_user_config_form';
  }

  public function texts() {
    // @TODO: Should module invoke for other to join in
    $transition_mails = [];
    foreach (['transition_changed_canceled', 'auto_order', 'manual_order:single'] as $key) {
      list ($key, $type) = explode(":", $key) + [FALSE, FALSE];
      foreach (['', $type ? FALSE : 'admin_'] as $role) {
        if ($role === FALSE) {
          continue;
        }
        $transition_mails = array_merge($transition_mails, [$role . $key . '_subject:textfield:' . $key, $role . $key . '_body:text_format:' . $key]);
      }
    }
    return array_merge(['password_tutorial', 'presurvey_tutorial', 'presurveydone_tutorial', 'usercourses_tutorial', 'cart_help1', 'cart_help3', 'cart_help2', 'checkout_steps', 'order_help1', 'order_help2'], $transition_mails);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('lms_user.settings');
    // This is a hack for translations to work
    $form['example'] = [
      '#type' => 'textarea',
      '#access' => FALSE,
      '#title' => $this->t('Example'),
      '#default_value' => $config->get('example'),
    ];
    foreach ($this->texts() as $key) {
      list ($key, $type, $group) = explode(":", $key) + [FALSE, FALSE, FALSE];
      $type = $type ? $type : 'text_format';
      $element = [
        '#type' => $type,
        '#title' => $this->t('Config @config', ['@config' => $key]),
        '#default_value' => $type === 'text_format' ? $config->get($key)['value'] : $config->get($key),
        '#format' => $type === 'text_format' ? $config->get($key)['format'] : '',
      ];
      if (!$group) {
        $group = 'common';
      }
      $form[$group] = ($form[$group] ?? FALSE) ? $form[$group] : [
        '#type' => 'details',
        '#title' => ucfirst(str_replace('_', ' ', $group)),
        '#collapsed' => FALSE,
        '#collapsible' => TRUE,
      ];
      $form[$group][$key] = $element;
    }
    $form['how_to_page_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('How to page url'),
      '#default_value' => $config->get('how_to_page_url') ?? '',
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('lms_user.settings')->set('example', $form_state->getValue('example'));
    $config->set('how_to_page_url', $form_state->getValue('how_to_page_url'));
    foreach ($this->texts() as $key) {
      list ($key, $type) = explode(":", $key) + [FALSE, FALSE];
      $config->set($key, $form_state->getValue($key));
    }
    $config->save();
  }

}
