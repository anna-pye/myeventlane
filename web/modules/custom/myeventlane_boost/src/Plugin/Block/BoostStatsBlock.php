<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "myeventlane_boost_stats_block",
 *   admin_label = @Translation("Boost: Stats"),
 * )
 */
final class BoostStatsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private EntityTypeManagerInterface $etm,
    private TimeInterface $time
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  public function build(): array {
    $now = $this->time->getRequestTime();
    $soon = $now + 48 * 3600;

    $q = fn() => \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type','event')->condition('status',1);

    $active = $q()->condition('field_promoted', 1)->condition('field_promo_expires', $now, '>')->count()->execute();
    $expiring = $q()->condition('field_promoted', 1)->condition('field_promo_expires', $now, '>')->condition('field_promo_expires', $soon, '<=')->count()->execute();

    return [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Active boosts: @n', ['@n' => $active]),
        $this->t('Expiring ≤ 48h: @n', ['@n' => $expiring]),
      ],
      '#cache' => ['max-age' => 300],
    ];
  }
}
