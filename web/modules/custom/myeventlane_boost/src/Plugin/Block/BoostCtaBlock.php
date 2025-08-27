<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;

/**
 * @Block(
 *   id = "myeventlane_boost_cta",
 *   admin_label = @Translation("Boost CTA (MyEventLane)")
 * )
 */
final class BoostCtaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountInterface $account
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('current_user'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'event') {
      return [];
    }

    $is_owner = ((int) $node->getOwnerId() === (int) $this->account->id());
    $can_purchase = $this->account->hasPermission('purchase boost for events');
    $can_admin = $node->access('update', $this->account);
    if (!($is_owner || $can_purchase || $can_admin)) {
      return [];
    }

    $promoted = (bool) $node->get('field_promoted')->value;
    $expires_val = $node->get('field_promo_expires')->value;

    $expires_ts = 0;
    if ($expires_val) {
      $expires_ts = (new \DateTimeImmutable($expires_val, new \DateTimeZone('UTC')))->getTimestamp();
    }
    $is_boosted = $promoted && ($expires_ts > \Drupal::time()->getRequestTime());

    $cta_link = [];
    if (!$is_boosted) {
      $cta_url = \Drupal\Core\Url::fromRoute('myeventlane_boost.boost_page', ['node' => $node->id()]);
      $cta_link = \Drupal\Core\Link::fromTextAndUrl($this->t('Boost your event'), $cta_url)->toRenderable();
      $cta_link['#attributes']['class'][] = 'button';
      $cta_link['#attributes']['class'][] = 'button--primary';
    }

    return [
      '#theme' => 'boost_cta',
      '#event' => $node,
      '#is_boosted' => $is_boosted,
      '#expires' => $expires_val,
      '#cta' => $cta_link,
      '#attached' => ['library' => ['myeventlane_boost/boost']],
      '#cache' => [
        'contexts' => ['user', 'route'],
        'tags' => $node->getCacheTags(),
        'max-age' => 0,
      ],
    ];
  }

}
