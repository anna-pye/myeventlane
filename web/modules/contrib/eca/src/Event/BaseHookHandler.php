<?php

namespace Drupal\eca\Event;

/**
 * Base class for all HookHandlers of sub-modules.
 *
 * @deprecated in eca:3.0.0 and is removed from eca:3.1.0. Procedural hooks
 * should be converted into classes.
 *
 * @see https://www.drupal.org/project/eca/issues/3481999
 */
abstract class BaseHookHandler {

  /**
   * The service for triggering ECA-related events.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * The HookHandler constructor.
   *
   * @param \Drupal\eca\Event\TriggerEvent $trigger_event
   *   The service for triggering ECA-related events.
   */
  public function __construct(TriggerEvent $trigger_event) {
    $this->triggerEvent = $trigger_event;
  }

}
