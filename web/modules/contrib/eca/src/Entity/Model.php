<?php

namespace Drupal\eca\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the ECA Model entity type.
 *
 * @ConfigEntityType(
 *   id = "eca_model",
 *   label = @Translation("ECA Model"),
 *   label_collection = @Translation("ECA Models"),
 *   label_singular = @Translation("ECA Model"),
 *   label_plural = @Translation("ECA Models"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ECA Model",
 *     plural = "@count ECA Models",
 *   ),
 *   config_prefix = "model",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "tags",
 *     "documentation",
 *     "modeldata"
 *   }
 * )
 *
 * @deprecated in eca:3.0.0 and is removed from eca:3.1.0. Raw model data is now
 * owned by the Modeler API and will be stored in third-party settings or in
 * their own config entity.
 *
 * @see https://www.drupal.org/project/eca/issues/3517784
 */
class Model extends ConfigEntityBase {

  /**
   * Get the tags of this model.
   *
   * @return array
   *   The tags of this model.
   */
  public function getTags(): array {
    return $this->get('tags') ?? [];
  }

  /**
   * Get the documentation of this model.
   *
   * @return string
   *   The documentation.
   */
  public function getDocumentation(): string {
    return $this->get('documentation') ?? '';
  }

  /**
   * Get the raw model data of this model.
   *
   * @return string
   *   The raw model data.
   */
  public function getModeldata(): string {
    return $this->get('modeldata') ?? '';
  }

}
