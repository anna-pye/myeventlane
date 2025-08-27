<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\EventSubscriber;

use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\OrderItemComparisonFieldsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


final class CartComparisonSubscriber implements EventSubscriberInterface {
  public static function getSubscribedEvents(): array {
    return [CartEvents::ORDER_ITEM_COMPARISON_FIELDS => 'onCompare'];
  }

  public function onCompare(OrderItemComparisonFieldsEvent $event): void {
    $order_item = $event->getOrderItem();
    if ($order_item->bundle() !== 'boost') {
      return;
    }
    // Ensure these fields are used for equality checks.
    $fields = $event->getComparisonFields();
    $fields[] = 'purchased_entity';
    if ($order_item->hasField('field_target_event')) {
      $fields[] = 'field_target_event';
    }
    $event->setComparisonFields(array_unique($fields));
  }
}

