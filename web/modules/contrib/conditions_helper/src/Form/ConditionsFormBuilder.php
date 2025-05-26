<?php

declare(strict_types=1);

namespace Drupal\conditions_helper\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds and processes forms for configuring condition plugins.
 *
 * This service is responsible for generating the FAPI elements required to
 * configure a set of selected condition plugins. It handles subforms for each
 * condition and manages context mapping where applicable.
 */
class ConditionsFormBuilder {

  use StringTranslationTrait;
  use ContextAwarePluginAssignmentTrait;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The conditions that are available to be selected.
   *
   * @var \Drupal\Core\Condition\ConditionInterface[]
   */
  protected array $conditions = [];

  /**
   * Constructs a new ConditionsFormBuilder object.
   *
   * @param \Drupal\Core\Condition\ConditionManager $conditionManager
   *   The condition plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $contextHandler
   *   The context handler service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    protected ConditionManager $conditionManager,
    protected ContextHandlerInterface $contextHandler,
    protected ConfigFactoryInterface $configFactory,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Get the available conditions for a given config name.
   *
   * @param string $config_name
   *   The config name to get the available conditions for.
   *
   * @return \Drupal\Core\Condition\ConditionInterface[]
   */
  public function getAvailableConditions(string $config_name): array {
    // Get the condition plugin manager and load all the available conditions.
    $all_conditions = $this->conditionManager->getDefinitions();

    // Filter conditions based on admin settings.
    $enabled_conditions = $this->configFactory
      ->get($config_name)
      ->get(ConditionSelectorSettingsFormBase::getEnabledConditionsKey()) ?: [];

    // If we have enabled conditions defined, filter by them.
    if (!empty($enabled_conditions)) {
      $this->conditions = array_intersect_key($all_conditions, array_flip($enabled_conditions));
    }
    else {
      // If no settings exist yet, use all conditions.
      $this->conditions = $all_conditions;
    }

    return $this->conditions;
  }

  /**
   * Builds the configuration forms for a given set of condition plugins.
   *
   * This method iterates through selected condition plugins, builds their
   * individual configuration forms as subforms, and includes context mapping
   * UI for context-aware plugins.
   *
   * @param array &$form
   *   The parent form array to which the condition forms will be added.
   *   This is passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The main form state.
   * @param array $available_conditions
   *   An array where keys are condition plugin IDs and values are their current
   *   configurations.
   *   Example: `['user_role' => ['roles' => ['administrator']]]`.
   * @param array $available_contexts
   *   An array of \Drupal\Core\Plugin\Context\ContextInterface objects that are
   *   available for mapping in this specific configuration scenario.
   * @param array $stored_values
   *   An array of values that are stored for the conditions.
   */
  public function buildConditionsForm(array &$form, FormStateInterface $form_state, array $available_conditions, array $available_contexts, array $stored_values = []): void {
    // Get the total count for the '#open' logic before using array_walk.
    $total_available_plugins = count($available_conditions);

    // Iterate over each selected plugin configuration to build its form.
    array_walk(
      $available_conditions,
      function (&$plugin_config_item, $plugin_id_item) use (&$form, $form_state, $available_contexts, $total_available_plugins, $stored_values): void {
        // Get the stored value for this condition.
        $condition_values = $stored_values[$plugin_id_item] ?? [
          'id' => $plugin_id_item,
        ];

        // Create an instance of the condition plugin.
        /** @var \Drupal\Core\Condition\ConditionInterface|\Drupal\Core\Plugin\ContextAwarePluginInterface $instance */
        $instance = $this->conditionManager->createInstance($plugin_id_item, $condition_values);

        // Each condition plugin will have its own subform within a details
        // element.
        $instance_form = [
          '#type' => 'details',
          '#title' => $instance->getPluginDefinition()['label'],
          // Open the details element if it has configuration or is the only
          // one.
          '#open' => !empty($condition_values) || $total_available_plugins === 1,
        ];

        $form[$plugin_id_item] = [];

        // Create a subform state for the current condition plugin.
        $subform_state = SubformState::createForSubform($form[$plugin_id_item], $form, $form_state);

        // Build the plugin's specific configuration form.
        $form[$plugin_id_item] = $instance->buildConfigurationForm($instance_form, $subform_state);

        // If the plugin is context-aware, add the context mapping elements.
        if ($instance instanceof ContextAwarePluginInterface) {
          $form[$plugin_id_item]['context_mapping'] = $this->addContextAssignmentElement($instance, $available_contexts);
        }
      }
    );

    // It is the responsibility of the consuming form (the one calling this
    // service) to add appropriate #validate and #submit handlers. Validation
    // is optional, but submit handlers should call the submitConditionsForm()
    // method of this service.
  }

  /**
   * Submit callback for the form being submitted.
   *
   * @param array $form
   *   The form being altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $form_parents
   *   The parents of the subform being submitted.
   */
  public function submitConditionsForm(array &$form, FormStateInterface $form_state, array $form_parents = []): void {
    $values = $form_state->getValue($form_parents);

    // Loop through all the available conditions.
    array_walk($values, function ($condition, $id) use (&$form, $form_state, $values, $form_parents) {
      // Get the submitted values for this condition.
      $condition_values = $values[$id];

      // If the condition is not enabled, skip it.
      if (empty($condition_values)) {
        return;
      }

      // Build an instance from the values so we can submit the subform.
      $instance = $this->conditionManager->createInstance($id, $condition_values);

      $id_form_parents = array_merge($form_parents, [$id]);

      // Build a subform state for just this condition.
      $subform_state = SubformState::createForSubform(
        NestedArray::getValue($form, $id_form_parents),
        $form,
        $form_state
      );

      // If this condition relies on contexts, grab those.
      if ($instance instanceof ContextAwarePluginInterface && $instance->getContextDefinitions()) {
        $context_mapping = $subform_state->getValue('context_mapping', []);
        $instance->setContextMapping($context_mapping);
      }

      // Submit this condition's form.
      $instance->submitConfigurationForm($form, $subform_state);

      // Set the form values to be the process instance configuration.
      $form_state->setValue(
        $id_form_parents,
        $instance->getConfiguration()
      );
    });
  }

  /**
   * Gets the ConditionManager service.
   *
   * This allows consuming forms to access the condition manager, for instance,
   * to create plugin instances during validation or submission if needed,
   * though typically direct interaction is minimized by this service.
   *
   * @return \Drupal\Core\Condition\ConditionManager
   *   The condition plugin manager service.
   */
  public function getConditionManager(): ConditionManager {
    return $this->conditionManager;
  }

}
