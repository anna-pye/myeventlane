<?php

namespace Drupal\platform_fee\EventSubscriber;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_price\Price;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds a platform fee during the order placement process.
 */
class PlatformFeeSubscriber implements EventSubscriberInterface {

  /**
   * Applies the platform fee during the checkout transition.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   */
  public function applyPlatformFee(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    if (!$order instanceof OrderInterface) {
      return;
    }

    // Prevent double-charging the fee.
    foreach ($order->getAdjustments() as $adjustment) {
      if ($adjustment->getType() === 'fee' && $adjustment->getLabel() === 'Platform Fee') {
        return;
      }
    }

    // Add a fixed $1.50 platform fee using the allowed 'fee' type.
    $order->addAdjustment(new Adjustment([
      'type' => 'fee',
      'label' => t('Platform Fee'),
      'amount' => new Price('1.50', 'AUD'),
      'included' => FALSE,
    ]));

   // $order->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.place.pre_transition' => 'applyPlatformFee',
    ];
  }

}
