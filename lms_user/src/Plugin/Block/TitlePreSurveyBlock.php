<?php

namespace Drupal\lms_user\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Title Pre-Survey'.
 *
 * @Block(
 *   id = "title_pre_survey_block",
 *   admin_label = @Translation("Title Pre-Survey Block"),
 * )
 */
class TitlePreSurveyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    /** @var \Drupal\lms_user\UserManagerInterface $user_manager */
    $user_manager = \Drupal::service('lms_user.manager');
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $user_class = \Drupal::request()->get('user_class');
    if (!empty($user_class)) {
      $uid = $user_class->get('uid')->getString();
      $user = \Drupal::currentUser();
      if ($uid != $user->id()) {
        return [
          '#markup' => NULL,
        ];
      }
      $class = $user_manager->getClassFromUserClass($user_class);
      $class = $class->hasTranslation($langcode) ? $class->getTranslation($langcode) : $class;
      $date = $user_manager->getClassEndDateRangeFormat($class);
      $title = $this->t('Pre-questionnaire');
      $markup = "<div class='custom-title-pre-survey-block'>$title</div>";
      $markup .= '<div class="custom-pre-survey-info">';
      $markup .= "<div class='custom-message-pre-survey-block'>";
      $markup .= "<div>" . $class->label() . "</div>";
      $markup .= "<div>$date</div>";
      $markup .= "</div>";
      $markup .= "</div>";

      return [
        '#markup' => $markup,
      ];
    }
    else {
      return [
        '#markup' => NULL,
      ];
    }
  }

}
