<?php

namespace Drupal\fullcalendarview_generator\Commands;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * Drush command to generate a Fullcalendar view.
 */
class FullcalendarViewGeneratorCommands extends DrushCommands {

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The configuration storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new FullcalendarViewGeneratorCommands object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The configuration storage service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StorageInterface $config_storage,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    parent::__construct();
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Generate a calendar view using FullcalendarView.
   *
   * @command fullcalendarview:generate
   * @aliases fcvg
   */
  public function generate() {
    // Collect user input with validation.
    // View name.
    $view_name = $this->io()->ask(
        'What is the name of the view?',
        NULL,
        function ($input) {
          $input = trim($input);
          if (empty($input)) {
            throw new \RuntimeException('The view name cannot be empty. Please enter a valid view name.');
          }
          return $input;
        }
        );
    $machine_name = $this->machineName($view_name);

    // Content types.
    $content_types = $this->io()->ask(
        'What are the machine name of the content types used in the view? (Comma-separated)',
        NULL,
        function ($input) {
          $input = trim($input);
          if (empty($input)) {
            throw new \RuntimeException('You must provide at least one content type.');
          }
          $content_types = array_map('trim', explode(',', $input));
          $valid_content_types = [];
          foreach ($content_types as $content_type) {
            if ($this->entityTypeManager->getStorage('node_type')->load($content_type)) {
              $valid_content_types[] = $content_type;
            }
            else {
              $this->io()->warning("Content type '$content_type' does not exist and will be ignored.");
            }
          }
          if (empty($valid_content_types)) {
            throw new \RuntimeException('None of the specified content types exist. Please provide valid content types.');
          }
          return $valid_content_types;
        }
        );

    // Start date field.
    $start_date_field = $this->io()->ask(
        'What is the machine name of the start date field for the calendar?',
        NULL,
        function ($input) use ($content_types) {
          $input = trim($input);
          if (empty($input)) {
            throw new \RuntimeException('The start date field cannot be empty. Please enter a valid field name.');
          }
          // Validate the field.
          $field_exists = FALSE;
          foreach ($content_types as $content_type) {
            $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
            if (isset($field_definitions[$input])) {
              $field_exists = TRUE;
              break;
            }
          }
          if (!$field_exists) {
            throw new \RuntimeException("The field '$input' does not exist on the specified content types.");
          }
          return $input;
        }
        );

    // End date field is optional.
    $end_date_field = $this->io()->ask(
        'What is the machine name of the end date field for the calendar? (optional)',
        '',
        function ($input) use ($content_types) {
          $input = trim($input);
          if (!empty($input)) {
            // Validate the field.
            $field_exists = FALSE;
            foreach ($content_types as $content_type) {
              $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
              if (isset($field_definitions[$input])) {
                $field_exists = TRUE;
                break;
              }
            }
            if (!$field_exists) {
              throw new \RuntimeException("The field '$input' does not exist on the specified content types.");
            }
          }
          return $input;
        }
        );

    // Title field.
    $title_field = $this->io()->ask(
        'What is the machine name of the title field for the calendar event?',
        'title',
        function ($input) use ($content_types) {
          $input = trim($input);
          if (empty($input)) {
            throw new \RuntimeException('The title field cannot be empty. Please enter a valid field name.');
          }
          // Validate the field.
          $field_exists = FALSE;
          foreach ($content_types as $content_type) {
            $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
            if (isset($field_definitions[$input])) {
              $field_exists = TRUE;
              break;
            }
          }
          if (!$field_exists) {
            throw new \RuntimeException("The field '$input' does not exist on the specified content types.");
          }
          return $input;
        }
        );

    // Path.
    $path = $this->io()->ask(
        'What is the path for the calendar page?',
        'calendar',
        function ($input) {
          $input = trim($input);
          if (empty($input)) {
            throw new \RuntimeException('The path cannot be empty. Please enter a valid path.');
          }
          return $input;
        }
        );

    // Ask the user if they want to enable the view after creation.
    $enable_view = $this->io()->confirm('Do you want to enable the view after creation?', FALSE);

    // Load the YAML template using the module path dynamically.
    if (!$this->moduleHandler->moduleExists('fullcalendarview_generator')) {
      $this->io()->error('The FullcalendarView Generator module is not enabled.');
      return;
    }

    $module_path = $this->moduleHandler->getModule('fullcalendarview_generator')->getPath();
    $template_path = DRUPAL_ROOT . '/' . $module_path . '/templates/views.view.template.yml';

    if (!file_exists($template_path)) {
      $this->io()->error('The view template file does not exist.');
      return;
    }

    try {
      $view_config_data = Yaml::parseFile($template_path);
    }
    catch (InvalidDataTypeException $e) {
      $this->io()->error('The view template file is invalid YAML: ' . $e->getMessage());
      return;
    }

    // Replace placeholders with default values before importing.
    $view_config_data['id'] = $machine_name;
    $view_config_data['label'] = $view_name;
    $view_config_data['status'] = $enable_view;

    // Load the new view entity.
    $view = $this->entityTypeManager->getStorage('view')->create($view_config_data);

    if (!$view) {
      $this->io()->error('Failed to create the new view.');
      return;
    }

    // Modify the view programmatically based on user input.
    $view->set('label', $view_name);
    $view->set('description', 'A calendar view generated by FullcalendarView Generator.');
    $view->set('status', $enable_view);

    // Get the view executable.
    $view_executable = $view->getExecutable();

    // Update the default display.
    $view_executable->setDisplay('default');
    $default_display = $view_executable->display_handler;

    // Update the title.
    $default_display->setOption('title', $view_name);

    // Update filters for content types.
    $filter_values = [];
    foreach ($content_types as $content_type) {
      $filter_values[$content_type] = $content_type;
    }
    $filters = $default_display->getOption('filters') ?: [];
    $filters['type'] = [
      'id' => 'type',
      'table' => 'node_field_data',
      'field' => 'type',
      'value' => $filter_values,
      'operator' => 'in',
      'expose' => FALSE,
      'plugin_id' => 'bundle',
    ];
    $default_display->setOption('filters', $filters);

    // Update fields.
    $fields = [
      $start_date_field,
      $title_field,
    ];
    if (!empty($end_date_field)) {
      $fields[] = $end_date_field;
    }

    $fields_config = $default_display->getOption('fields') ?: [];
    foreach ($fields as $field_name) {
      // Get the field definition.
      $field_definition = NULL;
      foreach ($content_types as $content_type) {
        $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
        if (isset($field_definitions[$field_name])) {
          $field_definition = $field_definitions[$field_name];
          break;
        }
      }

      if ($field_definition) {
        // Get the field storage definition to determine the table.
        $field_storage_definition = $field_definition->getFieldStorageDefinition();
        if ($field_name === 'title') {
          $field_table = 'node_field_data';
        }
        else {
          $field_table = $field_storage_definition->getTargetEntityTypeId() . '__' . $field_storage_definition->getName();
        }

        $fields_config[$field_name] = [
          'id' => $field_name,
          'table' => $field_table,
          'field' => $field_name,
          'relationship' => 'none',
          'group_type' => 'group',
          'admin_label' => '',
          'plugin_id' => 'field',
          'label' => '',
          'exclude' => FALSE,
          'alter' => [],
          'element_type' => '',
          'empty' => '',
          'hide_empty' => FALSE,
          'empty_zero' => FALSE,
          'settings' => [],
        ];
      }
      else {
        $this->io()->warning("Field '$field_name' not found on the specified content types.");
      }
    }
    $default_display->setOption('fields', $fields_config);

    // Update style options.
    $style_options = $default_display->getOption('style');
    $style_options['options']['start'] = $start_date_field;
    $style_options['options']['title'] = $title_field;
    if (!empty($end_date_field)) {
      $style_options['options']['end'] = $end_date_field;
    }
    else {
      unset($style_options['options']['end']);
    }
    $default_display->setOption('style', $style_options);

    // Update the page display.
    $view_executable->setDisplay('page_1');
    $page_display = $view_executable->display_handler;

    // Update the path and ensure the display is enabled.
    $page_display->setOption('path', $path);
    $page_display->setOption('enabled', TRUE);

    // Save the view.
    $view->save();

    // Clear caches again to ensure the updated view is registered.
    drupal_flush_all_caches();

    $status_message = $enable_view ? 'enabled' : 'disabled';
    $this->io()->success("The calendar view has been generated successfully and is {$status_message}.");
  }

  /**
   * Generate a machine name from the view name.
   *
   * @param string $name
   *   The human-readable name.
   *
   * @return string
   *   The machine name.
   */
  protected function machineName(string $name): string {
    return preg_replace('/[^a-z0-9_]+/', '_', strtolower($name));
  }

}
