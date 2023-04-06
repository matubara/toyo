<?php

namespace Drupal\lms_user\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the CSV Import User Course Log entity.
 *
 * @ContentEntityType(
 *   id = "csv_import_user_course_log",
 *   label = @Translation("CSV Import User Course Log"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\lms_user\CsvImportUserCourseLogListBuilder",
 *     "form" = {
 *       "edit" = "Drupal\lms_user\Form\CsvImportUserCourseLogForm",
 *       "delete" = "Drupal\lms_user\Form\CsvImportUserCourseLogDeleteForm",
 *     },
 *     "access" = "Drupal\entity\EntityAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "csv_import_user_course_log",
 *   data_table = "csv_import_user_course_log_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer csv_import_user_course_log entity",
 *   entity_keys = {
 *     "id" = "csv_import_user_course_log_id",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/orders/csv-import-user-course-log/{csv_import_user_course_log}",
 *     "edit-form" = "/admin/commerce/orders/csv-import-user-course-log/{csv_import_user_course_log}/edit",
 *     "delete-form" = "/admin/commerce/orders/csv-import-user-course-log/{csv_import_user_course_log}/delete",
 *     "collection" = "/admin/commerce/orders/csv-import-user-course-log",
 *   },
 *   field_ui_base_route = "entity.csv_import_user_course_log.collection",
 * )
 */
class CsvImportUserCourseLog extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['line_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Line number'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the UserCourse was created.'));

    $fields['import_status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Import Status'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['import_status_detail'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Import Status'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['email'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Email'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['class'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Email'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['course'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Email'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['payment_status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Payment Status'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['file_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('File Name'))
      ->setSettings([
        'max_length' => 128,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'type' => 'string',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
