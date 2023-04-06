<?php

namespace Drupal\lms_user;

use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lms_user\Controller\UserClassController;
use Drupal\node\Entity\Node;
use Drupal\system\ActionConfigEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for user_course entity.
 */
class UserCourseListBuilder extends BatchFormEntityListBuilder {
  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  private $user_manager;

  /**
   * UserCourseListBuilder constructor.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $entity_storage,
    EntityStorageInterface $action_storage,
    FormBuilderInterface $form_builder,
    UserManagerInterface $user_manager
  ) {
    parent::__construct($entity_type, $entity_storage, $action_storage, $form_builder);
    $this->user_manager = $user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $course = $request->query->get('course');
    $user = $request->query->get('user');
    $mail = $request->query->get('mail');
    $course_start_date = $request->query->get('start');
    $course_end_date = $request->query->get('end');
    $course = $course ? Product::load($course) : NULL;
    $class = $request->query->get('class');
    $class = $class ? Node::load($class) : NULL;
    $form['wrapper'] = [
      '#type' => 'container',
      '#weight' => -100,
    ];

    $form['wrapper']['filter'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
      '#weight' => -100,
    ];
    $form['wrapper']['filter']['class'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['class'],
      ],
      '#title' => t('Class name'),
      '#default_value' => $class,
      '#attributes' => [
        'size' => 30,
      ],
    ];
    $form['wrapper']['filter']['course'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'commerce_product',
      '#autocreate' => [
        'bundle' => 'product',
      ],
      '#title' => t('Course name'),
      '#default_value' => $course,
      '#attributes' => [
        'size' => 30,
      ],
    ];
    $form['wrapper']['filter']['user'] = [
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $user,
      '#attributes' => [
        'size' => 30,
      ],
    ];
    $form['wrapper']['filter']['mail'] = [
      '#type' => 'textfield',
      '#title' => t('User email address'),
      '#default_value' => $mail,
      '#attributes' => [
        'size' => 30,
      ],
    ];

    $form['wrapper']['filter']['course_start_date'] = [
      '#type' => 'date',
      '#title' => t('Course start date'),
      '#default_value' => $course_start_date,
    ];
    $form['wrapper']['filter']['course_end_date'] = [
      '#type' => 'date',
      '#title' => t('Course end date'),
      '#default_value' => $course_end_date,
    ];

    $form['wrapper']['actions'] = [
      '#type' => 'actions',
    ];
    $form['wrapper']['actions']['submit_filter'] = [
      '#type' => 'submit',
      '#value' => t('Filter'),
      '#action_submit' => t('filter'),
    ];
    $form['wrapper']['actions']['submit_download'] = [
      '#type' => 'submit',
      '#value' => t('Download csv'),
      '#action_submit' => t('download'),
    ];

    if ($request->getQueryString()) {
      $form['wrapper']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => t('Reset'),
        '#action_submit' => 'reset',
      ];
    }

    // Call updateFilters here
    $form['wrapper']['filter'] = UserClassController::updateFilters(UserCourseListBuilder::newfilters(), [$request], $form['wrapper']['filter'], 'filterpos');

    return $form + parent::buildForm($form, $form_state);
  }

  public function buildHeader() {
    $header['class'] = $this->t('Class name');
    $header['course'] = $this->t('Course name');
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('commerce_product', 'course');
    $field = $fields['field_application_number'] ?? FALSE;
    $application_number_course = '';

    if ($field) {
      $header['application_number'] = $this->t('Application number');
    }
    $header['user'] = $this->t('User name', [], ['context' => 'user_course']);
    $header['mail'] = $this->t('User email address');
    $header['date'] = $this->t('Course period');
    $header['status'] = $this->t('Status');
    $header['post_survey'] = $this->t('Post Survey');

    $header = UserClassController::updateHeader(UserCourseListBuilder::newfields(), $header, 'coursepos');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $langcode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();
    /** @var \Drupal\lms_user\UserManagerInterface $user_manager */
    $user_manager = \Drupal::service('lms_user.manager');
    /** @var \Drupal\lms_user\Entity\UserCourseInterface $entity */
    $course = $user_manager->getCourseFromUserCourse($entity);
    /** @var \Drupal\lms_user\Entity\UserClassInterface $class */
    if ($course) {
      $course = $course->hasTranslation($langcode) ? $course->getTranslation($langcode) : $course;
    }
    /** @var \Drupal\node\NodeInterface $class */
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('commerce_product', 'course');
    $field = $fields['field_application_number'] ?? FALSE;
    $application_number_course = '';

    if ($field) {
      $application_number_course = $course ? $course->get('field_application_number')->getString() : '0';
    }
    $user_class = $entity->class->entity;
    $class = $user_class->class->entity;

    if ($class) {
      $class = $class->hasTranslation($langcode) ? $class->getTranslation($langcode) : $class;
    }
    $date = $course ? $user_manager->getCourseEndDateRangeFormat($course) : '';
    $user = $entity->getUser();

    if ($user) {
      if ($user->get('field_full_name')->getString()) {
        $full_name = $user->get('field_full_name')->getString();
      }
      else {
        $full_name = $user->getAccountName();
      }
    }
    else {
      $full_name = '';
    }
    $row['class'] = [
      '#type' => 'link',
      '#title' => $class ? $class->label() : NULL,
      '#url' => $class ? $class->toUrl() : NULL,
    ];
    $row['course'] = [
      '#type' => 'link',
      '#title' => $course ? $course->getTitle() : NULL,
      '#url' => $course ? $course->toUrl() : NULL,
    ];
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('commerce_product', 'course');
    $field = $fields['field_application_number'] ?? FALSE;

    if ($field) {
      $row['application_number'] = [
        '#type' => 'markup',
        '#markup' => $application_number_course,
      ];
    }
    $row['user'] = [
      '#type' => 'link',
      '#title' => $full_name,
      '#url' => $user ? $user->toUrl() : '',
    ];
    $row['mail'] = [
      '#type' => 'link',
      '#title' => $user ? $user->getEmail() : '',
      '#url' => $user ? $user->toUrl() : '',
    ];
    $row['date'] = [
      '#markup' => $date,
    ];
    $row['status'] = [
      '#markup' => (string) $entity->status->getString() === '1' ? t('Published') : t('Unpublished'),
    ];
    $row['post_survey'] = [
      '#type' => 'link',
      '#title' => $entity->post_survey->entity ? $entity->post_survey->entity->id() : '',
      '#url' => $entity->post_survey->entity ? $entity->post_survey->entity->toUrl() : '',
    ];

    $row = UserClassController::updateCallbackMarkup(UserCourseListBuilder::newfields(), [$user, [], $entity, $entity->post_survey->entity], $row, 'coursepos');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('action'),
      $container->get('form_builder'),
      $container->get('lms_user.manager')
    );
  }

  /**
   * Share config at UserClassController::dynfields.
   */
  public static function newfields() {
    $newfields = ['submitted_date', 'registration_date', 'field_member_id', 'field_application_by_csv'];

    return array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $newfields);
  }

  public static function newfilters() {
    $newfilters = ['field_member_id', 'category', 'field_application_by_csv'];

    return array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $newfilters);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entity_type_id = $this->entityTypeId;
    $this->actions = array_filter($this->actionStorage->loadMultiple(), static function (ActionConfigEntityInterface $action) use ($entity_type_id) {
      return (string) $action->getType() === $entity_type_id;
    });

    return $this->formBuilder->getForm($this);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();
    $action = (string) $triggering['#action_submit'];

    if ($action === 'filter' || $action === 'download') {
      $query = [];
      $class = $form_state->getValue('class');
      $course = $form_state->getValue('course');
      $course_start_date = $form_state->getValue('course_start_date');
      $course_end_date = $form_state->getValue('course_end_date');
      $user = $form_state->getValue('user');
      $mail = $form_state->getValue('mail');

      if (!empty($class)) {
        $query['class'] = $class;
      }

      if (!empty($course)) {
        $query['course'] = $course;
      }

      if (!empty($course_start_date)) {
        $query['start'] = $course_start_date;
      }

      if (!empty($course_end_date)) {
        $query['end'] = $course_end_date;
      }

      if (!empty($user)) {
        $query['user'] = $user;
      }

      if (!empty($mail)) {
        $query['mail'] = $mail;
      }

      $query = UserClassController::updateUnique(UserCourseListBuilder::newfilters(), $query, 'url', [$form_state]);

      if ((string) $triggering['#action_submit'] === 'filter') {
        $form_state->setRedirect('entity.user_course.collection', $query);
      }
      else {
        return $this->handleBatch(
          $query,
          'lms_user.download_csv_user_course',
          $form_state,
          'Drupal\lms_user\Controller\UserClassController::handleCSVUserCourse'
              );
      }
    }
    elseif ((string) $triggering['#action_submit'] === 'reset') {
      $form_state->setRedirect('entity.user_course.collection');
    }
    else {
      return parent::submitForm($form, $form_state);
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();
    $action = (string) $triggering['#action_submit'];

    if ($action !== 'filter' && $action !== 'reset' && $action !== 'download') {
      parent::validateForm($form, $form_state);
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function getEntityIds() {
    // @refer to downloadCSVUserCourse of UserCourseListBuilder
    // Same course but bit different
    $this->limit = 20;
    $request = \Drupal::request();
    $class = $request->query->get('class');
    $course = $request->query->get('course');
    $course_start_date = $request->query->get('start');
    $course_end_date = $request->query->get('end');
    $user = $request->query->get('user');
    $mail = $request->query->get('mail');
    $page = $request->query->get('page');
    $page = $page ? $page[0] : 0;

    $query_course = \Drupal::entityQuery('commerce_product');

    if ($class) {
      $query_course->condition('field_class', $class);
    }

    if ($course) {
      $query_course->condition('product_id', $course);
    }

    if ($course_start_date) {
      // Convert JP time to UTC time
      $start = date(\DATE_ATOM, jp_to_utc_time(strtotime($course_start_date)));
      $query_course->condition('field_day_of_the_event.value', $start, '>=');
    }

    if ($course_end_date) {
      $end = date(
        \DATE_ATOM,
        strtotime('+1 day', jp_to_utc_time(strtotime($course_end_date)))
        // Convert JP time to UTC time
      );
      $query_course->condition('field_day_of_the_event.end_value', $end, '<=');
    }
    $query = \Drupal::entityQuery('user_course');
    UserClassController::updateUnique(UserCourseListBuilder::newfilters(), [], 'query', [$query, $request, $query_course, FALSE]);

    $courses = $query_course->execute();
    if (\count($courses) > 0) {
      if ($user) {
        $query_user = \Drupal::entityQuery('user');
        $group = $query_user
          ->orConditionGroup()
          ->condition('field_full_name', $user, 'LIKE')
          ->condition('field_full_name', $user . '%', 'LIKE')
          ->condition('field_full_name', '%' . $user . '%', 'LIKE')
          ->condition('field_full_name', '%' . $user, 'LIKE')
          ->condition('name', $user, 'LIKE')
          ->condition('name', $user . '%', 'LIKE')
          ->condition('name', '%' . $user . '%', 'LIKE')
          ->condition('name', '%' . $user, 'LIKE');
        $users = $query_user->condition($group)->execute();

        if (empty($users)) {
          $query->condition('uid', '');
        }
        else {
          $query->condition('uid', $users, 'IN');
        }
      }

      if ($mail) {
        $users = \Drupal::entityTypeManager()
          ->getStorage('user')
          ->loadByProperties(['mail' => $mail]);

        if ($users) {
          $query->condition('uid', reset($users)->id());
        }
        else {
          $query->condition('uid', NULL);
        }
      }
      $query->condition('course', $courses, 'IN');
    }
    else {
      $query->condition('course', '');
      $this->count = 0;
    }

    if ($this->limit) {
      $query->pager($this->limit);
    }

    $query = $query->sort('changed', 'DESC');
    return $query->execute();
  }

}
