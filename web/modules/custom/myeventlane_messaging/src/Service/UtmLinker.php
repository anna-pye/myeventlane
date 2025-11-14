<?php

namespace Drupal\myeventlane_messaging\Service;

/**
 * Naive HTML link UTM tagger. Covers <a href="..."> links.
 */
final class UtmLinker {
  public function apply(string $html, array $params): string {
    if (!$params) return $html;
    $q = http_build_query($params);
    return preg_replace_callback('#href="([^"]+)"#i', function ($m) use ($q) {
      $url = $m[1];
      if (str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) return $m[0];
      $sep = (str_contains($url, '?') ? '&' : '?');
      return 'href="' . $url . $sep . $q . '"';
    }, $html) ?? $html;
  }
}
