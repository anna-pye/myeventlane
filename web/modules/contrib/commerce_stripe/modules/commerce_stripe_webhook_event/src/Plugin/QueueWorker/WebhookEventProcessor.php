<?php

namespace Drupal\commerce_stripe_webhook_event\Plugin\QueueWorker;

use Drupal\commerce_stripe_webhook_event\WebhookEvent;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker for processing webhook events.
 *
 * @QueueWorker(
 *  id = "commerce_stripe_webhook_event_processor",
 *  title = @Translation("Commerce Stripe Webhook Event Processor"),
 *  cron = {"time" = 60}
 * )
 *
 * @phpstan-consistent-constructor
 */
class WebhookEventProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   *
   *  phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    try {
      WebhookEvent::process($data);
    }
    catch (\Throwable $throwable) {

    }
  }

}
