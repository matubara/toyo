<?php

namespace Drupal\lms_user\Form;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for Update application number of course.
 *
 * @ingroup lms_user
 */
class UpdateApplicationNumberCoursesForm extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new NodeRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'lms_user_update_application_number';
  }

  /**
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getTitle() {
    return $this->t('Are you want to Update application number of course?');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit_update'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
      "#weight" => 1,
      '#button_type' => 'primary',
    ];

    $form['update_label'] = [
      '#type' => 'label',
      '#title' => $this->t('This action cannot be undone.'),
    ];

    $form['submit_cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#weight' => 2,
      '#submit' => [[$this, 'submitFormTwo']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @TODO: Move to UserCourse logic with isPublished
    $sql = "SELECT cp.product_id AS course_id, COUNT(uc.course) AS application_number FROM commerce_product AS cp LEFT JOIN user_course_field_data AS uc ON cp.product_id = uc.course AND uc.status = 1 AND uc.uid IS NOT NULL WHERE cp.type = 'course' GROUP BY cp.product_id";
    $result = \Drupal::database()->query($sql)->fetchAll();
    if ($result) {
      $course_ids = [];
      $course_application_data = [];
      foreach ($result as $course_data) {
        $course_ids[] = $course_data->course_id;
        $course_application_data[$course_data->course_id] = (int) $course_data->application_number;
      }

      $products = $this->entityTypeManager->getStorage('commerce_product')->loadMultiple($course_ids);
      $operations = [];
      foreach ($products as $product) {
        $operations[] = [
          'Drupal\lms_user\Form\UpdateApplicationNumberCoursesForm::updateApplicationNumber',
          [$product, $course_application_data[$product->id()]],
        ];

      }
      $batch = [
        'title' => count($products) > 1 ? t('Update @num courses', ['@num' => count($products)]) : t('Update @num course', ['@num' => count($products)]),
        'operations' => $operations,
        'progress_message' => $this->t('Processed @current out of @total.'),
        'finished' => 'Drupal\lms_user\Form\UpdateApplicationNumberCoursesForm::updateApplicationNumberCompleted',
      ];
      batch_set($batch);
    }
    else {
      $products = Product::loadMultiple();
      $operations = [];
      foreach ($products as $product) {
        $operations[] = [
          'Drupal\lms_user\Form\UpdateApplicationNumberCoursesForm::updateApplicationNumber',
          [$product, 0],
        ];
        $batch = [
          'title' => count($products) > 1 ? t('Update @num courses', ['@num' => count($products)]) : t('Update @num course', ['@num' => count($products)]),
          'operations' => $operations,
          'progress_message' => $this->t('Processed @current out of @total.'),
          'finished' => 'Drupal\lms_user\Form\UpdateApplicationNumberCoursesForm::updateApplicationNumberCompleted',
        ];
        batch_set($batch);
      }
    }
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitFormTwo(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.commerce_product.collection');
  }

  /**
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   * @param int $application_number
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function updateApplicationNumber(ProductInterface $product, int $application_number) {
    $product->set('field_application_number', $application_number);
    $product->save();
  }

  /**
   * Show message after run batch.
   */
  public static function updateApplicationNumberCompleted() {
    \Drupal::messenger()->addMessage(t('Update application number of course successfully'));
  }

}
