<?php

namespace Drupal\commerce_stripe_webhook_event\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\commerce_stripe_webhook_event\WebhookEvent;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the job type for webhook events.
 *
 * @AdvancedQueueJobType(
 *   id = "commerce_stripe_webhook_event",
 *   label = @Translation("Process Stripe webhook events"),
 * )
 *
 * @phpstan-consistent-constructor
 */
class WebhookEventJob extends JobTypeBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   *
   * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    try {
      WebhookEvent::process($job->getPayload());
      return JobResult::success();
    }
    catch (\Throwable $throwable) {
      return JobResult::failure($throwable->getMessage());
    }
  }

}
