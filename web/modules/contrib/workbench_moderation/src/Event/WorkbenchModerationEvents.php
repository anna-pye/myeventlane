<?php

namespace Drupal\workbench_moderation\Event;

/**
 * Defines a class for moderation events.
 */
final class WorkbenchModerationEvents {

  /**
   * This event is fired everytime a state is changed.
   *
   * @Event
   */
  const STATE_TRANSITION = 'workbench_moderation.state_transition';

}
