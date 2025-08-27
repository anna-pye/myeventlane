<?php

declare(strict_types=1);

namespace Drupal\Tests\myeventlane_boost\Unit;

use Drupal\myeventlane_boost\EventSubscriber\BoostRefundSubscriber;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\node\NodeInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

final class BoostRefundSubscriberTest extends UnitTestCase {

  public function testRefundRevokesBoost(): void {
    $setCalls = [];

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('event');
    $node->expects($this->exactly(2))
      ->method('set')
      ->willReturnCallback(function ($field, $value) use (&$setCalls, $node) {
        $setCalls[] = [$field, $value];
        return $node;
      });
    $node->expects($this->once())->method('save');

    $node_storage = $this->createMock(EntityStorageInterface::class);
    $node_storage->method('load')->with(123)->willReturn($node);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('node')->willReturn($node_storage);

    $logger = $this->createMock(LoggerInterface::class);
    $subscriber = new BoostRefundSubscriber($etm, $logger);

    $item = $this->createMock(OrderItemInterface::class);
    $item->method('bundle')->willReturn('boost');
    $item->method('hasField')->with('field_target_event')->willReturn(TRUE);
    $item->method('get')->with('field_target_event')->willReturn((object) ['target_id' => 123]);

    $order = $this->createMock(OrderInterface::class);
    $order->method('getItems')->willReturn([$item]);
    $order->method('id')->willReturn(99);

    $payment = $this->createMock(PaymentInterface::class);
    $payment->method('getOrder')->willReturn($order);
    $payment->method('id')->willReturn(77);

    $transition = $this->createMock(WorkflowTransition::class);
    $transition->method('getId')->willReturn('refund');
    $workflow = $this->createMock(WorkflowInterface::class);

    // state_machine expects 4 params (transition, workflow, entity, context).
    $event = new WorkflowTransitionEvent($transition, $workflow, $payment, []);

    $subscriber->onRefundOrVoid($event);

    $this->assertContains(['field_promoted', 0], $setCalls);
    $this->assertContains(['field_promo_expires', null], $setCalls);
  }
}
