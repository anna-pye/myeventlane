<?php

namespace Drupal\social_auth_apple\Settings;

use Drupal\social_auth\Settings\SettingsInterface;

/**
 * Defines an interface for Social Auth Apple settings.
 */
interface AppleAuthSettingsInterface extends SettingsInterface {

  /**
   * The module config name.
   */
  const CONFIG_NAME = 'social_auth_apple.settings';

  /**
   * Returns the team ID.
   *
   * @return string|null
   *   The team ID.
   */
  public function getTeamId(): ?string;

  /**
   * Returns the key file ID.
   *
   * @return string|null
   *   The key file ID.
   */
  public function getKeyFileId(): ?string;

  /**
   * Returns the key file path.
   *
   * @return string|null
   *   The key file path.
   */
  public function getKeyFilePath(): ?string;

}
