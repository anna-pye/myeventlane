<?php

namespace Drupal\Tests\commerce_order\Unit\Plugin\Commerce\Condition;

use Drupal\Tests\UnitTestCase;
use Drupal\commerce\EntityUuidMapperInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderStore;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * @coversDefaultClass \Drupal\commerce_order\Plugin\Commerce\Condition\OrderStore
 * @group commerce
 */
class OrderStoreTest extends UnitTestCase {

  /**
   * ::covers evaluate.
   */
  public function testEvaluate() {
    $entity_uuid_mapper = $this->prophesize(EntityUuidMapperInterface::class);
    $entity_uuid_mapper = $entity_uuid_mapper->reveal();
    $condition = new OrderStore([
      'stores' => ['30df59bd-7b03-4cf7-bb35-d42fc49f0651'],
    ], 'order_store', ['entity_type' => 'commerce_order'], $entity_uuid_mapper);

    $store = $this->prophesize(StoreInterface::class);
    $store->uuid()->willReturn('30df59bd-7b03-4cf7-bb35-d42fc49f0651');
    $store = $store->reveal();
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order->getStore()->willReturn($store);
    $order = $order->reveal();
    $this->assertTrue($condition->evaluate($order));

    $store = $this->prophesize(StoreInterface::class);
    $store->uuid()->willReturn('a019d89b-c4d9-4ed4-b859-894e4e2e93cf');
    $store = $store->reveal();
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order->getStore()->willReturn($store);
    $order = $order->reveal();
    $this->assertFalse($condition->evaluate($order));
  }

}
