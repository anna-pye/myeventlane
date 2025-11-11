<?php

namespace Drupal\eca_project_browser\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\eca\Event\TriggerEvent;

/**
 * Implements project_browser hooks for the ECA Project Browser submodule.
 */
class ProjectBrowserHooks {

  /**
   * Constructs a new ProjectBrowserHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * Implements hook_project_browser_source_info_alter().
   */
  #[Hook('project_browser_source_info_alter', order: Order::Last)]
  public function projectBrowserSourceInfoAlter(array &$definitions): void {
    $this->triggerEvent->dispatchFromPlugin('project_browser:source_info_alter', $definitions);
  }

}
