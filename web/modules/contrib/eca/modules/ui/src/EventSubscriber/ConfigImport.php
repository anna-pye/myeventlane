<?php

namespace Drupal\eca_ui\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\eca\Entity\Eca;
use Drupal\eca_ui\V3Migrate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the config import event to update to v3 format if necessary.
 *
 * @deprecated in eca:3.0.0 and is removed from eca:3.1.0. Raw model data is now
 *  owned by the Modeler API and will be stored in third-party settings or in
 *  their own config entity.
 *
* @see https://www.drupal.org/project/eca/issues/3517784
 */
final class ConfigImport implements EventSubscriberInterface {

  /**
   * Constructs a ConfigImport object.
   */
  public function __construct(
    protected V3Migrate $ecaUiV3Migrate,
  ) {}

  /**
   * Config import event handler.
   */
  public function onConfigImport(ConfigImporterEvent $event): void {
    $list = $event->getChangelist();
    foreach ($list['create'] as $id) {
      if (str_starts_with($id, 'eca.eca.')) {
        $id = substr($id, 8);
        if ($eca = Eca::load($id)) {
          $this->ecaUiV3Migrate->migrateEca($eca, TRUE);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::IMPORT => ['onConfigImport'],
    ];
  }

}
