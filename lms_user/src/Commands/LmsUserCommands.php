<?php

namespace Drupal\lms_user\Commands;

use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\lms_user\Commands
 */
class LmsUserCommands extends DrushCommands {

  /**
   * Drush command that clear lms data.
   *
   * @param string $type
   *   Argument with type of data clear.
   *
   * @command lms_user:clear_lms_data
   * @aliases drush-clear_lms_data clear_lms_data
   * @usage lms_user:clear_lms_data type
   */
  public function clear_lms_data(string $type = 'all') {
    $lms_user_manager = \Drupal::service('lms_user.manager');
    $lms_user_manager->clearLMSData($type);
    $this->output()->writeln('Clear data ' . $type . ' completed');
  }

}
