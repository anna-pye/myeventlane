<?php

namespace Drupal\eca_ui;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Model;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;

/**
 * Provides services to migrate older models to the v3 format.
 *
 * @deprecated in eca:3.0.0 and is removed from eca:3.1.0. Raw model data is now
 * owned by the Modeler API and will be stored in third-party settings or in
 * their own config entity.
 *
 * @see https://www.drupal.org/project/eca/issues/3517784
 */
final class V3Migrate {

  use StringTranslationTrait;

  /**
   * The model owner plugin.
   *
   * @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   */
  protected ModelOwnerInterface $owner;

  /**
   * Constructs the V3Migrate service.
   */
  public function __construct(
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected ModelerPluginManager $modelerPluginManager,
    protected MessengerInterface $messenger,
  ) {
    $owner = $this->modelOwnerPluginManager->createInstance('eca');
    if ($owner instanceof ModelOwnerInterface) {
      $this->owner = $owner;
    }
    else {
      throw new \RuntimeException('The model owner plugin for ECA is not available.');
    }
  }

  /**
   * Migrates all older models to the v3 format.
   */
  public function migrateAll(): void {
    foreach (Eca::loadMultiple() as $eca) {
      $this->migrateEca($eca);
    }
  }

  /**
   * Migrate the given ECA model if necessary.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA model to migrate.
   * @param bool $silent
   *   This migration will output messages by default, unless this is set to
   *   TRUE, which skips all messages.
   */
  public function migrateEca(Eca $eca, bool $silent = FALSE): void {
    if (in_array('modeler_api', $eca->getThirdPartyProviders(), TRUE)) {
      // Already up-to-date, nothing to do.
      if (!$silent) {
        $this->messenger->addStatus($this->t('ECA @name (@id) is already up-to-date.', [
          '@name' => $eca->label(),
          '@id' => $eca->id(),
        ]));
      }
      return;
    }

    /** @var \Drupal\eca\Entity\Model|null $modelData */
    $modelData = Model::load($eca->id());
    if ($modelData) {
      $modeler_id = 'bpmn_io';
      /** @var \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface|null $modeler */
      $modeler = $this->modelerPluginManager->createInstance($modeler_id);
      if (!$modeler) {
        if (!$silent) {
          $this->messenger->addError($this->t('Cannot migrate ECA @name (@id) because the modeler @modeler_id is not available.', [
            '@name' => $eca->label(),
            '@id' => $eca->id(),
            '@modeler_id' => $modeler_id,
          ]));
        }
        return;
      }
      $data = $modelData->get('modeldata');
      $data = str_replace('camunda:field name="form_id"', 'camunda:field name="form_ids"', $data);
      $modeler->parseData($this->owner, $data);
      $this->owner
        ->setModelerId($eca, $modeler_id)
        ->setModelData($eca, $data)
        ->setStatus($eca, $modeler->getStatus())
        ->setChangelog($eca, $modeler->getChangelog())
        ->setLabel($eca, $modeler->getLabel())
        ->setDocumentation($eca, $modeler->getDocumentation())
        ->setTags($eca, $modeler->getTags())
        ->setVersion($eca, $modeler->getVersion());
      $modelData->delete();
    }
    else {
      $this->owner
        ->setModelerId($eca, 'fallback')
        ->setModelData($eca, '')
        ->setChangelog($eca, '')
        ->setLabel($eca, $eca->get('label') ?? $eca->id())
        ->setDocumentation($eca, '')
        ->setTags($eca, [])
        ->setVersion($eca, '');
    }
    $events = $eca->get('events');
    $changed = FALSE;
    foreach ($events as $id => $event) {
      if (isset($event['configuration']['form_id'])) {
        $events[$id]['configuration']['form_ids'] = $event['configuration']['form_id'];
        unset($events[$id]['configuration']['form_id']);
        $changed = TRUE;
      }
    }
    if ($changed) {
      $eca->set('events', $events);
    }
    $eca->save();
    if (!$silent) {
      $this->messenger->addStatus($this->t('Successfully migrated ECA @name (@id).', [
        '@name' => $eca->label(),
        '@id' => $eca->id(),
      ]));
    }
  }

}
