<?php

namespace Drupal\eca_ui\Plugin\ModelerApiModelOwner;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Entity\Eca as EcaModel;
use Drupal\eca\Service\Actions;
use Drupal\eca\Service\Conditions;
use Drupal\eca\Service\Events;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\ModelOwner;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Form\Settings;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Model owner plugins implementation for ECA.
 */
#[ModelOwner(
  id: "eca",
  label: new TranslatableMarkup("ECA"),
  description: new TranslatableMarkup("Configure ECA - Events, Conditions, Actions.")
)]
class Eca extends ModelOwnerBase {

  public const array SUPPORTED_COMPONENT_TYPES = [
    Api::COMPONENT_TYPE_START => 'event',
    Api::COMPONENT_TYPE_LINK => 'condition',
    Api::COMPONENT_TYPE_ELEMENT => 'action',
    Api::COMPONENT_TYPE_GATEWAY => 'gateway',
  ];

  /**
   * Dependency Injection container.
   *
   * Used for getter injection.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
   */
  protected ?ContainerInterface $container;

  /**
   * ECA events service.
   *
   * @var \Drupal\eca\Service\Events
   */
  protected Events $eventsService;

  /**
   * ECA conditions service.
   *
   * @var \Drupal\eca\Service\Conditions
   */
  protected Conditions $conditionsService;

  /**
   * ECA actions service.
   *
   * @var \Drupal\eca\Service\Actions
   */
  protected Actions $actionsService;

  /**
   * The documentation domain.
   *
   * @var string|null
   */
  protected ?string $documentationDomain;

  /**
   * {@inheritdoc}
   */
  public function modelIdExistsCallback(): array {
    return [EcaModel::class, 'load'];
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityProviderId(): string {
    return 'eca';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityTypeId(): string {
    return 'eca';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityBasePath(): string {
    return 'admin/config/workflow/eca';
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(): ?string {
    return '\Drupal\eca_ui\Form\Settings';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultStorageMethod(): string {
    return Settings::STORAGE_OPTION_SEPARATE;
  }

  /**
   * Get Dependency Injection container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   Current Dependency Injection container.
   */
  protected function getContainer(): ContainerInterface {
    if (!isset($this->container)) {
      // @phpstan-ignore-next-line
      $this->container = \Drupal::getContainer();
    }
    return $this->container;
  }

  /**
   * Get the ECA event service.
   *
   * @return \Drupal\eca\Service\Events
   *   The ECA event service.
   */
  protected function eventsService(): Events {
    if (!isset($this->eventsService)) {
      $this->eventsService = $this->getContainer()->get('eca.service.event');
    }
    return $this->eventsService;
  }

  /**
   * Get the ECA condition service.
   *
   * @return \Drupal\eca\Service\Conditions
   *   The ECA condition service.
   */
  protected function conditionsService(): Conditions {
    if (!isset($this->conditionsService)) {
      $this->conditionsService = $this->getContainer()->get('eca.service.condition');
    }
    return $this->conditionsService;
  }

  /**
   * Get the ECA action service.
   *
   * @return \Drupal\eca\Service\Actions
   *   The ECA action service.
   */
  protected function actionsService(): Actions {
    if (!isset($this->actionsService)) {
      $this->actionsService = $this->getContainer()->get('eca.service.action');
    }
    return $this->actionsService;
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponents(ConfigEntityInterface $model): array {
    assert($model instanceof EcaModel);
    $components = [];
    foreach (self::SUPPORTED_COMPONENT_TYPES as $type => $typeString) {
      foreach ($model->get($typeString . 's') ?? [] as $id => $item) {
        $successors = [];
        foreach ($item['successors'] ?? [] as $successor) {
          $successors[] = new ComponentSuccessor($successor['id'], $successor['condition']);
        }
        $components[] = new Component(
          $this,
          $id,
          $type,
          $item['plugin'] ?? '',
          $item['label'] ?? '',
          $item['configuration'] ?? [],
          $successors,
        );
      }
    }
    return $components;
  }

  /**
   * {@inheritdoc}
   */
  public function supportedOwnerComponentTypes(): array {
    return self::SUPPORTED_COMPONENT_TYPES;
  }

  /**
   * {@inheritdoc}
   */
  public function availableOwnerComponents(int $type): array {
    return match($type) {
      Api::COMPONENT_TYPE_START => $this->eventsService()->events(),
      Api::COMPONENT_TYPE_LINK => $this->conditionsService()->conditions(),
      Api::COMPONENT_TYPE_ELEMENT => $this->actionsService()->actions(),
      default => [],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentId(int $type): string {
    return self::SUPPORTED_COMPONENT_TYPES[$type] ?? 'unsupported';
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponent(int $type, string $id, array $config = []): ?PluginInspectionInterface {
    return match($type) {
      Api::COMPONENT_TYPE_START => $this->eventsService()->createInstance($id, $config),
      Api::COMPONENT_TYPE_LINK => $this->conditionsService()->createInstance($id, $config),
      Api::COMPONENT_TYPE_ELEMENT => $this->actionsService()->createInstance($id, $config),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId = NULL, bool $modelIsNew = TRUE): array {
    $form_state = new FormState();
    try {
      if ($plugin instanceof ActionInterface) {
        $form = $this->actionsService()->getConfigurationForm($plugin, $form_state) ?? [
          'error_message' => [
            '#type' => 'markup',
            '#markup' => '<strong>' . $this->t('Error in configuration form!!!') . '</strong><br><br>' . $this->t('Details can be found in the Drupal error log.'),
          ],
        ];
      }
      elseif ($plugin instanceof PluginFormInterface) {
        $form = $plugin->buildConfigurationForm([], $form_state);
      }
      else {
        $form = [];
      }
    }
    catch (\Throwable $ex) {
      $form['error_message'] = [
        '#type' => 'markup',
        '#markup' => '<strong>' . $this->t('Error in configuration form!!!') . '</strong><br><br>' . $ex->getMessage(),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function skipConfigurationValidation(int $type, string $id): bool {
    return match($type) {
      Api::COMPONENT_TYPE_ELEMENT => in_array($id, ['action_send_email_action', 'node_assign_owner_action'], TRUE),
      default => FALSE,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function docBaseUrl(): ?string {
    if (!isset($this->documentationDomain)) {
      $this->documentationDomain = $this->getContainer()->getParameter('eca.default_documentation_domain') ?
        $this->getContainer()->get('config.factory')->get('eca.settings')->get('documentation_domain') :
        NULL;
    }
    return $this->documentationDomain;
  }

  /**
   * {@inheritdoc}
   */
  public function pluginDocUrl(PluginInspectionInterface $plugin, string $pluginType): ?string {
    if (!($domain = $this->docBaseUrl())) {
      return NULL;
    }
    $provider = $plugin->getPluginDefinition()['provider'];
    $basePath = (mb_strpos($provider, 'eca_') === 0) ?
      str_replace('eca_', 'eca/', $provider) :
      $provider;
    return sprintf('%s/plugins/%s/%ss/%s/', $domain, $basePath, $pluginType, str_replace([':'], '_', $plugin->getPluginId()));
  }

  /**
   * Checks if a given value has the patterns of a token.
   *
   * @param string $value
   *   The field value.
   *
   * @return bool
   *   Wether TRUE or FALSE based on the pattern.
   */
  protected function valueIsToken(string $value): bool {
    return (mb_substr($value, 0, 1) === '[') &&
      (mb_substr($value, -1, 1) === ']') &&
      (mb_strlen($value) <= 255);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormFieldForValidation(?string &$value, ?string &$replacement, array $element): ?string {
    $errorMsg = NULL;
    if (!empty($element['#eca_token_reference']) &&
      $value !== NULL &&
      $this->valueIsToken($value)
    ) {
      $errorMsg = 'This field requires a token name, not a token; please remove the brackets.';
    }
    if (!empty($element['#eca_token_select_option']) && isset($element['#options']) && is_array($element['#options']) && ($value === '_eca_token' || $value === '')) {
      // Remember the original configuration value.
      $replacement = $value;
      $value = (string) array_key_first($element['#options']);
    }
    if (isset($element['#type'], $value) &&
      in_array($element['#type'], ['number', 'email', 'machine_name'], TRUE) &&
      $this->valueIsToken($value)
    ) {
      // Remember the original configuration value.
      $replacement = $value;

      switch ($element['#type']) {

        case 'number':
          // Set a valid value for the form element type 'number'
          // to pass the validation. Also if the field is required
          // the value "0" would cause a form error, let's use "1" instead.
          $value = $element['#min'] ?? 1;
          break;

        case 'email':
          // Set a valid value for the form element type 'email'
          // to pass validation.
          $value = 'lorem@eca.local';
          break;

        case 'machine_name':
          // Set a valid value for the form element type 'machine_name'
          // to pass validation. Needs to append a random value, so that
          // it passes "exists" callbacks.
          $value = 'eca_' . mb_strtolower((new Random())->name(8, TRUE));
          break;

      }
    }
    if (isset($element['#type'], $value) && $element['#type'] === 'machine_name') {
      // Remember the original configuration value.
      $replacement = $value;
      $value = (string) str_replace('][', '', $value);
    }
    return $errorMsg;
  }

  /**
   * {@inheritdoc}
   */
  public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface {
    assert($model instanceof EcaModel);
    $model->resetComponents();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addComponent(ConfigEntityInterface $model, Component $component): bool {
    assert($model instanceof EcaModel);
    $id = $component->getId();
    $pluginId = $component->getPluginId();
    $label = $component->getLabel();
    $configuration = $component->getConfiguration();
    $successors = [];
    foreach ($component->getSuccessors() as $successor) {
      $successors[] = [
        'id' => $successor->getId(),
        'condition' => $successor->getConditionId(),
      ];
    }
    if (empty($label)) {
      $label = $id;
    }
    return match ($component->getType()) {
      Api::COMPONENT_TYPE_START => $model->addEvent($id, $pluginId, $label, $configuration, $successors),
      Api::COMPONENT_TYPE_LINK => $model->addCondition($id, $pluginId, $label, $configuration),
      Api::COMPONENT_TYPE_ELEMENT => $model->addAction($id, $pluginId, $label, $configuration, $successors),
      Api::COMPONENT_TYPE_GATEWAY => $model->addGateway($id, 0, $successors),
      default => FALSE,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentDefaultConfig(int $type, string $id): array {
    $plugin = $this->ownerComponent($type, $id);
    return $plugin instanceof ConfigurableInterface ?
      $plugin->defaultConfiguration() :
      [];
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponent(ConfigEntityInterface $model, Component $component): bool {
    // We can call the addComponent method here, because the component is add to
    // an id-keyed array, so this will override (i.e. update) the existing
    // component.
    return $this->addComponent($model, $component);
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponentsInfo(ConfigEntityInterface $model): array {
    assert($model instanceof EcaModel);
    return $model->getEventInfos();
  }

}
