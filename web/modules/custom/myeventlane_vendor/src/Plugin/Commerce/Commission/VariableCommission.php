<?php

namespace Drupal\myeventlane_vendor\Plugin\Commerce\Commission;

use Drupal\commerce_price\Price;
use Drupal\commerce_order\Entity\OrderInterface;

class VariableCommission {

  public static function calculateCommission(OrderInterface $order) {
    $commission_rate = '0.015'; // 1.5%
    // Future enhancement: Load custom commission per vendor from configuration.
    $total_price = $order->getTotalPrice()->getNumber();
    $commission = $total_price * $commission_rate;
    return new Price((string) $commission, $order->getTotalPrice()->getCurrencyCode());
  }
}
