<?php

declare(strict_types=1);

namespace Drupal\project_browser_test;

use Drupal\Core\Site\Settings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Alters site settings so core's Package Manager can be installed in the UI.
 */
final class SettingsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => 'makePackageManagerInstallable',
    ];
  }

  /**
   * Adjusts settings so Package Manager can be installed.
   */
  public function makePackageManagerInstallable(): void {
    $settings = Settings::getAll();
    $settings['testing_package_manager'] = TRUE;
    new Settings($settings);
  }

}
