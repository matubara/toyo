<?php

namespace Drupal\lms_user\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\Entity\User;

/**
 * Defines the User Course entity.
 *
 * @ContentEntityType(
 *     id="user_course",
 *     label=@Translation("User Course"),
 *     label_collection=@Translation("Course application / Questionnaire management after attendance"),
 *     handlers={
 *         "views_data": "Drupal\views\EntityViewsData",
 *         "view_builder": "Drupal\Core\Entity\EntityViewBuilder",
 *         "list_builder": "Drupal\lms_user\UserCourseListBuilder",
 *         "form": {
 *             "default": "Drupal\lms_user\Form\UserCourseForm",
 *             "add": "Drupal\lms_user\Form\UserCourseForm",
 *             "edit": "Drupal\lms_user\Form\UserCourseForm",
 *             "delete": "Drupal\lms_user\Form\UserCourseDeleteForm",
 *             "delete-multiple-confirm": "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *             "cancel": "Drupal\lms_user\Form\UserCourseCancelForm",
 *         },
 *         "access": "Drupal\entity\EntityAccessControlHandler",
 *         "permission_provider": "Drupal\entity\EntityPermissionProvider",
 *         "local_task_provider": {
 *             "default": "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *         },
 *         "route_provider": {
 *             "default": "Drupal\entity\Routing\AdminHtmlRouteProvider",
 *         },
 *     },
 *     base_table="user_course",
 *     data_table="user_course_field_data",
 *     translatable=TRUE,
 *     admin_permission="administer user_course entity",
 *     entity_keys={
 *         "id": "user_course_id",
 *         "label": "name",
 *         "uuid": "uuid",
 *         "uid": "uid",
 *         "published": "status",
 *         "langcode": "langcode",
 *     },
 *     links={
 *         "collection": "/admin/commerce/lms/user-course",
 *         "canonical": "/admin/commerce/lms/user-course/{user_course}",
 *         "add-form": "/admin/commerce/lms/user-course/course/add",
 *         "edit-form": "/admin/commerce/lms/user-course/{user_course}/edit",
 *         "delete-form": "/admin/commerce/lms/user-course/{user_course}/delete",
 *         "delete-multiple-form": "/admin/commerce/lms/user-course/delete-multiple",
 *         "cancel-form": "/admin/commerce/lms/user-course/{user_course}/cancel"
 *     },
 *     field_ui_base_route="entity.user_course.collection",
 * )
 */
class UserCourse extends ContentEntityBase implements UserCourseInterface {

  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(
    EntityTypeInterface $entity_type
  ) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user ID of the node author.'))
      ->setSettings([
        'target_type' => 'user',
        'default_value' => 0,
      ]);

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
        // 'region' => 'hidden',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['course'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'))
      ->setDescription(t('The course product that user bought.'))
      ->setSetting('target_type', 'commerce_product')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
      ])
      ->setDisplayOptions('view', ['type' => 'string', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['class'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class'))
      ->setDescription(t('The subject of course.'))
      ->setSetting('target_type', 'user_class')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
      ])
      ->setDisplayOptions('view', ['type' => 'string', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['post_survey'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Post Survey'))
      ->setDescription(t('The webform submission post survey.'))
      ->setSetting('target_type', 'webform_submission')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
      ])
      ->setDisplayOptions('view', ['type' => 'string', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['order'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order'))
      ->setDescription(t('The order that user applied the course.'))
      ->setSetting('target_type', 'commerce_order')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
      ])
      ->setDisplayOptions('view', ['type' => 'string', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['finished'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Course status'))
      ->setDescription(t('States of course is finished.'))
      ->setDefaultValue(FALSE)
      ->setSettings([
        'on_label' => t('Finished'),
        'off_label' => t('Unfinished'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 90,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the UserCourse was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the node was last edited.'));

    $fields['state'] = BaseFieldDefinition::create("list_string")
      ->setSettings([
        'allowed_values' => [
              'draft' => 'Draft',
              'cancel_requested' => 'Cancel Request',
              'refunded' => 'Refunded',
              'canceled' => 'Canceled',
              'rejected' => 'Rejected',
              'completed' => 'Paid',
        ],
      ])
      ->setLabel('State')
      ->setDescription('Payment state. This will also affect course status if changing directly!')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);
    return $fields;
  }

  public function getStatus() {
    return $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    $uid = $this->getEntityKey('uid');

    return $uid ? User::load($uid) : NULL;
  }

  public function getSurvey() {
    $items = $this->get('post_survey')->referencedEntities();
    return reset($items);
  }

  public function getCourse() {
    $courses = $this->get('course')->referencedEntities();
    return reset($courses);
  }

  public function isWebformDeleted() {
    return $this->getSurveyWebform() === 0;
  }

  public function getSurveyWebform() {
    // Try to load the webform from submitted survey
    $sv = $this->getSurvey();
    if ($sv && $sv->getWebform()) {
      return $sv->getWebform();
    }

    // Other wise load from the course
    $c = $this->getCourse();
    $s = $c->toArray()['field_post_survey'] ?? [];
    $fs = $c->get('field_post_survey')->referencedEntities();
    $ret = reset($fs);

    if (!$ret && count($s)) {
      // DELETED
      return 0;
    }
    return $ret;
  }

  public function isAttendPass() {
    return $this->get('field_attend_pass')->value === '1';
  }

  public function isPublished() {
    // Shall this need to check product status also?
    /*
    $courses = $user_course->get('course')->referencedEntities();
    $courses = array_filter($courses, function($i) {
    return $i->get('status')->getValue()[0]['value'] ?? FALSE;
    });
     */
    return $this->get('status')->getValue()[0]['value'] ?? FALSE;
  }

  public function getSurveyName() {
    if ($this->isWebformDeleted()) {
      $survey_title = t('DELETED');
    }
    else {
      $f = $this->getSurveyWebform();
      $survey_title = $f ? $f->label() : '';
    }
    return $survey_title . '';
  }

}
