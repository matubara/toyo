<?php

namespace Drupal\lms_user\TwigExtension;

use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;

/**
 * Provides Price-specific Twig extensions.
 */
class WebformSubmittedExtension extends \Twig_Extension {

  /**
   * In this function we can declare the extension function.
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('is_submitted_pre_survey', [
        $this,
        'isSubmittedPreSurvey',
      ]),
    ];
  }

  /**
   * @inheritdoc
   */
  public function getName() {
    return 'webform_submitted.twig_extension';
  }

  /**
   * @param \Drupal\node\Entity\Node $class
   * @param \Drupal\user\UserInterface $user
   *
   * @return false|true
   */
  public static function isSubmittedPreSurvey(Node $class, UserInterface $user) {
    /** @var \Drupal\lms_user\UserManagerInterface $userManger */
    $userManger = \Drupal::service('lms_user.manager');
    return $userManger->isSubmittedPreSurvey($class, $user);
  }

}
