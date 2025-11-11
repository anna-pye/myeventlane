<?php

namespace Drupal\social_auth_apple\Plugin\Network;

use Drupal\social_auth\Plugin\Network\NetworkBase;
use Drupal\social_auth\Settings\SettingsInterface;
use Drupal\social_auth_apple\Settings\AppleAuthSettingsInterface;

/**
 * Defines a Network Plugin for Social Auth Apple.
 *
 * @Network(
 *   id = "social_auth_apple",
 *   short_name = "apple",
 *   social_network = "Apple",
 *   img_path = "img/apple_logo.svg",
 *   type = "social_auth",
 *   class_name = "\League\OAuth2\Client\Provider\Apple",
 *   auth_manager = "\Drupal\social_auth_apple\AppleAuthManager",
 *   routes = {
 *     "redirect": "social_auth.network.redirect",
 *     "callback": "social_auth_apple.callback",
 *     "settings_form": "social_auth_apple.settings_form",
 *   },
 *   handlers = {
 *     "settings": {
 *       "class": "\Drupal\social_auth_apple\Settings\AppleAuthSettings",
 *       "config_id": "social_auth_apple.settings",
 *     },
 *   },
 * )
 */
class AppleAuth extends NetworkBase {

  /**
   * {@inheritdoc}
   */
  protected function getExtraSdkSettings(): array {
    assert($this->settings instanceof AppleAuthSettingsInterface);
    return [
      'teamId' => $this->settings->getTeamId(),
      'keyFileId' => $this->settings->getKeyFileId(),
      'keyFilePath' => $this->settings->getKeyFilePath(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateConfig(SettingsInterface $settings): bool {
    assert($settings instanceof AppleAuthSettingsInterface);
    $constraints = [
      [[$settings, 'getClientId'], 'Define "Client ID" in module settings.'],
      [[$settings, 'getTeamId'], 'Define "Team ID" in module settings.'],
      [[$settings, 'getKeyFileId'], 'Define "Key File ID" in module settings.'],
      [[$settings, 'getKeyFilePath'], 'Define "Key File Path" in module settings.'],
    ];
    foreach ($constraints as [$getter, $message]) {
      if (!$getter()) {
        $this->loggerFactory
          ->get($this->pluginId)
          ->error($message);
        return FALSE;
      }
    }
    return TRUE;
  }

}
