# Conditions Helper

## Table of Contents

- [Introduction](#introduction)
- [Purpose](#purpose)
- [Provided Services](#provided-services)
  - [ConditionSelectorFormBuilder (`conditions_helper.condition_selector_form_builder`)](#conditionselectorformbuilder)
  - [ConditionsFormBuilder (`conditions_helper.form_builder`)](#conditionsformbuilder)
  - [ConditionsEvaluator (`conditions_helper.evaluator`)](#conditionsevaluator)
- [Provided Base Classes](#provided-base-classes)
  - [ConditionSelectorSettingsFormBase](#conditionselectorsettingsformbase)
  - [ConditionsFormBase (Optional)](#conditionsformbase-optional)
- [Installation](#installation)
- [Usage Examples](#usage-examples)
  - [Example 1: Creating a Settings Form to Select Available Conditions](#example-1)
  - [Example 2: Building a Form to Configure Selected Conditions](#example-2)
  - [Example 3: Evaluating Configured Conditions](#example-3)
- [Defining Configuration Schema](#defining-configuration-schema)
  - [Schema for ConditionSelectorSettingsFormBase](#schema-for-conditionselectorsettingsformbase)
  - [Schema for Storing Condition Configurations](#schema-for-storing-condition-configurations)
- [Alter Hooks](#alter-hooks)
  - [`hook_conditions_helper_selector_definitions_alter`](#hook_conditions_helper_selector_definitions_alter)
- [Dependencies](#dependencies)
- [Maintainers](#maintainers)

## Introduction

The Conditions Helper module is an API-only module designed to simplify the
integration and utilization of Drupal Core's Conditions API for developers.
It provides a set of services and base classes to reduce boilerplate and
complexity when adding condition-based logic to other modules.

This module does not provide any UI or end-user features on its own. Instead,
it empowers other modules to easily build UIs for selecting and configuring
conditions, and to evaluate these conditions in their own specific contexts.

## Purpose

The primary goal of this module is to make it easier for developers to:

- Create settings forms where administrators can select which Condition plugins
  are relevant or available for a specific feature.
- Build forms where users can configure instances of these selected Condition
  plugins (including context mapping for context-aware conditions).
- Evaluate a set of configured conditions to control access, visibility, or
  other conditional logic within their modules.

## Provided Services

Detailed explanations of each service and its methods will be provided here.

### ConditionSelectorFormBuilder (`conditions_helper.condition_selector_form_builder`)

Service ID: `conditions_helper.condition_selector_form_builder`
Class: `Drupal\conditions_helper\Form\ConditionSelectorFormBuilder`

This service helps build form elements (typically checkboxes) for selecting
which condition plugins are available for a given feature. It includes an alter
hook (`hook_conditions_helper_selector_definitions_alter`) to allow modification
of the condition definitions list.

Key Methods:
- `buildSelectorFormElements(array $default_selected_ids = [], string $scope_identifier = 'default'): array`
- `getAllConditionPluginDefinitions(string $scope_identifier = 'default'): array`

### ConditionsFormBuilder (`conditions_helper.form_builder`)

Service ID: `conditions_helper.form_builder`
Class: `Drupal\conditions_helper\Form\ConditionsFormBuilder`

This service is responsible for constructing the detailed configuration forms
for a collection of active condition plugins. It handles subform creation for
each plugin and integrates context mapping UI for context-aware plugins.

Key Methods:
- `buildConditionsForm(array &$form, FormStateInterface $form_state, array $selected_plugins_configurations, array $available_contexts, string $conditions_key = 'conditions'): void`

### ConditionsEvaluator (`conditions_helper.evaluator`)

Service ID: `conditions_helper.evaluator`
Class: `Drupal\conditions_helper\ConditionsEvaluator`

This service evaluates a set of configured conditions, taking into account
their context requirements and AND/OR logic.

Key Methods:
- `evaluateConditions(array $configured_conditions, bool $all_must_pass = TRUE, array $additional_contexts = []): bool`

## Provided Base Classes

Detailed explanations of base classes will be provided here.

### ConditionSelectorSettingsFormBase

Class: `Drupal\conditions_helper\Form\ConditionSelectorSettingsFormBase`

An abstract `ConfigFormBase` descendant that provides a nearly turn-key
solution for creating settings forms where administrators select which condition
plugins are available for a consuming module's feature. It uses the
`conditions_helper.condition_selector_form_builder` service and handles common
config loading/saving to the standardized `'enabled_conditions'` key within the
module's configuration object.

### ConditionsFormBase (Optional)

Class: `Drupal\conditions_helper\Form\ConditionsFormBase`

An optional abstract `FormBase` descendant for forms that need to embed the
detailed configuration UI for selected/active condition instances. It provides
basic dependency injection for the `conditions_helper.form_builder` service.

## Installation

Standard module installation: `composer require drupal/conditions_helper` (once
available on Drupal.org) and then enable the module via the Drupal UI or Drush
(`drush en conditions_helper`).

## Usage Examples

Code examples demonstrating how to use the services and base classes will be
added here.

### Example 1: Creating a Settings Form to Select Available Conditions

(Using `ConditionSelectorSettingsFormBase`)

To create a settings form where administrators can choose which condition plugins
are available for your module's feature, extend the
`ConditionSelectorSettingsFormBase` class.

```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Form;

use Drupal\conditions_helper\Form\ConditionSelectorSettingsFormBase;

/**
 * Configure available conditions for My Module's feature.
 */
class MyModuleConditionsSettingsForm extends ConditionSelectorSettingsFormBase {

  /**
   * Config settings name.
   *
   * @var string
   */
  public const SETTINGS = 'my_module_conditions.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'my_module_conditions_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [static::SETTINGS];
  }

}
```

### Example 2: Building a Form to Configure Selected Conditions

(Using the `conditions_helper.form_builder` service)

This example shows how to embed condition configuration forms into a custom
form, perhaps for configuring conditions on a specific entity or page.

```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Form;

use Drupal\conditions_helper\Form\ConditionsFormBuilder as ConditionsFormBuilderService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to configure conditions for a specific item.
 */
class MyItemConditionsConfigurationForm extends FormBase {

  protected ConditionsFormBuilderService $conditionsFormBuilder;
  protected ContextRepositoryInterface $contextRepository;

  /**
   * Constructs a new MyItemConditionsConfigurationForm.
   */
  public function __construct(
    ConditionsFormBuilderService $conditions_form_builder,
    ContextRepositoryInterface $context_repository
  ) {
    $this->conditionsFormBuilder = $conditions_form_builder;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('conditions_helper.form_builder'),
      $container->get('context.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'my_module_item_conditions_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $item_id = NULL): array {
    // In a real scenario, you would load existing configurations for $item_id.
    // $selected_plugins_configurations would typically come from config or be derived
    // from the settings form (Example 1).
    // For this example, let's assume 'user_role' is an active plugin.
    $selected_plugins_configurations = [
      'user_role' => $form_state->getValue(['conditions', 'user_role'], []) ?? [],
      // Add other selected plugins and their current configs here.
    ];

    // Get available contexts for mapping.
    // These are the contexts that your specific feature can provide to conditions.
    $available_contexts = $this->contextRepository->getAvailableContexts();
    // You might filter or add specific contexts relevant to $item_id.

    $form['item_id'] = ['#type' => 'value', '#value' => $item_id];

    $form['conditions'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $available_conditions = $this->conditionsHelperFormBuilder->getAvailableConditions(MyModuleConditionsSettingsForm::SETTINGS);
    $available_contexts = $this->contextRepository->getAvailableContexts();
    $stored_values = $form_state->getValue($conditions, []);
    $this->conditionsHelperFormBuilder->buildConditionsForm($form['conditions'], $form_state, $available_conditions, $available_contexts, $stored_values);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save conditions'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Call submitConfigurationForm() on each plugin and save the configurations.
    $conditions_values = $form_state->getValue('conditions', []);
    $this->conditionsHelperFormBuilder->submitConditionsForm($form, $form_state, ['conditions']);
  }

}
```

### Example 3: Evaluating Configured Conditions

(Using the `conditions_helper.evaluator` service)

This example shows how to use the ConditionsEvaluator service to determine if a
set of configured conditions are met.

```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Service;

use Drupal\conditions_helper\ConditionsEvaluator;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
// Assuming you have some way to provide specific contexts, e.g., the current node.
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Entity\EntityInterface;

/**
 * Service that checks access based on configured conditions.
 */
class MyModuleAccessChecker {

  protected ConditionsEvaluator $conditionsEvaluator;
  protected ContextRepositoryInterface $contextRepository;

  /**
   * Constructs a new MyModuleAccessChecker.
   */
  public function __construct(
    ConditionsEvaluator $conditions_evaluator,
    ContextRepositoryInterface $context_repository
  ) {
    $this->conditionsEvaluator = $conditions_evaluator;
    $this->contextRepository = $context_repository;
  }

  /**
   * Checks if a user has access based on conditions for a given item.
   */
  public function checkAccess(string $item_id, AccountInterface $account, ?EntityInterface $current_node = NULL): bool {
    // Load the configured conditions for $item_id.
    // This would typically come from a configuration object.
    // Example: $config = $this->configFactory->get('my_module.item.' . $item_id)->get('conditions');
    $configured_conditions = [
      'user_role' => [
        'id' => 'user_role',
        'roles' => ['authenticated' => 'authenticated'],
        'negate' => FALSE,
        'context_mapping' => ['user' => '@user.current_user_context'],
      ],
      // Example: Current node is of type 'article'
      'node_type' => [
        'id' => 'node_type',
        'bundles' => ['article' => 'article'],
        'negate' => FALSE,
        'context_mapping' => ['node' => '@node.node_route_context:node'], // Or custom context
      ],
    ];

    return $this->conditionsEvaluator->evaluateConditions($configured_conditions);
  }
  }

}
```

## Defining Configuration Schema

When you save configurations related to conditions, it's important to define
their structure in your module's `config/schema/your_module_name.schema.yml`
file. This allows Drupal's configuration system to understand and manage your
data correctly.

### Schema for ConditionSelectorSettingsFormBase

If you use `ConditionSelectorSettingsFormBase` as shown in Example 1, it saves
the list of enabled condition plugin IDs to the `enabled_conditions` key within
the configuration object you specify (e.g., `my_module.settings`).

Here's an example schema for `my_module.schema.yml`:

```yaml
# File: my_module/config/schema/my_module.schema.yml

my_module.settings:
  type: config_object
  label: 'My Module settings'
  mapping:
    enabled_conditions:
      type: sequence
      label: 'Enabled condition plugin IDs'
      sequence:
        type: string
        label: 'Condition plugin ID'
    # Add other settings for your module here...
```

### Schema for Storing Condition Configurations

When you use the `ConditionsFormBuilder` service and save the resulting array of
configured condition instances (as conceptualized in Example 2 and 3), you need
to define a schema for this data.

A robust and Drupal-idiomatic way to do this is to store the conditions as a
mapping where each key is the condition plugin ID and the value is its specific
configuration. This allows you to leverage the condition plugins' own schema
definitions dynamically.

**Data Structure Expectation:**
For this schema approach to work best, the data you save for conditions should
look like this (notice it's a mapping by plugin ID, not a sequence):

```php
// Example of $final_configurations in PHP to be saved:
$final_configurations = [
  'user_role' => [
    // 'id' => 'user_role', // The ID is now the key, not usually repeated inside
    'negate' => false,
    'context_mapping' => ['user' => '@user.current_user_context'],
    'roles' => ['authenticated' => 'authenticated'], // Plugin-specific
  ],
  'node_type' => [
    // 'id' => 'node_type',
    'negate' => false,
    'context_mapping' => ['node' => '@node.node_route_context:node'],
    'bundles' => ['article' => 'article'], // Plugin-specific
  ],
  // ... more configured plugins
];
```

**Schema Definition Example:**

Let's assume you are saving conditions for various items, and the configuration
for each item is stored in `my_module.item.{item_id}.yml`, with a key `conditions`
holding the mapping of condition configurations.

```yaml
# File: my_module/config/schema/my_module.schema.yml

my_module.item.*:
  type: sequence
  label: 'My module item configuration'
  sequence:
    # The type should dynamically resolve to the schema of the specific
    # condition plugin.
    type: condition.plugin.[%key]
    label: 'Condition Plugin Configuration'
    # Note: If a specific plugin (e.g., 'some_custom_condition') does not
    # provide its own schema type discoverable as 'condition.plugin.some_custom_condition',
    # then schema validation for that particular plugin's settings might be
    # less strict or fall back to a generic mapping if the referenced type
    # is not found. For core conditions, this should generally work.
```

**Important Considerations:**

*   **Plugin Schemas:** This dynamic approach relies on individual condition
    plugins correctly defining and registering their own configuration schemas
    (e.g., a `user_role` condition plugin providing a schema type like
    `condition.plugin.user_role` that details its specific settings like `roles`,
    as well as common properties like `negate` and `context_mapping`).
*   **Data Structure:** Ensure the way you save the condition configurations in
    your module (e.g., in `submitForm` of Example 2) matches this mapping-by-plugin-ID
    structure. The `id` key within each condition's configuration array becomes
    redundant if the plugin ID is the key of the mapping.
*   **Fallback:** If a particular plugin ID used as a key does not have a
    corresponding `condition.plugin.[%key]` schema type defined and discoverable
    by Drupal, the configuration for that specific plugin might not be strictly
    validated beyond being a mapping. Well-behaved core and contrib plugins should
    provide these.

This method is generally preferred as it promotes encapsulation and reusability
of schema definitions provided by the plugins themselves.

## Dependencies

This module relies on Drupal Core APIs, primarily:
- Drupal Core Condition API (`plugin.manager.condition`)
- Drupal Core Context API (`context.repository`, `context.handler`)
- Drupal Core Form API

## Maintainers

This project is maintained by: Owen Bush
