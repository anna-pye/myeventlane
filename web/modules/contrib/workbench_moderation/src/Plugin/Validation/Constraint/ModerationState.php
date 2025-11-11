<?php

namespace Drupal\workbench_moderation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Dynamic Entity Reference valid reference constraint.
 *
 * Verifies that nodes have a valid moderation state.
 *
 * @Constraint(
 *   id = "ModerationState",
 *   label = @Translation("Valid moderation state", context = "Validation")
 * )
 */
class ModerationState extends Constraint {

  /**
   * {@inheritdoc}
   */
  public $message = 'Invalid state transition from %from to %to';

  /**
   * {@inheritdoc}
   */
  public $accessDeniedMessage = 'You do not have access to transition from %from to %to';

}
