<?php
// phpcs:ignoreFile

namespace Drupal\modeler_api\Plugin {

  $file = __DIR__ . '/../../../../modeler_api/src/Plugin/ModelOwnerPluginManager.php';

  if (file_exists($file)) {
    include_once $file;
  }
  else {
    class ModelOwnerPluginManager {}
  }

}

namespace Drupal\eca_modeller_bpmn {

  use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;

  /**
   * Placeholder class for the upgrade from ECA 2 to 3.
   */
  class PlaceholderOwner extends ModelOwnerPluginManager {}

}
