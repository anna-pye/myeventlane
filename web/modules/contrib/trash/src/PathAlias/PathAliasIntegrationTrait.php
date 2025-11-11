<?php

declare(strict_types=1);

namespace Drupal\trash\PathAlias;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;

/**
 * Provides path alias integration for trash handlers.
 */
trait PathAliasIntegrationTrait {

  /**
   * Automatically deletes associated path aliases when entity is trashed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being trashed.
   */
  protected function deleteAssociatedPathAliases(EntityInterface $entity): void {
    // Skip path_alias entities themselves - this is only for entities that
    // reference them.
    if ($entity->getEntityTypeId() === 'path_alias') {
      return;
    }

    // Check if the path_alias entity type is trash-enabled.
    if (!$this->trashManager->isEntityTypeEnabled('path_alias')) {
      return;
    }

    // Loop through all fields and call delete() if needed.
    assert($entity instanceof FieldableEntityInterface);
    foreach ($entity->getFields() as $field) {
      if ($field instanceof PathFieldItemList) {
        $field->delete();
      }
    }
  }

  /**
   * Automatically restores associated path aliases when an entity is restored.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being restored.
   * @param int|string $deleted_timestamp
   *   The timestamp when the entity was deleted.
   */
  protected function restoreAssociatedPathAliases(EntityInterface $entity, int|string $deleted_timestamp): void {
    // Skip path_alias entities themselves - this is only for entities that
    // reference them.
    if ($entity->getEntityTypeId() === 'path_alias') {
      return;
    }

    // Check if the path_alias entity type is trash-enabled.
    if (!$this->trashManager->isEntityTypeEnabled('path_alias')) {
      return;
    }

    // Find path aliases deleted at the exact same time.
    $storage = $this->entityTypeManager->getStorage('path_alias');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('deleted', $deleted_timestamp)
      ->condition('path', '/' . $entity->toUrl()->getInternalPath(), 'STARTS_WITH')
      ->execute();

    if (!empty($ids)) {
      $path_aliases = $this->trashManager->executeInTrashContext('ignore', function () use ($storage, $ids) {
        return $storage->loadMultiple($ids);
      });
      $storage->restoreFromTrash($path_aliases);
    }
  }

}
