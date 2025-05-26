<?php

declare(strict_types=1);

namespace Drupal\conditions_helper\Form;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a service to build form elements for selecting condition plugins.
 *
 * This service helps modules create a UI where users can choose which condition
 * plugins should be available for a particular feature.
 */
class ConditionSelectorFormBuilder {

  use StringTranslationTrait;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs a new ConditionSelectorFormBuilder object.
   *
   * @param \Drupal\Core\Condition\ConditionManager $conditionManager
   *   The condition plugin manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    protected ConditionManager $conditionManager,
    TranslationInterface $string_translation,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Builds a FAPI array for selecting available condition plugins.
   *
   * This method generates form elements (typically checkboxes) that allow
   * users to select from a list of all discoverable condition plugins.
   * An alter hook is invoked to allow other modules to modify the list of
   * definitions before the form elements are built.
   *
   * @param array $default_selected_ids
   *   An array of condition plugin IDs that should be selected by default.
   * @param string $scope_identifier
   *   A string identifier for the context in which this selector is being
   *   built. This is passed to the alter hook to allow for targeted
   *   alterations by other modules.
   *
   * @return array
   *   A FAPI array representing the condition selection UI. This is typically
   *   a fieldset containing checkboxes.
   */
  public function buildSelectorFormElements(array $default_selected_ids = [], string $scope_identifier = 'default'): array {
    // Get all available condition plugin definitions from the manager.
    $definitions = $this->conditionManager->getDefinitions();

    // Allow other modules to alter the definitions for this specific scope.
    // This hook allows for adding, removing, or modifying condition plugin
    // definitions before they are presented to the user for selection.
    $this->moduleHandler->alter('conditions_helper_selector_definitions', $definitions, $scope_identifier);

    // Prepare options for the checkboxes element.
    // The keys are plugin IDs, and values are their labels.
    $options = [];
    // We use array_walk here as per coding standards preference.
    array_walk($definitions, function ($definition, $plugin_id) use (&$options): void {
      if (isset($definition['label'])) {
        $options[$plugin_id] = $definition['label'];
      }
    });

    // Sort options by label for better user experience.
    // The uasort function is used to sort an array with a user-defined
    // comparison function and maintain index association.
    uasort($options, function ($a, $b) {
      return strnatcasecmp((string) $a, (string) $b);
    });

    // Build the FAPI array for the selection elements.
    $form_elements = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available conditions'),
      '#description' => $this->t(
        'Select the conditions that should be available for this feature.'
      ),
      '#options' => $options,
      '#default_value' => $default_selected_ids,
    ];

    return $form_elements;
  }

  /**
   * Gets all condition plugin definitions, after applying alterations.
   *
   * This method provides access to the list of condition definitions that
   * would be used for selection, including any modifications made by
   * the `hook_conditions_helper_selector_definitions_alter()` hook.
   *
   * @param string $scope_identifier
   *   A string identifier for the context. This is passed to the alter hook.
   *
   * @return array
   *   An array of condition plugin definitions, keyed by plugin ID.
   */
  public function getAllConditionPluginDefinitions(string $scope_identifier = 'default'): array {
    // Get all available condition plugin definitions from the manager.
    $definitions = $this->conditionManager->getDefinitions();

    // Allow other modules to alter the definitions.
    $this->moduleHandler->alter('conditions_helper_selector_definitions', $definitions, $scope_identifier);

    // Sort definitions by label before returning, for consistency if displayed.
    uasort($definitions, function ($a, $b) {
      // Ensure 'label' is a string for comparison, default to empty string if
      // not set.
      $label_a = isset($a['label']) ? (string) $a['label'] : '';
      $label_b = isset($b['label']) ? (string) $b['label'] : '';
      return strnatcasecmp($label_a, $label_b);
    });

    return $definitions;
  }

}
