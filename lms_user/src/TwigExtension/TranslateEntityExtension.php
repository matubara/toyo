<?php

namespace Drupal\lms_user\TwigExtension;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * Provides Price-specific Twig extensions.
 */
class TranslateEntityExtension extends \Twig_Extension {

  /**
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityInterface|mixed
   */
  public static function getTranslateEntity(ContentEntityBase $entity) {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    return $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;
  }

  /**
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|\Drupal\Core\Entity\EntityInterface|mixed
   */
  public static function getDateFormat($date_range) {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($date_range) {
      $date_range_values = $date_range->getValue();
      if ($date_range_values) {
        $user_manager = \Drupal::service('lms_user.manager');
        $start_date_with_timezone = $user_manager->getTimeStampWithTimezone($date_range_values[0]['value'], FALSE);
        $end_date_with_timezone = $user_manager->getTimeStampWithTimezone($date_range_values[0]['end_value'], FALSE);
        $start_date_data = $user_manager->getDateData($start_date_with_timezone);
        $end_date_data = $user_manager->getDateData($end_date_with_timezone);
        $response['start_date'] = $user_manager->getDateFormatResponse($langcode, $start_date_data);
        $response['end_date'] = $user_manager->getDateFormatResponse($langcode, $end_date_data);
        return $response;
      }
    }
    return NULL;

  }

  /**
   * In this function we can declare the extension function.
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('translate_entity', [
        $this,
        'getTranslateEntity',
      ]),
      new \Twig_SimpleFunction('get_date_format', [
        $this,
        'getDateFormat',
      ]),
    ];
  }

  /**
   * @inheritdoc
   */
  public function getName() {
    return 'translate_entity.twig_extension';
  }

}
