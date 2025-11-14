<?php

namespace Drupal\myeventlane_messaging\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;

final class UnsubscribeController extends ControllerBase {
  public function unsubscribe($uid, $ts, $h) {
    $user = $this->entityTypeManager()->getStorage('user')->load($uid);
    if (!$user instanceof UserInterface) {
      return ['#markup' => $this->t('Invalid link.')];
    }
    $secret = \Drupal::config('system.site')->get('hash_salt');
    $calc = hash('sha256', $uid.$ts.$secret);
    if (!hash_equals($calc, $h)) {
      return ['#markup' => $this->t('Invalid signature.')];
    }

    // Store pref in user_data to avoid schema work here.
    \Drupal::service('user.data')->set('myeventlane_messaging', $uid, 'marketing_opt_out', TRUE);

    return ['#markup' => $this->t('You have been unsubscribed from marketing emails. You may still receive transactional emails about your orders and RSVPs.')];
  }

  public static function buildUnsubUrl(UserInterface $user): string {
    $uid = (int) $user->id();
    $ts = \Drupal::time()->getCurrentTime();
    $secret = \Drupal::config('system.site')->get('hash_salt');
    $h = hash('sha256', $uid.$ts.$secret);
    return \Drupal\Core\Url::fromRoute('myeventlane_messaging.unsubscribe', ['uid'=>$uid,'ts'=>$ts,'h'=>$h], ['absolute'=>TRUE])->toString(TRUE)->getGeneratedUrl();
  }
}
