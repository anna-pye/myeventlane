<?php

namespace Drupal\myeventlane_views\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * @ViewsAccess(
 *   id = "vendor_store_access",
 *   title = @Translation("Vendor or Admin Access")
 * )
 */
class VendorStoreAccess extends AccessPluginBase {

  public function access(AccountInterface $account) {
    \Drupal::logger('access_debug')->notice('👤 Checking access for UID @uid with roles: @roles', [
      '@uid' => $account->id(),
      '@roles' => implode(', ', $account->getRoles()),
    ]);

    if ((int) $account->id() === 1) {
      \Drupal::logger('access_debug')->notice('✅ Access granted: UID 1');
      return TRUE;
    }

    if ($account->hasPermission('administer nodes') || $account->hasPermission('access vendor attendee data')) {
      \Drupal::logger('access_debug')->notice('✅ Access granted via permission.');
      return TRUE;
    }

    $store_ids = \Drupal::entityTypeManager()
      ->getStorage('commerce_store')
      ->getQuery()
      ->condition('uid', $account->id())
      ->accessCheck(TRUE)
      ->execute();

    \Drupal::logger('access_debug')->notice('Found @count store(s) for UID @uid', [
      '@count' => count($store_ids),
      '@uid' => $account->id(),
    ]);

    return !empty($store_ids);
  }

  public function alterRouteDefinition(Route $route) {
    // Required for Drupal 11
  }

  public function summaryTitle() {
    return $this->t('Vendor store owner or admin');
  }

  public function get_access_callback() {
    return [$this, 'access'];
  }
}
