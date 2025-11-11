<?php

namespace Drupal\modeler_api;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\ManagedStorage;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Drupal\modeler_api\Plugin\ModelerPluginManager;
use Drupal\modeler_api\Plugin\ModelOwnerPluginManager;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Provides services of the modeler API.
 */
class Api {

  use EntityOriginalTrait;
  use StringTranslationTrait;

  /**
   * Error messages that get collected through data preparation.
   *
   * @var string[]
   */
  protected array $errors = [];

  /**
   * Gets the API service.
   *
   * @return \Drupal\modeler_api\Api
   *   The API service.
   */
  public static function get(): Api {
    return \Drupal::service('modeler_api.service');
  }

  public const COMPONENT_TYPE_START = 1;
  public const COMPONENT_TYPE_SUBPROCESS = 2;
  public const COMPONENT_TYPE_SWIMLANE = 3;
  public const COMPONENT_TYPE_ELEMENT = 4;
  public const COMPONENT_TYPE_LINK = 5;
  public const COMPONENT_TYPE_GATEWAY = 6;
  public const COMPONENT_TYPE_ANNOTATION = 7;

  public const AVAILABLE_COMPONENT_TYPES = [
    self::COMPONENT_TYPE_START,
    self::COMPONENT_TYPE_SUBPROCESS,
    self::COMPONENT_TYPE_SWIMLANE,
    self::COMPONENT_TYPE_ELEMENT,
    self::COMPONENT_TYPE_LINK,
    self::COMPONENT_TYPE_GATEWAY,
    self::COMPONENT_TYPE_ANNOTATION,
  ];

  /**
   * Constructs the modeler API plugin manager.
   */
  public function __construct(
    protected ModelOwnerPluginManager $modelOwnerPluginManager,
    protected ModelerPluginManager $modelerPluginManager,
    protected ConfigFactoryInterface $configFactory,
    protected ManagedStorage $configStorage,
    protected FileSystemInterface $fileSystem,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteProviderInterface $routeProvider,
    protected MenuLinkManagerInterface $menuLinkManager,
  ) {}

  /**
   * Get the modeler if there's only one available, except the fallback.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface|null
   *   The modeler if only one exists besides the fallback, NULL otherwise.
   */
  public function getModeler(): ?ModelerInterface {
    $modelers = $this->modelerPluginManager->getAllInstances();
    if (count($modelers) === 2) {
      unset($modelers['fallback']);
      return reset($modelers);
    }
    return NULL;
  }

  /**
   * Finds the model owner plugin of a config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|string $model
   *   The config entity or the config entity type ID.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface|null
   *   The model owner
   */
  public function findOwner(ConfigEntityInterface|string $model): ?ModelOwnerInterface {
    $entityTypeId = is_string($model) ? $model : $model->getEntityTypeId();
    foreach ($this->modelOwnerPluginManager->getAllInstances() as $owner) {
      if ($entityTypeId === $owner->configEntityTypeId()) {
        return $owner;
      }
    }
    return NULL;
  }

  /**
   * Embeds the modeler into a config entity form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model entity.
   * @param string $default_modeler_id
   *   The default modeler ID.
   *
   * @return array
   *   The form element describing the integrated modeler.
   */
  public function embedIntoForm(array &$form, FormStateInterface $form_state, ConfigEntityInterface $model, string $default_modeler_id): array {
    $owner = $this->findOwner($model);
    if ($model->isNew()) {
      $owner->setModelerId($model, $default_modeler_id);
      $id = '';
      $modelerData = $owner->getModeler($model)->prepareEmptyModelData($id);
    }
    else {
      $modelerData = $form_state->getUserInput()['modeler_api_data'] ?? '';
    }
    if ($modelerData) {
      $owner->setModelData($model, $modelerData);
    }
    $element = $this->edit($model, $default_modeler_id);
    unset($element['form']);
    $form['#attributes']['class'][] = 'modeler-api-embed';
    $form['#validate'][] = [$this, 'validateEmbed'];
    $form['#entity_builders'][] = [$this, 'buildEntity'];
    return $element;
  }

  /**
   * Validate callback for forms that contain an embedded modeler.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state.
   */
  public function validateEmbed(array &$form, FormStateInterface $form_state): void {
    $modelerData = $form_state->getValue('modeler_api_data');
    $form_state->set('modeler_api_data', $modelerData);
    $formObject = $form_state->getFormObject();
    if ($formObject instanceof EntityFormInterface) {
      $entity = $formObject->getEntity();
      if ($entity instanceof ConfigEntityInterface) {
        $owner = $this->findOwner($entity);
        if (!$this->prepareModelFromData($modelerData, $owner->getPluginId(), 'bpmn_io', $entity->isNew(), TRUE, $entity)) {
          $form_state->setError($form, implode('<br/>', $this->getErrors()));
        }
      }
    }
  }

  /**
   * Entity builder callback for forms that contain an embedded modeler.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state.
   */
  public function buildEntity(string $entity_type_id, ConfigEntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    if ($form['#validated'] ?? FALSE) {
      $owner = $this->findOwner($entity);
      $this->prepareModelFromData($form_state->get('modeler_api_data'), $owner->getPluginId(), 'bpmn_io', $entity->isNew(), TRUE, $entity);
    }
  }

  /**
   * Edit the given entity if the modeler supports that.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The config entity.
   * @param string|null $modelerId
   *   The optional ID of the modeler that should be used for editing.
   * @param bool $readOnly
   *   TRUE, if the model should only be viewed, FALSE otherwise.
   *
   * @return array
   *   The render array for editing the entity.
   */
  public function edit(ConfigEntityInterface $model, ?string $modelerId = NULL, bool $readOnly = FALSE): array {
    $owner = $this->findOwner($model);
    if (!$owner->isEditable($model)) {
      return [];
    }

    if ($modelerId === NULL) {
      // If there's only 1 modeler, let's use that.
      $modeler = $owner->getModeler($model);
      if (!$modeler || $modeler->getPluginId() === 'fallback') {
        $modelerId = $this->getModeler()?->getPluginId();
      }
    }

    if ($modelerId !== NULL && $modelerId !== $owner->getModelerId($model)) {
      try {
        $modeler = $this->modelerPluginManager->createInstance($modelerId);
      }
      catch (PluginException $e) {
        return [
          '#markup' => $this->t('This modeler can not be loaded: :msg.', [
            ':msg' => $e->getMessage(),
          ]),
        ];
      }
      $data = '';
    }
    else {
      $modeler = $owner->getModeler($model);
      $data = $owner->getModelData($model);
    }
    if (!$modeler->isEditable()) {
      return [
        '#markup' => $this->t('This model can not be edited with this modeler.'),
      ];
    }
    if ($data === '' || $owner->getModeler($model)->getPluginId() !== $modeler->getPluginId()) {
      // No raw data is available, or model is from a different modeler, so
      // let the modeler do upstream conversion.
      $build = $modeler->convert($owner, $model, $readOnly);
    }
    else {
      $build = $modeler->edit($owner, $model->id() ?? 'placeholder', $data, $model->isNew(), $readOnly);
    }
    // Add settings.
    $settings = [];
    if ($owner->configEntityBasePath() !== NULL) {
      $settings += [
        'save_url' => Url::fromRoute('entity.' . $owner->configEntityTypeId() . '.save', [
          'modeler_id' => $modeler->getPluginId(),
        ])->toString(),
        'token_url' => Url::fromRoute('system.csrftoken')->toString(),
        'collection_url' => Url::fromRoute('entity.' . $owner->configEntityTypeId() . '.collection')->toString(),
      ];
    }
    else {
      $build['modeler_api_data'] = [
        '#type' => 'hidden',
      ];
    }
    $settings['mode'] = 'edit';
    $settings['config_url'] = Url::fromRoute('entity.' . $owner->configEntityTypeId() . '.config', [
      'modeler_id' => $modeler->getPluginId(),
    ])->toString();
    $build['#attached']['drupalSettings']['modeler_api'] = $settings;
    $build['#title'] = $this->t(':type Model: :label', [':type' => $owner->label(), ':label' => $model->label()]);
    $build['config_form'] = [
      '#type' => 'container',
      '#id' => 'modeler-api-config-form',
    ];
    return $build;
  }

  /**
   * View the given entity if the modeler supports that.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The config entity.
   * @param string|null $modelerId
   *   The optional ID of the modeler that should be used for viewing.
   *
   * @return array
   *   The render array for viewing the entity.
   */
  public function view(ConfigEntityInterface $model, ?string $modelerId = NULL): array {
    $build = $this->edit($model, $modelerId, TRUE);
    $build['#attached']['drupalSettings']['modeler_api']['mode'] = 'view';
    return $build;
  }

  /**
   * Parses the raw modeler data and creates/updates the model entity.
   *
   * @param string $data
   *   The raw model data.
   * @param string $model_owner_id
   *   The model owner ID.
   * @param string $modeler_id
   *   The modeler ID.
   * @param bool $isNew
   *   TRUE, if the data is for a new model, FALSE if we'd expect the model to
   *   already exist.
   * @param bool $dry_run
   *   If TRUE, the method will always create a new entity and avoid saving
   *   the optional raw data.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model
   *   The optional model entity to be used.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface|null
   *   The prepared model entity, if all is successful, NULL otherwise.
   */
  public function prepareModelFromData(string $data, string $model_owner_id, string $modeler_id, bool $isNew, bool $dry_run = FALSE, ?ConfigEntityInterface $model = NULL): ?ConfigEntityInterface {
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner */
    $owner = $this->modelOwnerPluginManager->createInstance($model_owner_id);
    /** @var \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface $modeler */
    $modeler = $this->modelerPluginManager->createInstance($modeler_id);

    $this->errors = [];
    $modeler->parseData($owner, $data);
    $modelId = mb_strtolower($modeler->getId());

    // Validate the model ID that it doesn't exist yet for new models.
    if ($isNew && call_user_func($owner->modelIdExistsCallback(), $modelId)) {
      $this->errors[] = 'The model ID already exists.';
      return NULL;
    }

    if ($model !== NULL) {
      $this->setOriginal($model, clone $model);
      $owner->resetComponents($model);
    }
    else {
      $storage = $this->entityTypeManager->getStorage($owner->configEntityTypeId());
      if ($dry_run) {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model */
        $model = $storage->create(['id' => $modelId]);
      }
      else {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model */
        $model = $storage->load($modelId);
        if ($model) {
          $this->setOriginal($model, clone $model);
          $owner->resetComponents($model);
        }
        else {
          /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $model */
          $model = $storage->create(['id' => $modelId]);
        }
      }
    }
    if ($owner->supportsStatus()) {
      $owner->setStatus($model, $modeler->getStatus());
    }
    $owner
      ->setModelerId($model, $modeler_id)
      ->setChangelog($model, $modeler->getChangelog())
      ->setLabel($model, $modeler->getLabel())
      ->setStorage($model, $modeler->getStorage())
      ->setDocumentation($model, $modeler->getDocumentation())
      ->setTags($model, $modeler->getTags())
      ->setVersion($model, $modeler->getVersion());
    $annotations = [];
    $colors = [];
    $swimlanes = [];
    foreach ($modeler->readComponents() as $component) {
      if ($color = $component->getColor()) {
        $colors[$component->getId()] = $color;
      }
      if ($id = $component->getParentId()) {
        if (!isset($swimlanes[$id])) {
          $swimlanes[$id] = [
            'id' => NULL,
            'name' => '',
            'components' => [],
          ];
        }
        if ($component->getType() === self::COMPONENT_TYPE_SWIMLANE) {
          $swimlanes[$id]['id'] = $component->getId();
          $swimlanes[$id]['name'] = $component->getLabel();
          continue;
        }
        $swimlanes[$id]['components'][] = $component->getId();
      }
      if ($component->getType() === self::COMPONENT_TYPE_ANNOTATION) {
        $annotations[] = $component;
        continue;
      }
      if ($errors = $component->validate()) {
        $this->errors = array_merge($this->errors, $errors);
      }
      if (!$owner->addComponent($model, $component)) {
        $this->errors[] = 'A component can not be added.';
      }
    }
    $owner->setAnnotations($model, $annotations);
    $owner->setColors($model, $colors);
    $owner->setSwimlanes($model, $swimlanes);
    if (empty($this->errors)) {
      if (!$dry_run) {
        $owner->finalizeAddingComponents($model);
        $owner->setModelData($model, $data);
      }
      return $model;
    }
    $this->errors = array_unique($this->errors);
    return NULL;
  }

  /**
   * Exports the model owner's config with all dependencies into an archive.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The model owner's config entity.
   * @param string|null $archiveFileName
   *   The fully qualified filename for the archive. If NULL, this only
   *   calculates and returns the dependencies but doesn't write an archive.
   *
   * @return array
   *   An array with "config" and "module" keys, each containing the list of
   *   dependencies.
   */
  public function exportArchive(ModelOwnerInterface $owner, ConfigEntityInterface $entity, ?string $archiveFileName = NULL): array {
    $dependencies = [
      'config' => [
        $owner->configEntityProviderId() . '.' . $owner->configEntityTypeId() . '.' . $entity->id(),
      ],
      'module' => [],
    ];
    if ($owner->storageMethod($entity) === Settings::STORAGE_OPTION_SEPARATE) {
      $dependencies['config'][] = 'modeler_api.data_model.' . $owner->storageId($entity);
    }
    $this->getNestedDependencies($dependencies, $entity->getDependencies());
    if ($archiveFileName !== NULL) {
      if (file_exists($archiveFileName)) {
        try {
          @$this->fileSystem->delete($archiveFileName);
        }
        catch (FileException) {
          // Ignore failed deletes.
        }
      }
      $archiver = new ArchiveTar($archiveFileName, 'gz');
      foreach ($dependencies['config'] as $name) {
        $config = $this->configStorage->read($name);
        if ($config) {
          unset($config['uuid'], $config['_core']);
          $archiver->addString("$name.yml", Yaml::encode($config));
        }
      }
      $archiver->addString('dependencies.yml', Yaml::encode($dependencies));
    }

    // Remove model from the config dependencies.
    array_shift($dependencies['config']);
    foreach ($dependencies as $type => $values) {
      if (empty($values)) {
        unset($dependencies[$type]);
      }
      else {
        sort($dependencies[$type]);
      }
    }
    return $dependencies;
  }

  /**
   * Recursively determines config dependencies.
   *
   * @param array $allDependencies
   *   The list of all dependencies.
   * @param array $dependencies
   *   The list of dependencies to be added.
   */
  public function getNestedDependencies(array &$allDependencies, array $dependencies): void {
    foreach ($dependencies['module'] ?? [] as $module) {
      if (!in_array($module, $allDependencies['module'], TRUE)) {
        $allDependencies['module'][] = $module;
      }
    }
    if (empty($dependencies['config'])) {
      return;
    }
    foreach ($dependencies['config'] as $dependency) {
      if (!in_array($dependency, $allDependencies['config'], TRUE)) {
        $allDependencies['config'][] = $dependency;
        $depConfig = $this->configFactory->get($dependency)->getStorage()->read($dependency);
        if ($depConfig && isset($depConfig['dependencies'])) {
          $this->getNestedDependencies($allDependencies, $depConfig['dependencies']);
        }
      }
    }
  }

  /**
   * Provides a list of available plugins from the owner for a given type.
   *
   * @param \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface $owner
   *   The model owner.
   * @param int $type
   *   The component type.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface[]
   *   The list of plugins.
   */
  public function availableOwnerComponents(ModelOwnerInterface $owner, int $type): array {
    assert(in_array($type, self::AVAILABLE_COMPONENT_TYPES), 'Invalid component type');
    return $owner->availableOwnerComponents($type);
  }

  /**
   * Gets error messages that got collected through data preparation.
   *
   * @return string[]
   *   The error messages that got collected through data preparation.
   */
  public function getErrors(): array {
    return $this->errors ?? [];
  }

  /**
   * Gets the route, if it exists, NULL otherwise.
   *
   * @param string $name
   *   The route name.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The route, if it exists, NULL otherwise.
   */
  public function getRouteByName(string $name): ?Route {
    try {
      return $this->routeProvider->getRouteByName($name);
    }
    catch (RouteNotFoundException) {
      // If the route can not be found, don't set the configure route.
    }
    return NULL;
  }

  /**
   * Gets the menu name of the parent path.
   *
   * @param string $path
   *   The path of which we search for the parent path.
   *
   * @return string|null
   *   The menu name of the parent path, if we can find it, FALSE otherwise.
   */
  public function getParentMenuName(string $path): ?string {
    $parts = explode('/', trim($path, '/'));
    array_pop($parts);
    $path = implode('/', $parts);
    $url = Url::fromUri('internal:/' . $path);
    $links = $this->menuLinkManager->loadLinksByRoute($url->getRouteName(), $url->getRouteParameters());
    if (!empty($links)) {
      $menuLink = reset($links);
      return $menuLink->getPluginId();
    }
    return NULL;
  }

  /**
   * Get the edit URL for an entity of given type and id.
   *
   * @param string $type
   *   The entity type.
   * @param string $id
   *   The entity id.
   *
   * @return \Drupal\Core\Url
   *   The edit URL.
   */
  public function editUrl(string $type, string $id): Url {
    $name = 'entity.' . $type . '.edit';
    if (!$this->getRouteByName($name)) {
      $name = 'entity.' . $type . '.edit_form';
      if (!$this->getRouteByName($name)) {
        return Url::fromRoute('entity.' . $type . '.collection');
      }
    }
    return Url::fromRoute($name, [$type => $id]);
  }

}
