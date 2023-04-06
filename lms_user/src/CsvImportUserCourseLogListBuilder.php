<?php

namespace Drupal\lms_user;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for csv_import_user_course_log entity.
 */
class CsvImportUserCourseLogListBuilder extends EntityListBuilder {
  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new CsvImportUserCourseLogListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RequestStack $request_stack) {
    parent::__construct($entity_type, $storage);
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static($entity_type, $container->get('entity_type.manager')->getStorage($entity_type->id()), $container->get('request_stack'));
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\lms_user\Form\CsvImportUserCourseLogFilterForm');
    $build['table'] = parent::render();

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['line_number'] = $this->t('Line');
    $header['created'] = $this->t('Created');
    $header['import_status'] = $this->t('Import Status');
    $header['import_status_detail'] = $this->t('Import Status Detail');
    $header['email'] = $this->t('Email');
    $header['class'] = $this->t('Class');
    $header['course'] = $this->t('Course');
    $header['payment_status'] = $this->t('Payment Status');
    $header['file_name'] = $this->t('File');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    // Format payment status.
    $payment_status = NULL;
    if (!empty($entity->payment_status->value)) {
      if ($entity->payment_status->value == 'paid') {
        $payment_status = $this->t('Paid');
      }
      elseif ($entity->payment_status->value == 'unpaid') {
        $payment_status = $this->t('Unpaid');
      }
      else {
        $payment_status = $entity->payment_status->value;
      }
    }
    // Format import status.
    if (!empty($entity->import_status->value) && $entity->import_status->value == 'ok') {
      $import_status = $this->t('OK');
    }
    else {
      $import_status = $this->t('Fail');
    }
    $row['line_number'] = $entity->line_number->value;
    $row['created'] = date('Y-m-d H:i:s', $entity->created->value);
    $row['import_status'] = $import_status;
    $row['import_status_detail'] = $entity->import_status_detail->value;
    $row['email'] = $entity->email->value;
    $row['class'] = $entity->class->value;
    $row['course'] = $entity->course->value;
    $row['payment_status'] = $payment_status;
    $row['file_name'] = $entity->file_name->value;

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    // Filter CSV import logs.
    $request = $this->requestStack->getCurrentRequest();
    $query = $this->getStorage('csv_import_user_course_log')
      ->getQuery()
      ->sort('created', 'DESC')
      ->sort('line_number', 'DESC');

    if (!empty($request->query->get('status')) && $request->query->get('status') !== 'any') {
      $query->condition('import_status', $request->query->get('status'));
    }

    if (!empty($request->query->get('mail'))) {
      $query->condition('email', $request->query->get('mail'), 'CONTAINS');
    }

    if (!empty($request->query->get('class'))) {
      $query->condition('class', $request->query->get('class'), 'CONTAINS');
    }

    if (!empty($request->query->get('course'))) {
      $query->condition('course', $request->query->get('course'), 'CONTAINS');
    }

    if (!empty($request->query->get('payment_status')) && $request->query->get('payment_status') !== 'any') {
      if ($request->query->get('payment_status') != 'free') {
        $query->condition('payment_status', $request->query->get('payment_status'));
      }
      else {
        $query->notExists('payment_status');
      }
    }

    if (!empty($request->query->get('file_name'))) {
      $query->condition('file_name', $request->query->get('file_name'), 'CONTAINS');
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

}
