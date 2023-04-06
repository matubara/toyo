<?php

namespace Drupal\lms_user\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\Entity\User;

/**
 * Defines the User Class entity.
 *
 * @ContentEntityType(
 *     id="user_class",
 *     label=@Translation("User Class"),
 *     label_collection=@Translation("Class/pre-questionnaire management"),
 *     handlers={
 *         "views_data": "Drupal\views\EntityViewsData",
 *         "view_builder": "Drupal\Core\Entity\EntityViewBuilder",
 *         "list_builder": "Drupal\lms_user\UserClassListBuilder",
 *         "form": {
 *             "default": "Drupal\lms_user\Form\UserClassForm",
 *             "add": "Drupal\lms_user\Form\UserClassForm",
 *             "edit": "Drupal\lms_user\Form\UserClassForm",
 *             "delete": "Drupal\lms_user\Form\UserClassDeleteForm",
 *             "delete-multiple-confirm": "Drupal\Core\Entity\Form\DeleteMultipleForm",
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
 *     base_table="user_class",
 *     data_table="user_class_field_data",
 *     translatable=TRUE,
 *     admin_permission="administer user_class entity",
 *     entity_keys={
 *         "id": "user_class_id",
 *         "label": "name",
 *         "uuid": "uuid",
 *         "uid": "uid",
 *         "langcode": "langcode",
 *     },
 *     links={
 *         "collection": "/admin/commerce/lms/user-class",
 *         "canonical": "/admin/commerce/lms/user-class/{user_class}",
 *         "add-form": "/admin/commerce/lms/user-class/add",
 *         "edit-form": "/admin/commerce/lms/user-class/{user_class}/edit",
 *         "delete-form": "/admin/commerce/lms/user-class/{user_class}/delete",
 *         "delete-multiple-form": "/admin/commerce/lms/user-class/delete-multiple",
 *     },
 *     field_ui_base_route="entity.user_class.collection",
 * )
 */
class UserClass extends ContentEntityBase implements UserClassInterface {
  private $dt;

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

    $fields['class'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class'))
      ->setDescription(t('Class node.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'node')
      ->setSetting('handler_settings', [
        'target_bundles' => ['class' => 'class'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['user_courses'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Courses'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDescription(t('User course entities that user applied.'))
      ->setSetting('target_type', 'user_course')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
      ])
      ->setDisplayOptions('view', ['type' => 'string', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['pre_survey'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Pre Survey'))
      ->setDescription(t('The webform submission per survey.'))
      ->setSetting('target_type', 'webform_submission')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -9,
      ])
      ->setDisplayOptions('view', ['type' => 'string', 'weight' => -9])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the UserClass was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the node was last edited.'));

    $fields['data'] = BaseFieldDefinition::create('json');

    return $fields;
  }

  public function getCustom($key) {
    $this->getData();

    return $this->dt[$key] ?? -1;
  }

  public function getData() {
    if (isset($this->dt)) {
      return $this->dt;
    }

    $this->dt = $this->get('data')->getValue()[0]['value'] ?? '{}';

    try {
      $this->dt = json_decode($this->dt, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (Exception $e) {
      $this->dt = [];
    }

    return $this->dt;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    $uid = $this->getEntityKey('uid');

    return $uid ? User::load($uid) : NULL;
  }

  public function setCustom($key, $value) {
    $this->getData();
    $this->dt[$key] = $value;
    $this->setData($this->dt);
  }

  public function setData($dt) {
    $this->set('data', json_encode($dt));
  }

  public function getClass() {
    $cs = $this->get('class')->referencedEntities();
    return reset($cs);
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

    // Other wise load from the class
    $c = $this->getClass();
    $s = $c->toArray()['field_pre_survey'] ?? [];
    $fs = $c->get('field_pre_survey')->referencedEntities();
    $ret = reset($fs);

    if (!$ret && count($s)) {
      // DELETED
      return 0;
    }
    return $ret;
  }

  public function getSurvey() {
    $items = $this->get('pre_survey')->referencedEntities();
    return reset($items);
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
