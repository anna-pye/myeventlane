<?php

namespace Drupal\commerce_store;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\commerce_store\Entity\StoreType;

/**
 * Defines the list builder for stores.
 */
class StoreListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_store\Entity\StoreInterface $entity */
    $store_type = StoreType::load($entity->bundle());

    $row['name']['data'] = Link::fromTextAndUrl($entity->label(), $entity->toUrl());
    $row['type'] = $store_type->label();

    return $row + parent::buildRow($entity);
  }

}
