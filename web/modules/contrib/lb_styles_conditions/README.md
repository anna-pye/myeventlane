# Layout Builder Styles Conditions

A Drupal module that allows you to control which Layout Builder Styles are
available based on configurable conditions.

## Overview

Layout Builder Styles Conditions provides a flexible way to control when styles
are available for selection within the Layout Builder UI. This module extends
the Layout Builder Styles configuration to include conditional rules, making it
easy to show or hide style options based on various conditions, such as user
role, node type, or request path.

The primary use case for this module is restricting specific style options to
certain conditions.

## Requirements

- Drupal 10.x or 11.x
- Layout Builder (Core Module)
- Layout Builder Styles (Contrib Module)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

### Admin Settings

The module provides an administration page to control which conditions are
available for style visibility at:

Administration > Configuration > Content authoring > Layout Builder Styles
Conditions.

From this page, administrators with the appropriate permission can select
which conditions should be available when configuring Layout Builder Styles.

### Layout Builder Styles Configuration

When enabled, the module adds visibility settings automatically to the
Layout Builder Styles configuration forms, allowing you to apply conditions
to individual styles or style groups.

## Usage

1. Navigate to the Layout Builder Styles configuration where you want to set
   conditional availability (`/admin/config/content/layout_builder_style`).
2. Edit the style or style group you wish to control.
3. Look for the "Conditions restrictions" section.
4. Add one or more conditions that determine when the style should be available.
5. Configure the condition parameters as needed.
6. Save the configuration.
7. When using Layout Builder, only the styles whose conditions are met will be
   presented as options in the Styles dropdown for blocks or sections.

## Extending the Module

Developers can alter the available conditions using a provided hook:

```php
/**
 * Implements hook_lb_styles_conditions_available_conditions_alter().
 */
function my_module_lb_styles_conditions_available_conditions_alter(
  array &$conditions, FormStateInterface $form_state, ?string $form_id): void {
  // Remove specific conditions
  $conditions_to_remove = [
    'language',
    'request_path',
  ];
  $conditions = array_diff_key($conditions, array_flip($conditions_to_remove));
}
```

## Contributing

Contributions are welcome! Please feel free to submit Issues and Merge Requests
via the project's issue queue.

## License

This module is licensed under the same terms as Drupal core.
