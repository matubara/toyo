<?php

namespace Drupal\lms_user\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the submitted value is a unique integer.
 *
 * @Constraint(
 *   id = "BuniqueField",
 *   label = @Translation("Unique Field B", context = "Validation"),
 *   type = "string"
 * )
 */
class BuniqueField extends Constraint {
  public $message = 'A member id %value already exists.';

}
