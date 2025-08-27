<?php

/**
 * @file
 * Contains post-update hooks for Automatic Updates Extensions.
 */

declare(strict_types=1);

/**
 * Rebuilds the container to account for Package Manager moving to core.
 */
function automatic_updates_extensions_post_update_rebuild_for_core_package_manager(): void {
  // Intentionally empty to force a container rebuild.
}
