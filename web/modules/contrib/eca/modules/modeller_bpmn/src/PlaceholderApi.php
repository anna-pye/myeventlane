<?php
// phpcs:ignoreFile

namespace Drupal\modeler_api {

  $file = __DIR__ . '/../../../../modeler_api/src/Api.php';

  if (file_exists($file)) {
    include_once $file;
  }
  else {
    class Api {}
  }

}

namespace Drupal\eca_modeller_bpmn {

  use Drupal\modeler_api\Api;

  /**
   * Placeholder class for the upgrade from ECA 2 to 3.
   */
  class PlaceholderApi extends Api {}

}
