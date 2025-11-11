<?php

declare(strict_types=1);

namespace Drupal\fullcalendarview_generator\Commands;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
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
   * The router matcher.
   *
   * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
   */
  protected $routerMatcher;

  /**
   * Factory method for the command.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('router')
    );
  }

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
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $router_matcher
   *   The router matcher.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StorageInterface $config_storage,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    UrlMatcherInterface $router_matcher,
  ) {
    parent::__construct();
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->routerMatcher = $router_matcher;
  }

  /**
   * Generate a calendar view using FullcalendarView.
   *
   * This command can be run interactively by providing no arguments, or
   * non-interactively by providing all the required arguments and options.
   *
   * @command fullcalendarview:generate
   * @aliases fcvg
   * @argument view_name The name of the view. If not provided, you will be prompted.
   * @argument content_types_str A comma-separated list of content type machine names. If not provided, you will be prompted.
   * @argument start_date_field The machine name of the start date field. If not provided, you will be prompted.
   * @argument path The path for the calendar page. If not provided, you will be prompted.
   * @option end_date_field The machine name of the end date field (optional).
   * @option title_field The machine name of the title field for the calendar event. Defaults to 'title'.
   * @option enable Whether to enable the view after creation.
   * @usage drush fcvg "My Calendar" "article,page" "field_event_date" "my-calendar" --end_date_field=field_event_date_end --title_field=title --enable
   *   Generate a new calendar view non-interactively.
   * @usage drush fcvg
   *   Generate a new calendar view interactively.
   */
  #[CLI\Command(name: 'fullcalendarview:generate', aliases: ['fcvg'])]
  #[CLI\Argument(name: 'view_name', description: 'The name of the view. If not provided, you will be prompted.')]
  #[CLI\Argument(name: 'content_types_str', description: 'A comma-separated list of content type machine names. If not provided, you will be prompted.')]
  #[CLI\Argument(name: 'start_date_field', description: 'The machine name of the start date field. If not provided, you will be prompted.')]
  #[CLI\Argument(name: 'path', description: 'The path for the calendar page. If not provided, you will be prompted.')]
  #[CLI\Option(name: 'end_date_field', description: 'The machine name of the end date field (optional).')]
  #[CLI\Option(name: 'title_field', description: "The machine name of the title field for the calendar event. Defaults to 'title'.")]
  #[CLI\Option(name: 'enable', description: 'Whether to enable the view after creation.')]
  #[CLI\Usage(name: 'drush fcvg "My Calendar" "article,page" "field_event_date" "my-calendar" --end_date_field=field_event_date_end --title_field=title --enable', description: 'Generate a new calendar view non-interactively.')]
  #[CLI\Usage(name: 'drush fcvg', description: 'Generate a new calendar view interactively.')]
  public function generate(
    ?string $view_name = NULL,
    ?string $content_types_str = NULL,
    ?string $start_date_field = NULL,
    ?string $path = NULL,
    array $options = [
      'end_date_field' => NULL,
      'title_field' => NULL,
      'enable' => FALSE,
    ],
  ) {
    // Ensure all arguments and options are set, interactively if needed.
    $this->ensureArgument('view_name', [$this, 'askViewName']);
    $this->ensureArgument('content_types_str', [$this, 'askContentTypes']);
    $this->ensureArgument('start_date_field', [$this, 'askStartDateField']);
    $this->ensureArgument('path', [$this, 'askPath']);

    // Options.
    $this->ensureOption('end_date_field', [$this, 'askEndDateField'], FALSE);
    $this->ensureOption('title_field', [$this, 'askTitleField'], FALSE);
    $this->ensureOption('enable', [$this, 'askEnableView'], FALSE);

    // Retrieve values from the Input object after they have been ensured.
    $view_name = $this->input()->getArgument('view_name');
    $content_types_str = $this->input()->getArgument('content_types_str');
    $start_date_field = $this->input()->getArgument('start_date_field');
    $path = $this->input()->getArgument('path');
    $end_date_field = $this->input()->getOption('end_date_field');
    $title_field = $this->input()->getOption('title_field');
    $enable_view = $this->input()->getOption('enable');

    $machine_name = $this->machineName($view_name);

    // Perform cross-input validation after all inputs are gathered.
    $content_types = $this->validateAndGetContentTypes($content_types_str);
    $this->validateFieldExistsOnContentTypes($start_date_field, $content_types, 'start date field');
    if (!empty($end_date_field)) {
      $this->validateFieldExistsOnContentTypes($end_date_field, $content_types, 'end date field');
    }
    $this->validateFieldExistsOnContentTypes($title_field, $content_types, 'title field');
    $this->validatePath($path);

    // Load the YAML template using the module path dynamically.
    if (!$this->moduleHandler->moduleExists('fullcalendarview_generator')) {
      $this->io()->error(dt('The FullcalendarView Generator module is not enabled.'));
      return DrushCommands::EXIT_FAILURE;
    }

    $module_path = $this->moduleHandler->getModule('fullcalendarview_generator')->getPath();
    $template_path = DRUPAL_ROOT . '/' . $module_path . '/templates/views.view.template.yml';

    if (!file_exists($template_path)) {
      $this->io()->error(dt('The view template file does not exist.'));
      return DrushCommands::EXIT_FAILURE;
    }

    try {
      $view_config_data = Yaml::parseFile($template_path);
    }
    catch (InvalidDataTypeException $e) {
      $this->io()->error(dt('The view template file is invalid YAML: @message', ['@message' => $e->getMessage()]));
      return DrushCommands::EXIT_FAILURE;
    }

    // Replace placeholders with default values before importing.
    $view_config_data['id'] = $machine_name;
    $view_config_data['label'] = $view_name;
    $view_config_data['status'] = $enable_view;

    // Load the new view entity.
    $view = $this->entityTypeManager->getStorage('view')->create($view_config_data);

    if (!$view) {
      $this->io()->error(dt('Failed to create the new view.'));
      return DrushCommands::EXIT_FAILURE;
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
    } // This was a bug in the previous diff, it's fixed now.
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
    $this->io()->success(dt('The calendar view has been generated successfully and is @status.', ['@status' => $status_message]));
    return DrushCommands::EXIT_SUCCESS;
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

  /**
   * Helper to ensure an argument is set, prompting interactively if needed.
   */
  protected function ensureArgument(string $name, callable $asker): void {
    $value = $this->input()->getArgument($name);

    if ($value === NULL && $this->input()->isInteractive()) {
      $value = $asker();
    }

    if ($value === NULL) {
      throw new \InvalidArgumentException(dt('The !argumentName argument is required.', [
        '!argumentName' => $name,
      ]));
    }

    $this->input()->setArgument($name, $value);
  }

  /**
   * Helper to ensure an option is set, prompting interactively if needed.
   */
  protected function ensureOption(string $name, callable $asker, bool $required): void {
    $value = $this->input()->getOption($name);

    if ($value === NULL && $this->input()->isInteractive()) {
      $value = $asker();
    }

    if ($required && $value === NULL) {
      throw new \InvalidArgumentException(dt('The !optionName option is required.', [
        '!optionName' => $name,
      ]));
    }

    $this->input()->setOption($name, $value);
  }

  /**
   * Helper to ask for the view name.
   */
  protected function askViewName(): string {
    return $this->io()->ask(
      dt('What is the name of the view?'),
      NULL,
      required: TRUE
    );
  }

  /**
   * Helper to ask for the content types for the calendar.
   */
  protected function askContentTypes(): string {
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $choices = [];
    foreach ($node_types as $machine_name => $node_type) {
      $choices[$machine_name] = $node_type->label();
    }

    if (empty($choices)) {
      throw new \RuntimeException(dt('No content types found on the site.'));
    }

    $selected = $this->io()->multiselect(
      dt('Select the content types to include in the view'),
      $choices,
      required: TRUE
    );

    if (empty($selected)) {
      throw new \RuntimeException(dt('You must select at least one content type.'));
    }

    return implode(',', $selected);
  }

  /**
   * Helper to ask for the start date field for the calendar.
   */
  protected function askStartDateField(): string {
    $content_types_str = $this->input()->getArgument('content_types_str');
    $content_types = explode(',', $content_types_str);

    $date_fields = [];
    foreach ($content_types as $content_type) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
      foreach ($field_definitions as $field_name => $field_definition) {
        // We are looking for date fields.
        if (in_array($field_definition->getType(), ['datetime', 'daterange', 'timestamp'])) {
          $date_fields[$field_name] = $field_definition->getLabel() . " ($field_name)";
        }
      }
    }

    if (empty($date_fields)) {
      throw new \RuntimeException(dt('No suitable date fields (datetime, daterange, timestamp, created, changed) found on the selected content types.'));
    }

    asort($date_fields);

    $selected = $this->io()->choice(
      dt('Select the start date field for the calendar'),
      $date_fields,
      required: TRUE
    );

    return $selected;
  }

  /**
   * Helper to ask for the end date field for the calendar (optional).
   */
  protected function askEndDateField(): ?string {
    $content_types_str = $this->input()->getArgument('content_types_str');
    $content_types = explode(',', $content_types_str);

    $date_fields = [];
    foreach ($content_types as $content_type) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
      foreach ($field_definitions as $field_name => $field_definition) {
        if (in_array($field_definition->getType(), ['datetime', 'daterange', 'timestamp'])) {
          $date_fields[$field_name] = $field_definition->getLabel() . " ($field_name)";
        }
      }
    }

    if (empty($date_fields)) {
      $this->io()->note(dt('No suitable date fields found for the optional end date field.'));
      return NULL;
    }

    asort($date_fields);
    $choices = ['' => dt('<none>')] + $date_fields;

    return $this->io()->choice(
      dt('Select the end date field for the calendar (optional)'),
      $choices,
      ''
    ) ?: NULL;
  }

  /**
   * Helper to ask for the title field for the calendar event.
   */
  protected function askTitleField(): string {
    $content_types_str = $this->input()->getArgument('content_types_str');
    $content_types = explode(',', $content_types_str);

    $text_fields = ['title' => dt('Title (title)')];
    foreach ($content_types as $content_type) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
      foreach ($field_definitions as $field_name => $field_definition) {
        if (in_array($field_definition->getType(), ['string', 'string_long', 'text', 'text_long', 'text_with_summary'])) {
          $text_fields[$field_name] = $field_definition->getLabel() . " ($field_name)";
        }
      }
    }

    asort($text_fields);

    return $this->io()->choice(
      dt('Select the title field for the calendar event'),
      $text_fields,
      'title',
      required: TRUE
    );
  }

  /**
   * Helper to ask for the path for the calendar page.
   */
  protected function askPath(): string {
    return $this->io()->ask(
      dt('What is the path for the calendar page?'),
      'calendar',
      required: TRUE
    );
  }

  /**
   * Helper to ask if the view should be enabled after creation.
   */
  protected function askEnableView(): bool {
    return $this->io()->confirm(dt('Do you want to enable the view after creation?'), FALSE);
  }

  /**
   * Validates content types string and returns an array of valid content types.
   */
  protected function validateAndGetContentTypes(string $contentTypesStr): array {
    $content_types_list = array_map('trim', explode(',', $contentTypesStr));
    $valid_content_types = [];
    foreach ($content_types_list as $content_type) {
      if ($this->entityTypeManager->getStorage('node_type')->load($content_type)) {
        $valid_content_types[] = $content_type;
      }
      else {
        $this->io()->warning(dt("Content type '@content_type' does not exist and will be ignored.", ['@content_type' => $content_type]));
      }
    }
    if (empty($valid_content_types)) {
      throw new \RuntimeException(dt('None of the specified content types exist. Please provide valid content types.'));
    }
    return $valid_content_types;
  }

  /**
   * Validates if a field exists on any of the given content types.
   */
  protected function validateFieldExistsOnContentTypes(string $fieldName, array $contentTypes, string $fieldPurpose): void {
    $field_exists = FALSE;
    foreach ($contentTypes as $content_type) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
      if (isset($field_definitions[$fieldName])) {
        $field_exists = TRUE;
        break;
      }
    }
    if (!$field_exists) {
      throw new \RuntimeException(dt("The @field_purpose '@field_name' does not exist on the specified content types.", [
        '@field_purpose' => $fieldPurpose,
        '@field_name' => $fieldName,
      ]));
    }
  }

  /**
   * Validates if a path is already in use.
   */
  protected function validatePath(string $path): void {
    $path_alias = '/' . trim($path, '/');
    try {
      $this->routerMatcher->match($path_alias);
      throw new \RuntimeException(dt("The path '@path_alias' is already in use.", ['@path_alias' => $path_alias]));
    }
    catch (ResourceNotFoundException $e) {
      // The path is not in use, which is what we want.
    }
  }

}
