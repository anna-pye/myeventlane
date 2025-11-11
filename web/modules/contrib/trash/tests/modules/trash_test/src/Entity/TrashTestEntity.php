<?php

namespace Drupal\trash_test\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides a trash test entity.
 *
 * @ContentEntityType(
 *   id = "trash_test_entity",
 *   label = @Translation("Trash test"),
 *   label_collection = @Translation("Trash test"),
 *   label_singular = @Translation("Trash test entity"),
 *   label_plural = @Translation("Trash test entities"),
 *   label_count = @PluralTranslation(
 *     singular = "@count trash test entity",
 *     plural = "@count trash test entities",
 *   ),
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "views_data" = "\Drupal\views\EntityViewsData",
 *   },
 *   base_table = "trash_test",
 *   revision_table = "trash_test_revision",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/trash_test/{trash_test}",
 *     "collection" = "/trash_test",
 *     "revision" = "/trash_test/{trash_test}/revisions/{trash_test_revision}/view",
 *   }
 * )
 */
class TrashTestEntity extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['reference'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Reference'))
      ->setDescription(t('Reference to another TrashTestEntity.'))
      ->setSetting('target_type', 'trash_test_entity')
      ->setRequired(FALSE);

    return $fields;
  }

}
