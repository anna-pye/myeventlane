<?php

namespace Drupal\myeventlane_analytics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\views\Views;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for the vendor event analytics React chart.
 *
 * @Block(
 *   id = "vendor_event_analytics_chart_block",
 *   admin_label = @Translation("Vendor Event Analytics Chart Block")
 * )
 */
class VendorEventAnalyticsChartBlock extends BlockBase implements \Drupal\Core\Plugin\ContainerFactoryPluginInterface {

  protected $currentUser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  public function build() {
    $view = \Drupal\views\Views::getView('vendor_event_analytics');
    $view->setDisplay('default');
    $view->execute();
    $data = [];
    foreach ($view->result as $row) {
      $data[] = [
        'event' => isset($row->_entity) ? $row->_entity->label() : '',
        // Remove pseudo field references for now
        'tickets' => 0,
        'rsvps' => 0,
      ];
    }
    return [
      '#theme' => 'block__vendor_event_analytics_chart',
      '#attached' => [
        'library' => ['myeventlane_analytics/vendor_event_analytics_chart'],
        'drupalSettings' => [
          'myeventlane_analytics' => [
            'chartData' => $data,
          ],
        ],
      ],
    ];
  }
}
