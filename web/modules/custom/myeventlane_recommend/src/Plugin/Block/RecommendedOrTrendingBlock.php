<?php

namespace Drupal\myeventlane_recommend\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\views\Views;

/**
 * @Block(
 *   id = "myeventlane_recommend_recommended_or_trending",
 *   admin_label = @Translation("Recommended or Trending"),
 *   category = @Translation("MyEventLane")
 * )
 */
final class RecommendedOrTrendingBlock extends BlockBase {

  public function build(): array {
    $build = [
      '#cache' => [
        'contexts' => ['user', 'url.path', 'timezone'],
        'tags' => ['node_list'],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    // Try recommended first.
    if ($rec = $this->renderView('recommended_for_you', 'block_1')) {
      // If the view produced at least one row, return it.
      if ($this->hasResults($rec)) {
        $build['recs'] = $rec;
        return $build;
      }
    }

    // Fallback: trending.
    if ($trend = $this->renderView('trending_events', 'block_1')) {
      $build['trending'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['mel-recommend']],
        'title' => ['#markup' => '<h2 class="mel-recommend__title">Trending near you</h2>'],
        'view' => $trend,
      ];
      return $build;
    }

    // Nothing to show.
    return ['#markup' => ''];
  }

  private function renderView(string $id, string $display): ?array {
    $view = Views::getView($id);
    if (!$view) {
      return NULL;
    }
    $view->setDisplay($display);
    $exposed = []; // if you add exposed filters later
    return $view->buildRenderable($display, $exposed);
  }

  private function hasResults(array $render): bool {
    // Views stores row count in #total_rows when built renderably.
    return !empty($render['#total_rows']);
  }
}
