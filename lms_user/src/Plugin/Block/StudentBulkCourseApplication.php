<?php

namespace Drupal\lms_user\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\lms_user\Form\UserBulkCourseApplicationForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'CSV Upload' widget for student group course application.
 *
 * @Block(
 *   id = "student_bulk_course_application",
 *   admin_label = @Translation("Student Group Course Application CSV Upload Widget"),
 * )
 */
class StudentBulkCourseApplication extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $output['form'] = $this->formBuilder->getForm(UserBulkCourseApplicationForm::class);
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
