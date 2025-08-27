<?php

namespace Drupal\myeventlane_recommend\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Manages user affinity weights for taxonomy terms.
 */
final class AffinityManager {

  public function __construct(
    private Connection $db,
    private TimeInterface $time,
  ) {}

  /**
   * Increment affinity weight(s) for a user for given term IDs.
   *
   * @param int $uid
   * @param int[] $tids
   * @param float $delta
   */
  public function bump(int $uid, array $tids, float $delta = 1.0): void {
    if ($uid <= 0 || empty($tids)) {
      return;
    }
    $now = $this->time->getRequestTime();
    $tids = array_values(array_unique(array_map('intval', $tids)));

    foreach ($tids as $tid) {
      $this->db->merge('myeventlane_user_affinity')
        ->key(['uid' => $uid, 'tid' => $tid])
        ->fields(['weight' => $delta, 'changed' => $now])
        ->expression('weight', 'COALESCE(weight, 0) + :inc', [':inc' => $delta])
        ->execute();
    }
  }

  /**
   * Optional: decay all affinities by a factor (e.g. 0.98).
   */
  public function decay(float $factor = 0.98): void {
    $factor = max(0.0, min(1.0, $factor));
    $this->db->update('myeventlane_user_affinity')
      ->expression('weight', 'weight * :f', [':f' => $factor])
      ->fields(['changed' => $this->time->getRequestTime()])
      ->execute();
  }
}
