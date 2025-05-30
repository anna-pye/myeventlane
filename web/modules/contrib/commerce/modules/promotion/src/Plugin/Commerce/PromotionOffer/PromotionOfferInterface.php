<?php

namespace Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\commerce_promotion\Entity\PromotionInterface;

/**
 * Defines the base interface for offers.
 *
 * Offers can target the entire order, or individual order items.
 * Therefore, each offer plugin actually implements one of the child interfaces.
 *
 * @see \Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderPromotionOfferInterface
 * @see \Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderItemPromotionOfferInterface
 */
interface PromotionOfferInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Gets the offer entity type ID.
   *
   * This is the entity type ID of the entity passed to apply().
   *
   * @return string
   *   The offer's entity type ID.
   */
  public function getEntityTypeId();

  /**
   * Applies the offer to the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $promotion
   *   THe parent promotion.
   */
  public function apply(EntityInterface $entity, PromotionInterface $promotion);

  /**
   * Allows an offer to clean up any modifications done to the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $promotion
   *   THe parent promotion.
   */
  public function clear(EntityInterface $entity, PromotionInterface $promotion);

}
