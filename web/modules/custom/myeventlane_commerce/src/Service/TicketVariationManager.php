<?php

namespace Drupal\myeventlane_commerce\Service;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;

final class TicketVariationManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
  ) {}

  /**
   * Idempotently load/create a variation by SKU for a given Product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   * @param string $sku
   * @param array $values ['title' => string, 'status' => int, 'price' => \Drupal\commerce_price\Price]
   */
  public function getOrCreate(ProductInterface $product, string $sku, array $values = []) : ProductVariation {
    $storage = $this->etm->getStorage('commerce_product_variation');

    if ($found = $storage->loadByProperties(['sku' => $sku])) {
      /** @var \Drupal\commerce_product\Entity\ProductVariation $v */
      $v = reset($found);
      if ((int) $v->getProductId() !== (int) $product->id()) {
        $v->setProductId($product->id())->save();
      }
      return $v;
    }

    $data = [
      'type' => 'ticket',
      'sku' => $sku,
      'title' => $values['title'] ?? 'Ticket',
      'status' => $values['status'] ?? 1,
    ];
    if (!empty($values['price']) && $values['price'] instanceof Price) {
      $data['price'] = $values['price'];
    }

    /** @var \Drupal\commerce_product\Entity\ProductVariation $v */
    $v = ProductVariation::create($data);
    // DO NOT set UUID. Let Drupal generate it; our subscriber will also guard.
    $v->setProductId($product->id());
    $v->save();
    return $v;
  }
}
