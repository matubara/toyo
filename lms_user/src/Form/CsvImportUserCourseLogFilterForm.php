<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a CsvImportUserCourseLogFilterForm form.
 */
class CsvImportUserCourseLogFilterForm extends FormBase {

  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * CsvImportUserCourseLogFilterForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'csv_import_user_course_log_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->requestStack->getCurrentRequest();

    $form['wrapper'] = [
      '#type' => 'container',
      '#weight' => -100,
    ];

    $form['wrapper']['filter'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
      '#weight' => -100,
    ];

    $form['wrapper']['filter']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Import status'),
      '#default_value' => $request->query->get('status'),
      '#options' => [
        'any' => $this->t('Any'),
        'ok' => $this->t('OK'),
        'fail' => $this->t('Fail'),
      ],
    ];

    $form['wrapper']['filter']['mail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User email address'),
      '#default_value' => $request->query->get('mail'),
      '#attributes' => [
        'size' => 30,
      ],
    ];

    $form['wrapper']['filter']['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Class name'),
      '#default_value' => $request->query->get('class'),
      '#attributes' => [
        'size' => 30,
      ],
    ];

    $form['wrapper']['filter']['course'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Course name'),
      '#default_value' => $request->query->get('course'),
      '#attributes' => [
        'size' => 30,
      ],
    ];

    $form['wrapper']['filter']['payment_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment status'),
      '#default_value' => $request->query->get('payment_status'),
      '#options' => [
        'any' => $this->t('Any'),
        'paid' => $this->t('Paid'),
        'unpaid' => $this->t('Unpaid'),
        'free' => $this->t('Free'),
      ],
    ];

    $form['wrapper']['filter']['file_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSV file name'),
      '#default_value' => $request->query->get('file_name'),
      '#attributes' => [
        'size' => 30,
      ],
    ];

    $form['wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['wrapper']['actions']['submit_filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    if ($request->getQueryString()) {
      $form['wrapper']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [];

    if (!empty($form_state->getValue('status'))) {
      $query['status'] = $form_state->getValue('status');
    }

    if (!empty($form_state->getValue('mail'))) {
      $query['mail'] = $form_state->getValue('mail');
    }

    if (!empty($form_state->getValue('class'))) {
      $query['class'] = $form_state->getValue('class');
    }

    if (!empty($form_state->getValue('course'))) {
      $query['course'] = $form_state->getValue('course');
    }

    if (!empty($form_state->getValue('payment_status'))) {
      $query['payment_status'] = $form_state->getValue('payment_status');
    }

    if (!empty($form_state->getValue('file_name'))) {
      $query['file_name'] = $form_state->getValue('file_name');
    }

    $form_state->setRedirect('entity.csv_import_user_course_log.collection', $query);
  }

  /**
   * {@inheritdoc}
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.csv_import_user_course_log.collection');
  }

}
