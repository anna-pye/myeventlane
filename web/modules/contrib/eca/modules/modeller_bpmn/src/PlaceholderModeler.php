<?php
// phpcs:ignoreFile

namespace Drupal\modeler_api\Plugin {

  $file = __DIR__ . '/../../../../modeler_api/src/Plugin/ModelerPluginManager.php';

  if (file_exists($file)) {
    include_once $file;
  }
  else {
    class ModelerPluginManager {}
  }

}

namespace Drupal\eca_modeller_bpmn {

  use Drupal\modeler_api\Plugin\ModelerPluginManager;

  /**
   * Placeholder class for the upgrade from ECA 2 to 3.
   */
  class PlaceholderModeler extends ModelerPluginManager {

  }

}
