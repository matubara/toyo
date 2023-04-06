<?php

namespace Drupal\lms_user\Form;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for cancelling multiple course applied.
 *
 * @internal
 */
class CourseCancelMultipleForm extends ConfirmFormBase {
  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = ['entityManager' => 'entity.manager'];

  /**
   * The temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $courseStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CourseCancelMultipleForm constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->courseStorage = $entity_type_manager->getStorage('user_course');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_course_multiple_cancel_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to cancel these user courses?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.user_course.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Cancel courses');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve the accounts to be canceled from the temp store.
    /** @var \Drupal\lms_user\Entity\UserCourse[] $courses */
    $courses = $this->tempStoreFactory
      ->get('lms_user_course_operations_cancel')
      ->get($this->currentUser()->id());
    if (!$courses) {
      return $this->redirect('entity.user.collection');
    }

    $names = [];
    $form['courses'] = ['#tree' => TRUE];
    foreach ($courses as $course) {
      $uid = $course->id();
      $names[$uid] = $course->label();

      $form['courses'][$uid] = [
        '#type' => 'hidden',
        '#value' => $uid,
      ];
    }

    $form['course']['names'] = [
      '#theme' => 'item_list',
      '#items' => $names,
    ];
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_user_id = $this->currentUser()->id();
    $this->tempStoreFactory->get('lms_user_course_operations_cancel')->delete($current_user_id);
    if ($form_state->getValue('confirm')) {
      foreach ($form_state->getValue('courses') as $uid => $value) {
        $course = $this->courseStorage->load($uid);
        $course->set('status', 0);
        $course->save();
      }
    }

    $form_state->setRedirect('entity.user_course.collection');
  }

}
