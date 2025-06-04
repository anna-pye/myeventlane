<?php

namespace Drupal\calendar_view\Plugin\views\style;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\Date as ViewsDateField;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\style\DefaultStyle;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class for Calendar View style plugin.
 */
abstract class CalendarViewBase extends DefaultStyle implements CalendarViewInterface {
  use StringTranslationTrait;

  /**
   * The types of `date` fields supported by this plugin.
   */
  const DATE_FIELD_TYPES = ['date', 'created', 'changed', 'datetime', 'daterange', 'smartdate', 'timestamp'];

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Contains the system.data configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $dateConfig;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Language manager for retrieving the default langcode when none is specified.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->logger = $container->get('logger.channel.calendar_view');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->dateConfig = $container->get('config.factory')->get('system.date');
    $instance->token = $container->get('token');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * Check if a field is supported by this plugin.
   *
   * @param mixed $field
   *   A given View field.
   *
   * @return bool
   *   Wether or not the field is supported in Calendar View.
   */
  public function isDateField($field) {
    if ($field instanceof ViewsDateField) {
      return TRUE;
    }

    if ($field instanceof EntityField) {
      $entity_type_id = $field->configuration['entity_type'] ?? NULL;
      $field_name = $field->configuration['entity field'] ?? $field->configuration['field_name'] ?? NULL;
      $field_storages = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      if ($definition = $field_storages[$field_name] ?? NULL) {
        return in_array($definition->getType(), self::DATE_FIELD_TYPES);
      }
    }

    return FALSE;
  }

  /**
   * A (not so) scientific method to get the list of days of the week.
   *
   * Core provides a DateHelper already but with no way to set the first day.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   The list of days, keyed by their number.
   *
   * @see \Drupal\Core\Datetime\DateHelper::weekDaysOrdered();
   */
  public function getOrderedDays() {
    // Avoid unnecessary calls with static variable.
    $days = &drupal_static(__METHOD__);
    if (isset($days)) {
      return $days;
    }

    $days = [
      0 => $this->t('Sunday'),
      1 => $this->t('Monday'),
      2 => $this->t('Tuesday'),
      3 => $this->t('Wednesday'),
      4 => $this->t('Thursday'),
      5 => $this->t('Friday'),
      6 => $this->t('Saturday'),
    ];

    $weekday_start = $this->options['calendar_weekday_start'] ?: $this->dateConfig->get('first_day') ?? 0;
    $weekdays = range($weekday_start, 6);
    $days = array_replace(array_flip($weekdays), $days);

    return $days;
  }

  /**
   * Retrieve all fields.
   *
   * @return array
   *   List of field, keyed by field ID.
   */
  public function getFields() {
    // Improve performance with static variables.
    $view_fields = &drupal_static(__METHOD__);
    if (isset($view_fields)) {
      return $view_fields;
    }

    $view_fields = $this->view->display_handler->getHandlers('field') ?? [];
    return $view_fields;
  }

  /**
   * Retrieve all Date fields.
   *
   * @return array
   *   List of View field plugin, keyed by their name.
   */
  public function getDateFields() {
    // Improve performance with static variables.
    $date_fields = &drupal_static(__METHOD__);
    if (isset($date_fields)) {
      return $date_fields;
    }

    $date_fields = array_filter($this->view->display_handler->getHandlers('field'), function ($field) {
      return $this->isDateField($field);
    });

    return $date_fields;
  }

  /**
   * Get date values from a given field on the view.
   *
   * @param \Drupal\views\ResultRow $row
   *   A given view result.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase $field
   *   A given field placed on the View.
   * @param int $delta
   *   (optional) A given delta (default: 0).
   *
   * @return array
   *   A list of values.
   */
  public function getDateFieldValues(ResultRow $row, FieldPluginBase $field, int $delta = 0) {
    if ($field instanceof ViewsDateField) {
      return ['value' => $field->getValue($row)];
    }

    if ($field instanceof EntityField) {
      $items = $field->getItems($row) ?? [];
      $item = $items[$delta]['raw'] ?? $items[0]['raw'] ?? NULL;
      return $item instanceof FieldItemInterface ? $item->getValue() : [];
    }

    return [];
  }

  /**
   * Determine timezone relative to a given date field or to the current user.
   *
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase $field
   *   (optional) A given date field.
   *
   * @return string The timezone, as a string.
   */
  public function getTimezone(FieldPluginBase $field = NULL) {
    $timezone = $this->dateConfig->get('timezone')['default'];
    // Get user's timezone, if enabled.
    if ($this->dateConfig->get('timezone.user.configurable')) {
      $timezone = $this->currentUser->getTimeZone() ?: $timezone;
    }
    // Get field overridden timezone.
    if ($field && isset($field->options['settings']['timezone_override'])) {
      $timezone = $field->options['settings']['timezone_override'] ?: $timezone;
    }

    return $timezone;
  }

  /**
   * Calculate time offset between two timezones.
   *
   * @param string $time
   *   A date/time string compatible with \DateTime. It is used as the
   *   reference for computing the offset, which can vary based on the time
   *   zone rules.
   * @param string $timezone
   *   The time zone that $time is in.
   *
   * @return int
   *   The computed offset (by difference of offsets between $time on server's
   *   current TZ and the same $time on $timezone) in seconds
   *
   * @see \Drupal\datetime\Plugin\views\filter\Date::getOffset()
   */
  public function getTimezoneOffset(string $time, string $timezone) {
    $currentTzDateTime = new \DateTime($time);
    $givenTzDatetime = new \DateTime($time, new \DateTimeZone($timezone));
    return $currentTzDateTime->getOffset() - $givenTzDatetime->getOffset();
  }

  /**
   * {@inheritDoc}
   */
  public function getCalendarTimestamp($use_cache = TRUE): int {
    // Avoid unnecessary calls with static variable.
    $timestamp = &drupal_static(__METHOD__);
    if (isset($timestamp) && $use_cache) {
      return _calendar_view_ensure_timestamp_value($timestamp);
    }

    // Allow user to pass query string.
    // (i.e "<url>?calendar_timestamp=2022-12-31" or "<url>?calendar_timestamp=tomorrow").
    $selected_timestamp = $this->view->getExposedInput()['calendar_timestamp'] ?? NULL;
    $selected_timestamp = !empty($selected_timestamp) ? $selected_timestamp : NULL;

    // Get date (default: today).
    $default_timestamp = !empty($this->options['calendar_timestamp']) ? $this->options['calendar_timestamp'] : NULL;

    // Get first result's timestamp.
    $first_timestamp = NULL;
    if (empty($this->options['calendar_timestamp'])) {
      $available_date_fields = $this->getDateFields();
      $field = reset($available_date_fields) ?? NULL;
      $first_result = reset($this->view->result) ?? NULL;
      if ($first_result instanceof ResultRow && $field instanceof EntityField) {
        $row_values = $this->getRowValues($first_result, $field);
        $first_timestamp = $row_values['value'] ?? NULL;
      }
    }

    $timestamp = $selected_timestamp ?? $default_timestamp ?? $first_timestamp ?? date('U');

    return _calendar_view_ensure_timestamp_value($timestamp);
  }

  /**
   * Get a caption string for this calendar table.
   *
   * @param string $string
   *   (optional) A given string.
   * @param int $timestamp
   *   (optional) A given timestamp - default: current calendar timestamp.
   *
   * @return string
   *   The caption with tokens replaced.
   */
  public function getCalendarCaption(string $string = NULL, int $timestamp = NULL) {
    $langcode = $this->getCurrentLangcode();
    $token_options = ['langcode' => $langcode, 'clear' => TRUE];

    $timestamp = $timestamp ?? $this->getCalendarTimestamp();
    $token_data = ['view' => $this->view, 'date' => $timestamp];

    $string = $string ?? $this->options['calendar_title'] ?? $this->view->getTitle();
    return $this->token->replace($string, $token_data, $token_options);
  }

  /**
   * Get the langcode specified for the current display.
   *
   * If rendering language was set to "English" for instance, we respect it and
   * "English" will be used for token replacements for instance - even if the
   * interface language is different.
   *
   * @return string
   *   A given langcode.
   */
  public function getCurrentLangcode() {
    $rendering_language = $this->view->display_handler->getOption('rendering_language');
    $langcode = $this->queryLanguageSubstitutions()[$rendering_language] ?? $rendering_language;
    $language = $this->languageManager->getLanguage($langcode);
    return $language ? $language->getId() : $this->languageManager->getCurrentLanguage()->getId();
  }

  /**
   * Helper to render the message when no fields available.
   *
   * @return array
   *   The message as render array.
   */
  public function getOutputNoFields() {
    $view_edit_url = Url::fromRoute('entity.view.edit_form', ['view' => $this->view->id()]);

    $build = [];

    $build['#markup'] = $this->t('Missing calendar field.');
    $build['#markup'] .= '<br>';
    $build['#markup'] .= $this->t('Please select at least one field in the @link.', [
      '@link' => Link::fromTextAndUrl(
        $this->t('Calendar View settings'),
        $view_edit_url,
      )->toString(),
    ]);

    $build['#access'] = $view_edit_url->access();

    return $build;
  }

  /**
   * Render array for a table cell.
   *
   * @param int $timestamp
   *   A given UNIX timestamp.
   * @param array $children
   *   A given list of children elements.
   *
   * @return array
   *   A cell content, as a render array.
   */
  public function getCell(int $timestamp, array $children = []) {
    $cell = [];
    $cell['data'] = [
      '#theme' => 'calendar_view_day',
      '#timestamp' => $timestamp,
      '#children' => $children,
      '#view' => $this->view,
    ];

    $cell['data-calendar-view-day'] = date('d', $timestamp);
    $cell['data-calendar-view-month'] = date('m', $timestamp);
    $cell['data-calendar-view-year'] = date('y', $timestamp);

    // Check relation from today.
    $relation = (date('Ymd', $timestamp) <=> date('Ymd'));
    $cell['class'][] = $relation === 0 ? 'is-today' : ($relation === 1 ? 'is-future' : 'is-past');

    if ($relation === 0) {
      $cell['data-calendar-view-today'] = TRUE;
    }

    // Check relation from selection.
    $selection = $this->getCalendarTimestamp();
    if ($timestamp == $selection) {
      $cell['data-calendar-view-selected'] = TRUE;
    }

    $cell['class'][] = strtolower(
      $this->getOrderedDays()[date('w', $timestamp)]->getUntranslatedString()
    );

    return $cell;
  }

  /**
   * Get default options.
   *
   * @return array
   *   The value list.
   */
  public function getDefaultOptions() {
    return [
      'calendar_fields' => [],
      'calendar_display_rows' => 0,
      // Start on Monday by default.
      'calendar_weekday_start' => 1,
      'calendar_sort_order' => 'ASC',
      'calendar_timestamp' => 'this month',
      'calendar_title' => '',
      'calendar_row_title' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $defaults = $this->getDefaultOptions();
    foreach ($defaults as $key => $value) {
      $options[$key] = ['default' => $value];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['calendar_display_rows'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display default View results'),
      '#description' => $this->t('If selected, View results rows are also display along the calendar.'),
      '#default_value' => $this->options['calendar_display_rows'] ?? 0,
    ];

    $date_fields = $this->getDateFields();
    $date_fields_keys = array_keys($date_fields);
    $default_date_field = [reset($date_fields_keys)];

    $form['calendar_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Date fields'),
      '#empty_option' => $this->t('- Select -'),
      '#options' => array_combine($date_fields_keys, $date_fields_keys),
      '#default_value' => $this->options['calendar_fields'] ?? $default_date_field,
      '#disabled' => empty($date_fields),
    ];
    if (empty($date_fields)) {
      $form['calendar_fields']['#description'] = $this->t('Add a date field in <em>fields</em> on this View and edit this setting again to activate the Calendar.');
    }

    $form['calendar_weekday_start'] = [
      '#type' => 'select',
      '#title' => $this->t('Start week on:'),
      '#options' => [
        1 => $this->t('Monday'),
        2 => $this->t('Tuesday'),
        3 => $this->t('Wednesday'),
        4 => $this->t('Thursday'),
        5 => $this->t('Friday'),
        6 => $this->t('Saturday'),
        0 => $this->t('Sunday'),
      ],
      '#default_value' => $this->options['calendar_weekday_start'] ?? NULL,
      '#empty_option' => $this->t("Use site's default"),
    ];

    $form['calendar_timestamp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default date'),
      '#description' => $this->t('Default starting date of this calendar, in any machine readable format.') . '<br>' .
      $this->t('Leave empty to use the date of the first result out of the first selected Date filter above.') . '<br>' .
      $this->t('NB: The first result is controlled by the <em>@sort_order</em> on this View.', [
        '@sort_order' => $this->t('Sort order'),
      ]),
      '#default_value' => $this->options['calendar_timestamp'] ?? 'this month',
    ];

    $form['calendar_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Calendar title'),
      '#description' => $this->t('The text used in table caption') . ' ' . $this->t("If empty, same as the View's title."),
      '#default_value' => $this->options['calendar_title'] ?? '',
    ];

    $form['calendar_row_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Row title'),
      '#description' => $this->t('The HTML attribute of each row.'),
      '#default_value' => $this->options['calendar_row_title'] ?? NULL,
    ];

    if (!$this->usesFields()) {
      $form['calendar_row_title']['#description'] .= '<br>';
      $form['calendar_row_title']['#description'] .= $this->t('Make sure to select %label option if you want to use field tokens.', [
        '%label' => $this->t('Force using fields'),
      ]);
    }
    else {
      $form['calendar_row_title']['#description'] .= $this->t('You may use field tokens from as per the "Replacement patterns" used in "Rewrite the output of this field" for all fields.');
    }

    // Show token replacements.
    $this->tokenForm($form, $form_state);
    $form['tokens']['#weight'] = 99;
    $form['global_tokens']['#weight'] = 99;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableGlobalTokens($prepared = FALSE, array $types = []) {
    $types += ['site', 'date', 'view'];
    return parent::getAvailableGlobalTokens($prepared, $types);
  }

  /**
   * Adds tokenization form elements.
   */
  public function tokenForm(&$form, FormStateInterface $form_state) {
    // Get a list of the available fields and arguments for token replacement.
    $options = [];
    $optgroup_arguments = (string) $this->t('Arguments');
    $optgroup_fields = (string) $this->t('Fields');
    foreach ($this->view->display_handler->getHandlers('field') as $field => $handler) {
      $options[$optgroup_fields]["{{ $field }}"] = $handler->adminLabel();
    }

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$optgroup_arguments]["{{ arguments.$arg }}"] = $this->t('@argument title', ['@argument' => $handler->adminLabel()]);
      $options[$optgroup_arguments]["{{ raw_arguments.$arg }}"] = $this->t('@argument input', ['@argument' => $handler->adminLabel()]);
    }

    if (!empty($options)) {
      $form['tokens'] = [
        '#type' => 'details',
        '#title' => $this->t('Replacement patterns'),
        '#id' => 'edit-options-token-help',
        '#access' => $this->usesFields(),
      ];
      $form['tokens']['help'] = [
        '#markup' => '<p>' . $this->t('The following tokens are available. You may use Twig syntax in this field.') . '</p>',
      ];
      foreach (array_keys($options) as $type) {
        if (!empty($options[$type])) {
          $items = [];
          foreach ($options[$type] as $key => $value) {
            $items[] = $key . ' == ' . $value;
          }
          $form['tokens'][$type]['tokens'] = [
            '#theme' => 'item_list',
            '#items' => $items,
          ];
        }
      }
      $form['tokens']['html_help'] = [
        '#markup' => '<p>' . $this->t('You may include the following allowed HTML tags with these "Replacement patterns": <code>@tags</code>', [
          '@tags' => '<' . implode('> <', Xss::getAdminTagList()) . '>',
        ]) . '</p>',
      ];
    }

    $this->globalTokenForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function preRender($results) {
    parent::preRender($results);

    // Init calendars.
    if (!isset($this->view->calendars)) {
      $this->view->calendars = $this->buildCalendars($this->getCalendarTimestamp());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    $sets = parent::render();

    // Build calendar by fields.
    $available_date_fields = $this->getDateFields();
    $calendar_fields = $this->options['calendar_fields'] ?? [];
    $calendar_fields = array_filter($calendar_fields, function ($field_name) use ($available_date_fields) {
      return ($field_name !== 0) && isset($available_date_fields[$field_name]);
    });

    // Stop now if no field selected.
    if (empty($calendar_fields)) {
      $output = $this->getOutputNoFields();
      $this->view->calendars = [$output];
      $this->view->calendar_error = TRUE;
      return;
    }

    // Populate calendars.
    foreach ($this->view->result as $result) {
      foreach ($calendar_fields as $field_id) {
        $field = $available_date_fields[$field_id] ?? NULL;
        if (!$this->isDateField($field)) {
          continue;
        }

        $row_values = $this->getRowValues($result, $field);
        $this->populateCalendar($result, $row_values);
      }
    }

    $cache_tags = $this->view->getCacheTags() ?? [];

    foreach (Element::children($this->view->calendars) as $i) {
      // Add default cache tags to Calendars.
      $calendar = &$this->view->calendars[$i];
      $calendar['#cache']['contexts'] = ['url.query_args:calendar_timestamp'];
      $calendar['#cache']['tags'] = $cache_tags;

      // Inject helpful variables for template suggestions.
      // @see calendar_view_theme_suggestions_table_alter()
      $calendar['#attributes'] = $calendar['#attributes'] ?? [];
      $calendar['#attributes']['data-calendar-view'] = $this->getPluginId();
      $calendar['#attributes']['data-calendar-view-view-id'] = $this->view->id();
      $calendar['#attributes']['data-calendar-view-view-display'] = $this->view->current_display;
      // Reorder attributes for a cleaner rendering.
      ksort($calendar['#attributes']);

      // Preprocess every cell.
      $table = &$this->view->calendars[$i];
      foreach ($table['#rows'] as $r => $rows) {
        foreach (array_keys($rows['data']) as $timestamp) {
          $cell = &$table['#rows'][$r]['data'][$timestamp];

          // Allow theming of table cells depending on the number of results.
          // See issue https://www.drupal.org/project/calendar_view/issues/3373664.
          $count = count($cell['data']['#children'] ?? []);
          $cell['data-calendar-view-results'] = $count;
          if ($count < 1) {
            $cell['class'][] = 'empty';
          }
        }
      }
    }

    return $sets;
  }

  /**
   * Get the value out of a view Result for a given date field.
   *
   * @param \Drupal\views\ResultRow $result
   *   A given view result.
   * @param \Drupal\views\Plugin\views\field\EntityField $field
   *   A given date field.
   *
   * @return array
   *   Either the timestamp or nothing.
   */
  public function getRowValues(ResultRow $row, FieldPluginBase $field) {
    $delta = 0;
    if ($delta_field = $field->aliases['delta'] ?? NULL) {
      $delta = $row->{$delta_field} ?? 0;
    }

    // Get the result we need from the entity.
    $this->view->row_index = $row->index ?? 0;
    $values = $this->getDateFieldValues($row, $field, $delta);
    unset($this->view->row_index);

    // Skip empty fields.
    if (empty($values) || empty($values['value'])) {
      return [];
    }

    // Make sure values are timestamps.
    $values['value'] = _calendar_view_ensure_timestamp_value($values['value']);
    $values['end_value'] = (_calendar_view_ensure_timestamp_value($values['end_value'] ?? $values['value']));

    // Get offset to fix start/end datetime values.
    $timezone = $this->getTimezone($field);
    $offset = $this->getTimezoneOffset('now', $timezone);
    $values['value'] += $offset;
    $values['end_value'] += $offset;

    // Get first item value to reorder multiday events in cells.
    $all_values = $field->getValue($row);
    $all_values = \is_array($all_values) ? $all_values : [$all_values];
    $first_value = reset($all_values);

    // Transform ISO8601 to timestamp.
    if (!ctype_digit($first_value)) {
      $first_instance_date = new DateTimePlus($first_value);
      $first_value = $first_instance_date->getTimestamp();
    }

    $values['first_instance'] = (int) $first_value;

    // Expose the date field if other modules need it in preprocess.
    $config = $field->configuration ?? [];
    $field_id = $config['field_name'] ?? $config['entity field'] ?? $config['id'] ?? NULL;
    $values['field'] = $field_id;

    // Get a unique identifier for this event.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $field->getEntity($row);
    $key = $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $field_id;
    $values['hash'] = md5($key . $row->index);

    // Prepare title attribute - tokens allowed but no HTML tags for safety.
    $row_title = $this->options['calendar_row_title'] ?? '';
    $row_title = $this->tokenizeValue($row_title, $row->index);
    $token_options = ['langcode' => $this->getCurrentLangcode(), 'clear' => TRUE];
    $row_title = $this->globalTokenReplace($row_title, $token_options);
    $values['title'] = strip_tags($row_title);

    return $values;
  }

  /**
   * Fill calendar with View results.
   *
   * @param \Drupal\views\ResultRow $result
   *   A given view result.
   * @param int $row_timestamp
   *   (optional) The timestamp value of this result.
   */
  public function populateCalendar(ResultRow $result, array $values = []) {
    // Skip empty rows.
    if (empty($values)) {
      return;
    }

    $start = $values['value'] ?? NULL;
    if (empty($start)) {
      return;
    }

    /** @var \Drupal\Core\Datetime\DrupalDateTime $now */
    $now = new DrupalDateTime('', $this->getTimezone());

    $start_day = clone $now;
    $start_day->setTimestamp($start);
    $start_day->setTime(0, 0, 0);

    $end = $values['end_value'] ?? $start;
    $end_day = clone $now;
    $end_day->setTimestamp($end);
    $end_day->setTime(0, 0, 0);

    $interval = $start_day->diff($end_day);
    $instances = $interval->format('%a');
    $values['instances'] = $instances;

    $timestamps = [];
    $day = clone $start_day;
    for ($i = 0; $i <= $instances; $i++) {
      $timestamps[] = $day->getTimestamp();
      $day->modify('+1 day');
    }

    // Render row and insert content in cell.
    // @see template_preprocess_calendar_view_day()
    $renderable_row = $this->view->rowPlugin->render($result);

    $this->view->calendars = $this->view->calendars ?? [];
    foreach (Element::children($this->view->calendars) as $i) {
      $table = &$this->view->calendars[$i];
      foreach ($table['#rows'] as $r => $rows) {
        foreach (array_keys($rows['data']) as $timestamp) {
          if (in_array($timestamp, $timestamps)) {
            $today = clone $now;
            $today->setTimestamp($timestamp);
            $today->setTime(0, 0, 0);

            $interval = $start_day->diff($today);
            $values['instance'] = $interval->format('%a');
            $renderable_row['#values'] = $values;

            $cell = &$table['#rows'][$r]['data'][$timestamp];
            $cell['data']['#children'][$start][] = $renderable_row;
          }
        }
      }
    }
  }

  /**
   * Make filter date values relative to the calendar's timestamp.
   */
  public function makeFilterValuesRelative() {
    $display_id = $this->view->current_display;
    $timestamp = $this->getCalendarTimestamp(FALSE);
    $filters = $this->view->displayHandlers->get($display_id)->getOption('filters');

    $date_fields = [];
    foreach ($this->getDateFields() as $field) {
      $date_fields[] = $field->realField;
    }

    $offset_date_filters = array_filter($filters, function ($filter) use ($date_fields) {
      // @todo Find a better way to handle start/end datetime fields.
      $identifier = $filter['field'] ?? '';
      $identifier = str_replace('_end_value', '_value', $identifier);
      // Field selected in calendar style's settings.
      $exists = in_array($identifier, $date_fields);
      // Relative dates only for offset filters (e.g. `-1 week`).
      $use_offset = ($filter['value']['type'] ?? NULL) == 'offset';
      return $exists && $use_offset;
    });

    foreach ($offset_date_filters as $filter_id => $filter) {
      foreach (['min', 'max', 'value'] as $key) {
        $offset = $filter['value'][$key];
        if (empty($offset)) {
          continue;
        }

        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $date->modify($offset);
        $relative_date = $date->format(DateTimePlus::FORMAT);
        $filters[$filter_id]['value'][$key] = $relative_date;
      }

      $filters[$filter_id]['value']['type'] = 'date';
    }

    // Update view filters with new values.
    $this->view->displayHandlers->get($display_id)->overrideOption('filters', $filters);
  }

}
