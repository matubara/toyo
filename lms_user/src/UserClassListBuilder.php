<?php

namespace Drupal\lms_user;

use Drupal\Core\Datetime\DrupalDateTime;
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
 * Provides a list controller for user_class entity.
 */
class UserClassListBuilder extends BatchFormEntityListBuilder {
  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  private $count;

  /**
   * @var \Drupal\lms_user\UserManagerInterface
   */
  private $user_manager;

  /**
   * Constructs a new FranchisorListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
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
    // Proceed with building form.
    $request = \Drupal::request();
    $class_id = $request->query->get('class');
    $class_start_date = $request->query->get('start');
    $class_end_date = $request->query->get('end');

    $user = $request->query->get('user');
    $email = $request->query->get('email');

    if ($class_id) {
      $class = Node::load($class_id);
    }
    else {
      $class = NULL;
    }

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
      '#attributes' => ['size' => 30],
      '#autocreate' => [
        'bundle' => 'class',
      ],
      '#title' => t('Class name'),
      '#default_value' => $class,
    ];
    $form['wrapper']['filter']['user'] = [
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#default_value' => $user,
      '#attributes' => [
        'size' => 30,
      ],
    ];
    $form['wrapper']['filter']['email'] = [
      '#type' => 'textfield',
      '#title' => t('User email address'),
      '#default_value' => $email,
      '#attributes' => [
        'size' => 30,
      ],
    ];
    $form['wrapper']['filter']['class_start_date'] = [
      '#type' => 'date',
      '#title' => t('Class start date'),
      '#default_value' => $class_start_date,
    ];
    $form['wrapper']['filter']['class_end_date'] = [
      '#type' => 'date',
      '#title' => t('Class end date'),
      '#default_value' => $class_end_date,
    ];
    $form['wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['wrapper']['actions']['submit_filter'] = [
      '#type' => 'submit',
      '#value' => t('Filter'),
      '#action_submit' => 'filter',
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

    // Get count without Limit
    $backup = $this->limit;
    $this->limit = 0;
    $this->getEntityIds();
    $count = $this->count;
    $this->limit = $backup;
    $form['count'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . t('Total application number: @count', ['@count' => $count]) . '</h3>',
    ];

    // Call updateFilters here
    $form['wrapper']['filter'] = UserClassController::updateFilters(UserClassListBuilder::newfilters(), [$request], $form['wrapper']['filter'], 'filterpos');

    return $form + parent::buildForm($form, $form_state);
  }

  public function buildHeader() {
    $header['id'] = t('id');
    $header['class'] = t('Class name');
    $header['user'] = t('User name', [], ['context' => 'user_course']);
    $header['mail'] = t('User email address');
    $header['date'] = t('Class period');
    $header['pre_survey'] = t('Pre survey');

    $header = UserClassController::updateHeader(UserClassListBuilder::newfields(), $header);

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
    $class = $user_manager->getClassFromUserClass($entity);
    $class = $class ? ($class->hasTranslation($langcode) ? $class->getTranslation($langcode) : $class) : FALSE;
    $date = $class ? $user_manager->getClassEndDateRangeFormat($class) : '';
    /** @var \Drupal\lms_user\Entity\UserClassInterface $user */
    $user = $entity->getUser();
    $full_name = $entity->getUser() ? $entity->getUser()->getAccountName() : '';

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
    $row['id'] = [
      '#type' => 'link',
      '#title' => $entity->id(),
      '#url' => $entity->toUrl('edit-form'),
    ];
    $row['class'] = [
      '#type' => 'link',
      '#title' => $class ? $class->label() : '',
      '#url' => $class ? $class->toUrl() : '',
    ];
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
    $row['pre_survey'] = [
      '#type' => 'link',
      '#title' => $entity->pre_survey->entity ? $entity->pre_survey->entity->id() : '',
      '#url' => $entity->pre_survey->entity ? $entity->pre_survey->entity->toUrl() : '',
    ];

    $row = UserClassController::updateCallbackMarkup(UserClassListBuilder::newfields(), [$user, [], $entity, $entity->pre_survey->entity], $row);

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
    $newfields = ['submitted_date', 'registration_date', 'field_member_id'];

    return array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $newfields);
  }

  public static function newfilters() {
    $newfilters = ['field_member_id', 'category'];

    return array_map(static function ($i) {
      return UserClassController::dynfields()[$i];
    }, $newfilters);
  }

  public function render() {
    $entity_type_id = $this->entityTypeId;
    $this->actions = array_filter($this->actionStorage->loadMultiple(), static function (ActionConfigEntityInterface $action) use ($entity_type_id) {
      return (string) $action->getType() === $entity_type_id;
    });

    return $this->formBuilder->getForm($this);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();

    if ((string) $triggering['#action_submit'] === 'filter' || (string) $triggering['#action_submit'] === 'download') {
      $query = [];
      $class = $form_state->getValue('class');
      $class_start_date = $form_state->getValue('class_start_date');
      $class_end_date = $form_state->getValue('class_end_date');
      $user = $form_state->getValue('user');
      $email = $form_state->getValue('email');

      if ($class) {
        $query['class'] = $class;
      }

      if ($class_start_date) {
        $query['start'] = $class_start_date;
      }

      if ($class_end_date) {
        $query['end'] = $class_end_date;
      }

      if ($user) {
        $query['user'] = $user;
      }

      if ($email) {
        $query['email'] = $email;
      }

      $query = UserClassController::updateUnique(UserClassListBuilder::newfilters(), $query, 'url', [$form_state]);

      if ((string) $triggering['#action_submit'] === 'filter') {
        $form_state->setRedirect('entity.user_class.collection', $query);
      }
      else {
        return $this->handleBatch(
          $query,
          'lms_user.download_csv_user_class',
          $form_state,
          'Drupal\lms_user\Controller\UserClassController::handleCSVUserClass'
              );
      }
    }
    elseif ((string) $triggering['#action_submit'] === 'reset') {
      $form_state->setRedirect('entity.user_class.collection');
    }
    else {
      return parent::submitForm($form, $form_state);
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();

    if (
      (string) $triggering['#action_submit'] !== 'filter' &&
      (string) $triggering['#action_submit'] !== 'reset' &&
      (string) $triggering['#action_submit'] !== 'download'
    ) {
      parent::validateForm($form, $form_state);
    }
  }

  public function getEntityIds() {
    $request = \Drupal::request();
    $class = $request->query->get('class');
    $class_start_date = $request->query->get('start');
    $class_end_date = $request->query->get('end');
    $user = $request->query->get('user');
    $email = $request->query->get('email');
    $page = $request->query->get('page');
    $page = $page ? $page[0] : 0;
    $query = \Drupal::entityQuery('node')->condition('type', 'class');

    if ($class) {
      $query->condition('nid', $class);
    }

    if ($class_start_date) {
      $start = new DrupalDateTime($class_start_date);
      $start->setTimezone(new \DateTimeZone('UTC'));
      $query->condition('field_period.value', $start->format('Y-m-d\TH:i:s'), '>=');
    }

    if ($class_end_date) {
      $class_end_date = date('Y-m-d', strtotime($class_end_date . ' +1 day'));
      $end = new DrupalDateTime($class_end_date);
      $end->setTimezone(new \DateTimeZone('UTC'));
      $query->condition('field_period.end_value', $end->format('Y-m-d\TH:i:s'), '<');
    }
    $query_class = $query;

    $query = \Drupal::entityQuery('user_class');

    UserClassController::updateUnique(UserClassListBuilder::newfilters(), [], 'query', [$query, $request, $query_class, TRUE]);

    $node_classes = $query_class->execute();
    if (\count($node_classes) > 0) {
      $query->condition('class', $node_classes, 'IN');

      if ($user) {
        $query_user = \Drupal::entityQuery('user');
        $group = $query_user
          ->orConditionGroup()
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

      if ($email) {
        $users = \Drupal::entityTypeManager()
          ->getStorage('user')
          ->loadByProperties(['mail' => $email]);

        if ($users) {
          $query->condition('uid', reset($users)->id());
        }
        else {
          $query->condition('uid', NULL);
        }
      }
      $this->count = \count($query->execute());
    }
    else {
      $query = \Drupal::entityQuery($this->entityTypeId);
      $query->condition('class', '');
      $this->count = 0;
    }

    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

}
