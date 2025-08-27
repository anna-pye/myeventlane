<?php

namespace Drupal\myeventlane_analytics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an "Event Analytics Chart" block.
 *
 * @Block(
 *   id = "myeventlane_event_analytics_chart",
 *   admin_label = @Translation("Event Analytics Chart")
 * )
 */
final class EventAnalyticsChartBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
    );
  }

  public function build(): array {
    // 1) Configure your view + display IDs here.
    $view_id = 'event_analytics';     // <- ensure this matches the machine name.
    $display_id = 'block_1';          // <- ensure this matches the display ID.

    // 2) Load the View executable safely.
    $view = Views::getView($view_id);
    if (!$view) {
      \Drupal::logger('myeventlane_analytics')->error('View "@id" not found for EventAnalyticsChartBlock.', ['@id' => $view_id]);
      return [
        '#markup' => '',
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    // 3) Select the display and guard invalid IDs.
    if (!$view->setDisplay($display_id)) {
      \Drupal::logger('myeventlane_analytics')->error('Display "@display" not found on view "@view".', [
        '@display' => $display_id,
        '@view' => $view_id,
      ]);
      return [
        '#markup' => '',
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    // 4) Optional: pass current node ID as a contextual filter, if your view expects it.
    //    This assumes the display is configured with a contextual filter (e.g., Node ID).
    $nid = NULL;
    $node_param = $this->routeMatch->getParameter('node');
    if (is_object($node_param) && method_exists($node_param, 'id')) {
      $nid = $node_param->id();
    }
    elseif (is_numeric($node_param)) {
      $nid = (int) $node_param;
    }

    if ($nid) {
      $view->setArguments([$nid]);
    }

    // 5) Execute and render.
    $build = $view->render();

    // 6) Cache metadata: vary by route so the chart follows the page/node.
    //    Add view’s own cacheability + route context to avoid stale analytics.
    $build['#cache']['contexts'][] = 'route';
    // If analytics data changes often, consider:
    // $build['#cache']['max-age'] = 300; // 5 minutes, tune to taste.

    return $build;
  }

}
