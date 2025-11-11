<?php

namespace Drupal\social_auth_apple\Settings;

use Drupal\social_auth\Settings\SettingsBase;

/**
 * Defines methods to get Social Auth Apple settings.
 */
class AppleAuthSettings extends SettingsBase implements AppleAuthSettingsInterface {

  /**
   * The team ID.
   */
  protected ?string $teamId = NULL;

  /**
   * The key file ID.
   */
  protected ?string $keyFileId = NULL;

  /**
   * The key file path.
   */
  protected ?string $keyFilePath = NULL;

  /**
   * {@inheritdoc}
   */
  public function getTeamId(): ?string {
    if (!$this->teamId) {
      $this->teamId = $this->config->get('team_id');
    }
    return $this->teamId;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyFileId(): ?string {
    if (!$this->keyFileId) {
      $this->keyFileId = $this->config->get('key_file_id');
    }
    return $this->keyFileId;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyFilePath(): ?string {
    if (!$this->keyFilePath) {
      $this->keyFilePath = $this->config->get('key_file_path');
    }
    return $this->keyFilePath;
  }

}
