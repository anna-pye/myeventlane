<?php

namespace Drupal\eca_workflow\Hook;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\eca\EntityOriginalTrait;
use Drupal\eca\Event\TriggerEvent;
use Drupal\eca\Service\ContentEntityTypes;

/**
 * Implements workflow hooks for the ECA Workflow submodule.
 */
class WorkflowHooks {

  use EntityOriginalTrait;

  /**
   * Constructs a new WorkflowHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected ModerationInformationInterface $moderationInformation,
    protected ContentEntityTypes $contentEntityTypes,
  ) {}

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      if ($this->moderationInformation->isModeratedEntity($entity) && $entity->hasField('moderation_state')) {
        $original = $this->getOriginal($entity);
        $from_state = $original instanceof ContentEntityInterface ? $original->get('moderation_state')->value : NULL;
        $to_state = $entity->get('moderation_state')->value;
        if ($from_state !== $to_state) {
          $this->triggerEvent->dispatchFromPlugin('workflow:transition', $entity, $from_state, $to_state, $this->contentEntityTypes);
        }
      }
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->entityInsert($entity);
  }

}
