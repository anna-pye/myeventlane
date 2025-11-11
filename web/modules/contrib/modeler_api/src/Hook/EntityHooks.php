<?php

namespace Drupal\modeler_api\Hook;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Entity\AccessControlHandler;
use Drupal\modeler_api\Entity\ListBuilder;
use Drupal\modeler_api\Form\DeleteForm;
use Drupal\modeler_api\ModelerApiPermissions;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;

/**
 * Implements hooks for the Modeler API module.
 */
class EntityHooks {

  /**
   * Constructs a new ModelerApiHooks object.
   *
   * @param \Drupal\modeler_api\Api $modelerApiService
   *   The modeler API service.
   * @param \Drupal\modeler_api\Plugin\ModelerPluginManager $modelerManager
   *   The modeler plugin manager.
   * @param \Drupal\modeler_api\Plugin\ModelOwnerPluginManager $modelOwnerPluginManager
   *   The model owner plugin manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected Api $modelerApiService,
    protected ModelerPluginManager $modelerManager,
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_entity_type_build().
   *
   * @param array $entity_types
   *   The entity type definitions.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    static $alreadyRunning;
    if ($alreadyRunning) {
      return;
    }
    $alreadyRunning = TRUE;
    foreach ($this->modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {
      $basePath = $owner->configEntityBasePath();
      if ($basePath === NULL) {
        continue;
      }
      $type = $owner->configEntityTypeId();
      /**
       * @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
       */
      $entity_types[$type]
        ->setAccessClass(AccessControlHandler::class)
        ->setListBuilderClass(ListBuilder::class)
        ->setLinkTemplate('collection', '/' . $basePath)
        ->setFormClass('delete', DeleteForm::class)
        ->setLinkTemplate('edit-form', '/' . $basePath . '/{' . $type . '}/edit')
        ->setLinkTemplate('delete-form', '/' . $basePath . '/{' . $type . '}/delete');
    }
    $alreadyRunning = FALSE;
  }

  /**
   * Implements hook_entity_operation().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   An array of operations.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    $modelers = $this->modelerManager->getAllInstances();
    $numberOfModelers = count($modelers);
    if ($numberOfModelers === 1) {
      // There is only the fallback modeler, no operation provided.
      return [];
    }
    if (
      $entity instanceof ConfigEntityInterface &&
      ($owner = $this->modelerApiService->findOwner($entity)) &&
      $owner->configEntityBasePath()
    ) {
      $type = $entity->getEntityTypeId();
      $modelerId = $owner->getModelerId($entity);

      if ($numberOfModelers === 2) {
        // The default edit form comes from the list builder. If we have both
        // routes, "edit_form" and "edit", let's add the second one here.
        $name = 'entity.' . $type . '.edit';
        $defaultModeler = $this->modelerApiService->getModeler();
        $modelerId = $defaultModeler->getPluginId();
        if ($this->modelerApiService->getRouteByName($name) && $this->currentUser->hasPermission(ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $defaultModeler->getPluginId()))) {
          $operations['edit_with_modeler'] = [
            'title' => t('Edit with modeler'),
            'url' => Url::fromRoute($name, [$type => $entity->id()]),
            'weight' => 31,
          ];
        }
      }
      else {
        foreach ($modelers as $id => $modeler) {
          if ($modelerId !== $id && $modeler->isEditable() && $this->currentUser->hasPermission(ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $id))) {
            $operations['open_with_' . $id] = [
              'title' => t('Edit with :label', [':label' => $modeler->label()]),
              'url' => Url::fromRoute('entity.' . $type . '.edit_with.' . $id, [$type => $entity->id()]),
              'weight' => 40,
            ];
          }
          if ($id !== 'fallback' && $modelerId !== $id && $this->currentUser->hasPermission(ModelerApiPermissions::getPermissionKey('view', $owner->getPluginId(), $id))) {
            $operations['view_with_' . $id] = [
              'title' => t('View with :label', [':label' => $modeler->label()]),
              'url' => Url::fromRoute('entity.' . $type . '.view_with.' . $id, [$type => $entity->id()]),
              'weight' => 45,
            ];
          }
        }
      }

      $name = 'entity.' . $type . '.canonical';
      if ($this->modelerApiService->getRouteByName($name) && $this->currentUser->hasPermission(ModelerApiPermissions::getPermissionKey('view', $owner->getPluginId()))) {
        $operations['view'] = [
          'title' => t('View'),
          'url' => Url::fromRoute($name, [$type => $entity->id()]),
          'weight' => 44,
        ];
      }
      if ($owner->supportsStatus() && $this->currentUser->hasPermission(ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $modelerId))) {
        if (!$entity->status()) {
          $operations['enable'] = [
            'title' => t('Enable'),
            'url' => Url::fromRoute('entity.' . $type . '.enable', [$type => $entity->id()]),
            'weight' => 50,
          ];
        }
        else {
          $operations['disable'] = [
            'title' => t('Disable'),
            'url' => Url::fromRoute('entity.' . $type . '.disable', [$type => $entity->id()]),
            'weight' => 51,
          ];
        }
      }
      $name = 'entity.' . $type . '.clone';
      if ($this->modelerApiService->getRouteByName($name) && $this->currentUser->hasPermission(ModelerApiPermissions::getPermissionKey('edit', $owner->getPluginId(), $modelerId))) {
        if ($owner->isEditable($entity)) {
          $operations['clone'] = [
            'title' => t('Clone'),
            'url' => Url::fromRoute($name, [$type => $entity->id()]),
            'weight' => 52,
          ];
        }
      }
      if ($owner->isExportable($entity)) {
        $name = 'entity.' . $type . '.export';
        if ($this->modelerApiService->getRouteByName($name)) {
          $operations['export'] = [
            'title' => t('Export'),
            'url' => Url::fromRoute($name, [$type => $entity->id()]),
            'weight' => 52,
          ];
        }
        $name = 'entity.' . $type . '.export_recipe';
        if ($this->modelerApiService->getRouteByName($name)) {
          $operations['export_recipe'] = [
            'title' => t('Export as recipe'),
            'url' => Url::fromRoute($name, [$type => $entity->id()]),
            'weight' => 53,
          ];
        }
      }
    }
    return $operations;
  }

  /**
   * Implements hook_config_schema_info_alter().
   *
   * @param array $definitions
   *   The schema definitions.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    foreach ($this->modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {
      $provider = $owner->configEntityProviderId();
      $entityTypeId = $owner->configEntityTypeId();
      $key = implode('.', [$provider, $entityTypeId, '*']);
      if (!isset($definitions[$key]['mapping']['third_party_settings']['mapping']['modeler_api'])) {
        $definitions[$key]['mapping']['third_party_settings']['type'] = 'mapping';
        $definitions[$key]['mapping']['third_party_settings']['mapping'] = [
          'modeler_api' => [
            'type' => 'mapping',
            'mapping' => [
              'modeler_id' => [
                'type' => 'string',
                'label' => 'ID',
              ],
              'storage' => [
                'type' => 'string',
                'label' => 'Override system-wide storage setting',
              ],
              'data' => [
                'type' => 'string',
                'label' => 'Raw data, or an md5 hash of the raw data if that is stored externally',
              ],
              'changelog' => [
                'type' => 'text',
                'label' => 'Changelog',
              ],
              'label' => [
                'type' => 'label',
                'label' => 'Label',
              ],
              'documentation' => [
                'type' => 'text',
                'label' => 'Documentation',
              ],
              'tags' => [
                'type' => 'sequence',
                'label' => 'Changelog',
                'sequence' => [
                  'type' => 'string',
                  'label' => 'Tag',
                ],
              ],
              'version' => [
                'type' => 'string',
                'label' => 'Version',
              ],
              'annotations' => [
                'type' => 'sequence',
                'label' => 'Annotations',
                'sequence' => [
                  'type' => 'mapping',
                  'label' => 'Annotation',
                  'mapping' => [
                    'text' => [
                      'type' => 'label',
                      'label' => 'Text',
                    ],
                    'assigned_to' => [
                      'type' => 'sequence',
                      'label' => 'Assigned to',
                      'sequence' => [
                        'type' => 'string',
                        'label' => 'Target ID',
                      ],
                    ],
                  ],
                ],
              ],
              'colors' => [
                'type' => 'sequence',
                'label' => 'Colors',
                'sequence' => [
                  'type' => 'mapping',
                  'label' => 'Color',
                  'mapping' => [
                    'fill' => [
                      'type' => 'string',
                      'label' => 'Fill color',
                    ],
                    'stroke' => [
                      'type' => 'string',
                      'label' => 'Stroke color',
                    ],
                  ],
                ],
              ],
              'swimlanes' => [
                'type' => 'sequence',
                'label' => 'Swimlanes',
                'sequence' => [
                  'type' => 'mapping',
                  'label' => 'Swimlane',
                  'mapping' => [
                    'name' => [
                      'type' => 'string',
                      'label' => 'Name',
                    ],
                    'components' => [
                      'type' => 'sequence',
                      'label' => 'Components',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ];
      }
    }
  }

  /**
   * Implements hook_system_info_alter().
   *
   * @param array $info
   *   The info file contents, passed by reference so that it can be altered.
   * @param \Drupal\Core\Extension\Extension $file
   *   Full information about the module or theme.
   * @param string $type
   *   Either 'module' or 'theme', depending on the type of .info.yml file that
   *   was passed.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('system_info_alter')]
  public function systemInfoAlter(array &$info, Extension $file, string $type): void {
    if ($type === 'module') {
      foreach ($this->modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {
        $provider = $owner->getPluginDefinition()['provider'];
        if (str_ends_with($file->getPathname(), '/' . $provider . '.info.yml')) {
          $name = 'entity.' . $owner->configEntityTypeId() . '.collection';
          if ($route = $this->modelerApiService->getRouteByName($name)) {
            $info['configure'] = $name;
          }
        }
      }
    }
  }

  /**
   * Implements hook_modules_installed().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    if ($is_syncing) {
      return;
    }
    foreach ($this->modelOwnerPluginManager->getAllInstances(TRUE) as $owner) {
      $provider = $owner->getPluginDefinition()['provider'];
      if (in_array($provider, $modules)) {
        $this->entityTypeManager->clearCachedDefinitions();
        return;
      }
    }
  }

}
