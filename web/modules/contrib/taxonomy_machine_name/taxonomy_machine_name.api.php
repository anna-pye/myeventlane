<?php

/**
 * @file
 * Hooks provided by the taxonomy_machine_name.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow to override default machine name generation.
 *
 * @param string $machine_name
 *   Machine name.
 * @param string $name
 *   Basic name.
 * @param bool $force
 *   Force new machine name.
 */
function hook_taxonomy_machine_name_clean_name(&$machine_name, $name, $force) {
  if ($force) {
    $machine_name = strtolower(str_replace('_', '-', $name));
    $machine_name = preg_replace('/[^a-z0-9\_]/i', '_', $machine_name);

    $machine_name = trim($machine_name, '_');
  }
}

/**
 * @} End of "addtogroup hooks".
 */
