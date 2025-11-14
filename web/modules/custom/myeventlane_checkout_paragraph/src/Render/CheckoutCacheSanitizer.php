<?php

namespace Drupal\myeventlane_checkout_paragraph\Render;

use Drupal\Core\Security\TrustedCallbackInterface;

final class CheckoutCacheSanitizer implements TrustedCallbackInterface {

  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

  public static function preRender(array $element): array {
    // Move any stray 'max_age', 'contexts', 'tags' under a single '#cache'.
    // Also flatten nested '#cache' => ['#cache' => …].
    self::normalize($element);
    return $element;
  }

  private static function normalize(array &$a, int $depth = 0): void {
    if (!is_array($a) || $depth > 40) { return; }

    // Flatten accidentally nested #cache.
    if (isset($a['#cache']['#cache']) && is_array($a['#cache']['#cache'])) {
      self::mergeCache($a['#cache'], $a['#cache']['#cache']);
      unset($a['#cache']['#cache']);
    }

    // Hoist any top-level cache keys.
    foreach (['max_age','contexts','tags'] as $k) {
      if (array_key_exists($k, $a)) {
        $a['#cache'] = isset($a['#cache']) && is_array($a['#cache']) ? $a['#cache'] : [];
        if ($k === 'max_age') {
          $a['#cache']['max_age'] = (int) $a[$k];
        } elseif ($k === 'contexts') {
          $a['#cache']['contexts'] = self::uniqList(array_merge((array) ($a['#cache']['contexts'] ?? []), (array) $a[$k]));
        } else { // tags
          $a['#cache']['tags'] = self::uniqList(array_merge((array) ($a['#cache']['tags'] ?? []), (array) $a[$k]));
        }
        unset($a[$k]);
      }
    }

    // Coerce types again, just in case.
    if (isset($a['#cache'])) {
      if (isset($a['#cache']['max_age'])) {
        $a['#cache']['max_age'] = (int) $a['#cache']['max_age'];
      }
      foreach (['contexts','tags'] as $lk) {
        if (isset($a['#cache'][$lk])) {
          $a['#cache'][$lk] = self::uniqList((array) $a['#cache'][$lk]);
        }
      }
    }

    // Recurse into children (non-# keys).
    foreach ($a as $key => &$child) {
      if ($key !== '#cache' && is_array($child) && !str_starts_with((string) $key, '#')) {
        self::normalize($child, $depth + 1);
      }
    }
    unset($child);
  }

  private static function mergeCache(array &$dst, array $src): void {
    if (array_key_exists('max_age', $src)) {
      $dst['max_age'] = isset($dst['max_age'])
        ? min((int) $dst['max_age'], (int) $src['max_age'])
        : (int) $src['max_age'];
    }
    foreach (['contexts','tags'] as $lk) {
      if (isset($src[$lk])) {
        $dst[$lk] = self::uniqList(array_merge((array) ($dst[$lk] ?? []), (array) $src[$lk]));
      }
    }
  }

  private static function uniqList(array $list): array {
    $out = [];
    foreach ($list as $v) {
      if (is_scalar($v)) {
        $s = trim((string) $v);
        if ($s !== '') { $out[$s] = TRUE; }
      }
    }
    return array_keys($out);
  }
}