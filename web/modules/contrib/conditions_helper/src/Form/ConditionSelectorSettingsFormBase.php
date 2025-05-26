<?php

declare(strict_types=1);

namespace Drupal\conditions_helper\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for configuring which condition plugins are available.
 *
 * Extend this form to create a settings page where users can select which
 * condition plugins are enabled for a specific feature. The selection is saved
 * to the configuration key specified by the subclass through
 * getEditableConfigNames(). The actual list of enabled condition IDs is saved
 * under the 'enabled_conditions' key within that configuration object.
 */
abstract class ConditionSelectorSettingsFormBase extends ConfigFormBase {

  /**
   * Config key for the list of enabled condition plugin IDs.
   *
   * @var string
   */
  protected const ENABLED_CONDITIONS_KEY = 'enabled_conditions';

  /**
   * The string translation service.
   *
   * This overrides the property from FormBase to ensure it's available for
   * the StringTranslationTrait.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs a new ConditionSelectorSettingsFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed configuration manager.
   * @param \Drupal\conditions_helper\Form\ConditionSelectorFormBuilder $conditionSelectorFormBuilder
   *   The conditions helper service for building selector form elements.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected ConditionSelectorFormBuilder $conditionSelectorFormBuilder,
    TranslationInterface $string_translation,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('conditions_helper.condition_selector_form_builder'),
      $container->get('string_translation'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Subclasses must implement this method to define their unique config
   * name(s). Example: `return ['my_module.settings'];`
   */
  abstract protected function getEditableConfigNames(): array;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Retrieve the configuration object specific to the consuming module.
    // The name of this config object is defined by the subclass using
    // getEditableConfigNames().
    $config_names = $this->getEditableConfigNames();
    $config = $this->config(reset($config_names));

    // Get the list of currently enabled/selected condition plugin IDs.
    // These are stored under a standardized key in the module's config.
    $default_selected_ids = $config->get(static::ENABLED_CONDITIONS_KEY) ?? [];

    // Define a scope identifier for the alter hook.
    // This helps make the alter hook more targeted. Using the form ID is a
    // reasonable default for uniqueness.
    $scope_identifier = $this->getFormId();

    // Use the ConditionSelectorFormBuilder service to get the FAPI elements
    // for selecting conditions. This typically returns a checkboxes element.
    $condition_selector_elements = $this->conditionSelectorFormBuilder
      ->buildSelectorFormElements($default_selected_ids, $scope_identifier);

    // Embed the selector elements into the form. Subclasses can add more
    // elements before or after this, or wrap it in their own fieldset if
    // desired.
    $form[static::ENABLED_CONDITIONS_KEY] = $condition_selector_elements;
    // Ensure #tree is TRUE if this key is part of a larger structure, though
    // buildSelectorFormElements already returns a self-contained element.
    $form[static::ENABLED_CONDITIONS_KEY]['#tree'] = TRUE;

    // Allow subclasses to add their own specific settings to the form.
    // The parent::buildForm() call handles standard ConfigFormBase elements
    // like submit buttons.
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Retrieve the submitted values for the enabled conditions.
    // The form state values are already filtered, so we directly get them.
    $submitted_values = $form_state->getValue(static::ENABLED_CONDITIONS_KEY);

    // Filter out any unselected conditions (checkboxes returns 0 for
    // unchecked). array_filter removes FALSE, NULL, 0, and empty string values
    // by default.
    $enabled_conditions = array_filter($submitted_values);

    // Save the filtered list of enabled condition IDs to the module's config.
    $config_names = $this->getEditableConfigNames();
    $this->config(reset($config_names))
      ->set(static::ENABLED_CONDITIONS_KEY, $enabled_conditions)
      ->save();

    // Call the parent submitForm to handle messages, etc.
    parent::submitForm($form, $form_state);
  }

  /**
   * Get the key used to store the list of enabled conditions.
   *
   * @return string
   */
  public static function getEnabledConditionsKey(): string {
    return static::ENABLED_CONDITIONS_KEY;
  }

}
