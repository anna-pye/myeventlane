<?php

namespace Drupal\myeventlane_messaging\EventSubscriber;

use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class OrderPlacedSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.post_transition' => 'onPlace',
    ];
  }

  public function onPlace(WorkflowTransitionEvent $event): void {
    $order = $event->getEntity();
    $mail  = $order->getEmail() ?: ($order->getCustomer() ? $order->getCustomer()->getEmail() : NULL);
    if (!$mail) return;

    \Drupal::service('myeventlane_messaging.manager')->queue('order_receipt', $mail, [
      'first_name'   => $order->getCustomer() ? $order->getCustomer()->getDisplayName() : 'there',
      'order_number' => $order->label(),
      'order_url'    => $order->toUrl('canonical', ['absolute'=>TRUE])->toString(TRUE)->getGeneratedUrl(),
      // 'unsubscribe_url' => UnsubscribeController::buildUnsubUrl($order->getCustomer()),
    ], ['langcode' => $order->language()->getId()]);
  }
}
