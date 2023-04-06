<?php

namespace Drupal\lms_user\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Represents a user_course entity.
 */
interface UserClassInterface extends ContentEntityInterface {

  /**
   * Returns user.
   *
   * @return \Drupal\user\UserInterface
   */
  public function getUser();

}
