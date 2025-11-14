<?php

namespace Drupal\myeventlane_commerce\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class OrderPaidSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LoggerInterface $logger
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      OrderEvents::ORDER_PAID => ['onOrderPaid'],
    ];
  }

  public function onOrderPaid(OrderEvent $event): void {
    $order = $event->getOrder();
    // TODO: your boost logic here.
    $this->logger->info('Order @id paid — MYEL subscriber ran.', ['@id' => $order->id()]);
  }

}