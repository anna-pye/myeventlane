<?php

declare(strict_types=1);

namespace Drupal\Tests\myeventlane_boost\Unit;

use Drupal\myeventlane_boost\EventSubscriber\CartComparisonSubscriber;
use Drupal\commerce_cart\Event\OrderItemComparisonFieldsEvent;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Tests\UnitTestCase;

final class CartComparisonSubscriberTest extends UnitTestCase {

  public function testBoostAddsComparisonFields(): void {
    $item = $this->createMock(OrderItemInterface::class);
    $item->method('bundle')->willReturn('boost');
    $item->method('hasField')->with('field_target_event')->willReturn(TRUE);

    // Signature is (array $comparison_fields, OrderItemInterface $order_item).
    $event = new OrderItemComparisonFieldsEvent(['existing'], $item);

    (new CartComparisonSubscriber())->onCompare($event);

    $fields = $event->getComparisonFields();
    $this->assertContains('purchased_entity', $fields);
    $this->assertContains('field_target_event', $fields);
    $this->assertContains('existing', $fields);
  }
}
