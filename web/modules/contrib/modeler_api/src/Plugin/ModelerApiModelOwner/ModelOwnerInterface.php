<?php

namespace Drupal\modeler_api\Plugin\ModelerApiModelOwner;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The model owner plugin interface.
 *
 * A model owner owns config entities which can be modeled by the modeler API.
 */
interface ModelOwnerInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Provides the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function label(): string;

  /**
   * Provides the plugin description.
   *
   * @return string
   *   The plugin description.
   */
  public function description(): string;

  /**
   * Provides a callback for validating the unique model ID.
   *
   * Example: "[MyEntity::class, 'load']"
   *
   * @return array
   *   The callback.
   */
  public function modelIdExistsCallback(): array;

  /**
   * Provides the provider id of the model.
   *
   * @return string
   *   The provider id.
   */
  public function configEntityProviderId(): string;

  /**
   * Provides the entity type id of the model.
   *
   * @return string
   *   The entity type id.
   */
  public function configEntityTypeId(): string;

  /**
   * Provides the base path without leading or trailing slash.
   *
   * @return string|null
   *   The base path, or NULL if the model owner controls routing itself.
   */
  public function configEntityBasePath(): ?string;

  /**
   * Provides the settings form class.
   *
   * @return string|null
   *   The settings form class, if this model owner support settings, NULL
   *   otherwise.
   */
  public function settingsForm(): ?string;

  /**
   * Allow model owner to alter the default config form for model's metadata.
   *
   * @param array $form
   *   The form.
   */
  public function modelConfigFormAlter(array &$form): void;

  /**
   * Determines, if the model is editable.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return bool
   *   TRUE, if the model is editable, FALSE otherwise.
   */
  public function isEditable(ConfigEntityInterface $model): bool;

  /**
   * Determines, if the model is exportable.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return bool
   *   TRUE, if the model is exportable, FALSE otherwise.
   */
  public function isExportable(ConfigEntityInterface $model): bool;

  /**
   * Enables the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   */
  public function enable(ConfigEntityInterface $model): void;

  /**
   * Disabled the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   */
  public function disable(ConfigEntityInterface $model): void;

  /**
   * Clones the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The cloned model.
   */
  public function clone(ConfigEntityInterface $model): ConfigEntityInterface;

  /**
   * Exports the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The exported model as the response.
   */
  public function export(ConfigEntityInterface $model): Response;

  /**
   * Set label of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $label
   *   The label.
   *
   * @return $this
   */
  public function setLabel(ConfigEntityInterface $model, string $label): ModelOwnerInterface;

  /**
   * Get label from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The label.
   */
  public function getLabel(ConfigEntityInterface $model): string;

  /**
   * Set status of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param bool $status
   *   The status.
   *
   * @return $this
   */
  public function setStatus(ConfigEntityInterface $model, bool $status): ModelOwnerInterface;

  /**
   * Get status from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return bool
   *   The status.
   */
  public function getStatus(ConfigEntityInterface $model): bool;

  /**
   * Set version of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $version
   *   The version.
   *
   * @return $this
   */
  public function setVersion(ConfigEntityInterface $model, string $version): ModelOwnerInterface;

  /**
   * Get version from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The version.
   */
  public function getVersion(ConfigEntityInterface $model): string;

  /**
   * Set storage of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $storage
   *   The storage.
   *
   * @return $this
   */
  public function setStorage(ConfigEntityInterface $model, string $storage): ModelOwnerInterface;

  /**
   * Get storage setting from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The storage setting.
   */
  public function getStorage(ConfigEntityInterface $model): string;

  /**
   * Set documentation of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $documentation
   *   The documentation.
   *
   * @return $this
   */
  public function setDocumentation(ConfigEntityInterface $model, string $documentation): ModelOwnerInterface;

  /**
   * Get documentation from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The documentation.
   */
  public function getDocumentation(ConfigEntityInterface $model): string;

  /**
   * Set Tags of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param array $tags
   *   The tags.
   *
   * @return $this
   */
  public function setTags(ConfigEntityInterface $model, array $tags): ModelOwnerInterface;

  /**
   * Get Tags from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return array
   *   The tags.
   */
  public function getTags(ConfigEntityInterface $model): array;

  /**
   * Set changelog of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $changelog
   *   The changelog.
   *
   * @return $this
   */
  public function setChangelog(ConfigEntityInterface $model, string $changelog): ModelOwnerInterface;

  /**
   * Get changelog from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The changelog.
   */
  public function getChangelog(ConfigEntityInterface $model): string;

  /**
   * Set annotations of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\Component[] $annotations
   *   The annotations.
   *
   * @return $this
   */
  public function setAnnotations(ConfigEntityInterface $model, array $annotations): ModelOwnerInterface;

  /**
   * Get annotations from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Component[]
   *   The annotations.
   */
  public function getAnnotations(ConfigEntityInterface $model): array;

  /**
   * Set colors of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\ComponentColor[] $colors
   *   The annotations.
   *
   * @return $this
   */
  public function setColors(ConfigEntityInterface $model, array $colors): ModelOwnerInterface;

  /**
   * Get colors from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\ComponentColor[]
   *   The colors.
   */
  public function getColors(ConfigEntityInterface $model): array;

  /**
   * Set swimlanes of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param array $swimlanes
   *   The swimlanes.
   *
   * @return $this
   */
  public function setSwimlanes(ConfigEntityInterface $model, array $swimlanes): ModelOwnerInterface;

  /**
   * Get swimlanes from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return array
   *   The swimlanes.
   */
  public function getSwimlanes(ConfigEntityInterface $model): array;

  /**
   * Set raw data of the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $data
   *   The model data.
   *
   * @return $this
   */
  public function setModelData(ConfigEntityInterface $model, string $data): ModelOwnerInterface;

  /**
   * Get raw data from the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The raw data.
   */
  public function getModelData(ConfigEntityInterface $model): string;

  /**
   * Sets the modeler plugin ID to the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param string $id
   *   The modeler ID.
   *
   * @return $this
   */
  public function setModelerId(ConfigEntityInterface $model, string $id): ModelOwnerInterface;

  /**
   * Gets the modeler plugin ID that edited the model.
   *
   * @return string
   *   The modeler plugin ID.
   */
  public function getModelerId(ConfigEntityInterface $model): string;

  /**
   * Gets the modeler plugin that edited the model.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerInterface|null
   *   The modeler plugin, if it can be found, NULL otherwise.
   */
  public function getModeler(ConfigEntityInterface $model): ?ModelerInterface;

  /**
   * Gets the list of used components in the model.
   *
   * This method should be called by modelers to receive all the used
   * components. The base model owner class implements this as final. Model
   * owners should instead implement the self::usedComponents() method.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Component[]
   *   The list of used components.
   */
  public function getUsedComponents(ConfigEntityInterface $model): array;

  /**
   * Gets the list of used components in the model.
   *
   * This needs to be implemented by model owners, but it shouldn't be called
   * by modelers. Instead they should call self::getUsedComponents().
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Component[]
   *   The list of used components.
   */
  public function usedComponents(ConfigEntityInterface $model): array;

  /**
   * Gets the list of supported component types.
   *
   * @return array
   *   The list of supported component types with the modeler API component
   *   type as they key and a unique name as the value.
   */
  public function supportedOwnerComponentTypes(): array;

  /**
   * Provides a list of available plugins for a given type.
   *
   * @param int $type
   *   The component type.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface[]
   *   The list of plugins.
   */
  public function availableOwnerComponents(int $type): array;

  /**
   * Provides an ID of an owner component for a given type.
   *
   * @param int $type
   *   The component type.
   *
   * @return string
   *   The owner component's ID.
   */
  public function ownerComponentId(int $type): string;

  /**
   * Provides the default config of an owner component for a given type and ID.
   *
   * @param int $type
   *   The component type.
   * @param string $id
   *   The component ID.
   *
   * @return array
   *   The default config.
   */
  public function ownerComponentDefaultConfig(int $type, string $id): array;

  /**
   * Tells the modeler if the given plugin can be edited in the UI.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   *
   * @return bool
   *   TRUE, if the plugin is editable, FALSE otherwise.
   */
  public function ownerComponentEditable(PluginInspectionInterface $plugin): bool;

  /**
   * Tells the modeler if the given plugin can be removed.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   *
   * @return bool
   *   TRUE, if the plugin can be removed and then replaced by another one,
   *   FALSE otherwise.
   */
  public function ownerComponentPluginChangeable(PluginInspectionInterface $plugin): bool;

  /**
   * Provides the owner component for a given type and ID.
   *
   * @param int $type
   *   The component type.
   * @param string $id
   *   The component ID.
   * @param array $config
   *   The plugin configuration.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface|null
   *   The owner component, or NULL if it can't be found.
   */
  public function ownerComponent(int $type, string $id, array $config = []): ?PluginInspectionInterface;

  /**
   * Prepares the plugin's configuration form and catches errors.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   * @param string|null $modelId
   *   (Optional) The ID of the model entity for which the plugin config form
   *   should be built.
   * @param bool $modelIsNew
   *   (Optional) Flag to indicate if the model entity for which the plugin
   *   config form should be built is new or not.
   *
   * @return array
   *   The configuration form.
   */
  public function buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId = NULL, bool $modelIsNew = TRUE): array;

  /**
   * Provides the owner component for a given type and ID.
   *
   * @param int $type
   *   The component type.
   * @param string $id
   *   The component ID.
   *
   * @return bool
   *   TRUE, if the plugin's configuration should not be validated, FALSE
   *   otherwise.
   */
  public function skipConfigurationValidation(int $type, string $id): bool;

  /**
   * Provides the optional base URL to the offsite documentation.
   *
   * @return string|null
   *   The base URL to the offsite documentation, or NULL if no URL is
   *   configured.
   */
  public function docBaseUrl(): ?string;

  /**
   * Builds the URL to the offsite documentation for the given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin for which the documentation URL should be build.
   * @param string $pluginType
   *   The string identifying the plugin type, which is one of event, condition
   *   or action.
   *
   * @return string|null
   *   The URL to the offsite documentation, or NULL if no URL was generated.
   */
  public function pluginDocUrl(PluginInspectionInterface $plugin, string $pluginType): ?string;

  /**
   * Get the storage method from settings.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string|null
   *   The storage method, if the required modeler is not "fallback", NULL
   *   otherwise.
   */
  public function storageMethod(ConfigEntityInterface $model): ?string;

  /**
   * Get the storage ID for separate storage.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string
   *   The storage ID.
   */
  public function storageId(ConfigEntityInterface $model): string;

  /**
   * Allows model owner to prepare submitted config values.
   *
   * @param string|null $value
   *   The submitted config value.
   * @param string|null $replacement
   *   Variable may receive a replacement value which will only be used during
   *   validation and replaced back to the original value after validation.
   * @param array $element
   *   The form element.
   *
   * @return string|null
   *   If the preparation found an error before form validation, an error
   *   message should be return, NULL otherwise.
   */
  public function prepareFormFieldForValidation(?string &$value, ?string &$replacement, array $element): ?string;

  /**
   * Reset components in a model to add all current components afterwards.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface
   *   $this
   */
  public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface;

  /**
   * Add a component to the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\Component $component
   *   The component.
   *
   * @return bool
   *   TRUE, if the components was added successfully, FALSE otherwise.
   */
  public function addComponent(ConfigEntityInterface $model, Component $component): bool;

  /**
   * Helper function called after the last component has been added.
   *
   * This is only being called, if adding component happened without any errors
   * and if Api::prepareModelFromData() wasn't called in dry run mode.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   */
  public function finalizeAddingComponents(ConfigEntityInterface $model): void;

  /**
   * Update a component in the model.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   * @param \Drupal\modeler_api\Component $component
   *   The component.
   *
   * @return bool
   *   TRUE, if the components was updated successfully, FALSE otherwise.
   */
  public function updateComponent(ConfigEntityInterface $model, Component $component): bool;

  /**
   * Provides a list of strings containing infos about used components.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $model
   *   The model.
   *
   * @return string[]
   *   The list of infos.
   */
  public function usedComponentsInfo(ConfigEntityInterface $model): array;

  /**
   * Gets the default storage method for this model owner.
   *
   * @return string
   *   The default storage method for this model owner.
   */
  public function defaultStorageMethod(): string;

  /**
   * Flag whether the default storage is enforced.
   *
   * @return bool
   *   TRUE, if the storage method should not be changed for this model owner.
   *   Defaults to FALSE, so the user can decide.
   */
  public function enforceDefaultStorageMethod(): bool;

  /**
   * Determines if the model supports status.
   *
   * @return bool
   *   TRUE, if the model supports status, FALSE otherwise.
   */
  public function supportsStatus(): bool;

}
