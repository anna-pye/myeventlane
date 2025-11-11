<?php

namespace Drupal\commerce_stripe\EventSubscriber;

use Drupal\commerce\Utility\Error;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to order events.
 */
class OrderSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new OrderPaymentIntentSubscriber object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    protected LoggerInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.post_transition' => 'onOrderPlaced',
    ];
  }

  /**
   * Logs the placement of the order to the commerce log.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event.
   */
  public function onOrderPlaced(WorkflowTransitionEvent $event): void {
    try {
      if (!$this->moduleHandler->moduleExists('commerce_log')) {
        return;
      }
      // Add a log to the order activity stream about the order placed source.
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $event->getEntity();
      if ($order_placed_source = $order->getData('order_placed_source')) {
        $order_placed_source_type = $order_placed_source['type'] ?? NULL;
        if ($order_placed_source_type === 'notify') {
          $commerce_stripe_webhook_event_data = $order_placed_source['commerce_stripe_webhook_event'] ?? NULL;
          $webhook_event_type = $commerce_stripe_webhook_event_data['type'] ?? NULL;
          /** @var \Drupal\commerce_log\LogStorageInterface $log_storage */
          $log_storage = $this->entityTypeManager->getStorage('commerce_log');
          $log_storage->generate($order, 'commerce_stripe_order_placed_notify', [
            'webhook_event_type' => $webhook_event_type,
          ])->save();
        }
      }
    }
    catch (\Throwable $throwable) {
      Error::logException($this->logger, $throwable);
    }
  }

}
