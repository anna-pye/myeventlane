<?php

namespace Drupal\eca_ui\Drush\Commands;

use Drupal\eca_ui\V3Migrate;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA UI Drush command file to upgrade older models to version 3.
 *
 * @deprecated in eca:3.0.0 and is removed from eca:3.1.0. Raw model data is now
 * owned by the Modeler API and will be stored in third-party settings or in
 * their own config entity.
 *
 * @see https://www.drupal.org/project/eca/issues/3517784
 */
final class UpgradeTo3Commands extends DrushCommands {

  /**
   * Constructs an UpgradeTo3Commands object.
   */
  public function __construct(
    protected readonly V3Migrate $v3Migrate,
  ) {
    parent::__construct();
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\eca_ui\Drush\Commands\UpgradeTo3Commands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): UpgradeTo3Commands {
    return new UpgradeTo3Commands(
      $container->get('eca_ui.v3.migrate'),
    );
  }

  /**
   * Rebuild the state of subscribed events.
   */
  #[Command(name: 'eca:v3:migrate', aliases: [])]
  #[Usage(name: 'eca:v3:migrate', description: 'Upgrade older ECA models to version 3.')]
  public function rebuildSubscribedEvents(): void {
    $this->v3Migrate->migrateAll();
  }

}
