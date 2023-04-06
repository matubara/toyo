<?php

namespace Drupal\lms_user\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Represents a user_course entity.
 */
interface UserCourseInterface extends ContentEntityInterface {

  /**
   * Returns user.
   *
   * @return \Drupal\user\UserInterface
   */
  public function getUser();

}
