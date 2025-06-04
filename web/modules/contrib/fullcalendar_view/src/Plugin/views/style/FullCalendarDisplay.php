<?php

namespace Drupal\fullcalendar_view\Plugin\views\style;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\core\form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\fullcalendar_view\TaxonomyColor;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render content for FullCalendar.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "fullcalendar_view_display",
 *   title = @Translation("Full Calendar Display"),
 *   help = @Translation("Render contents in Full Calendar view."),
 *   theme = "views_view_fullcalendar",
 *   display_types = { "normal" }
 * )
 */
class FullCalendarDisplay extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * The taxonomy color service.
   *
   * @var \Drupal\fullcalendar_view\TaxonomyColor
   */
  protected $taxonomyColorService;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\fullcalendar_view\TaxonomyColor $taxonomyColorService
   *   The Taxonomy Color Service object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The Module Handler Service object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TaxonomyColor $taxonomyColorService,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfo $entity_type_bundle_info,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->taxonomyColorService = $taxonomyColorService;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('fullcalendar_view.taxonomy_color'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['default_date_source'] = ['default' => 'now'];
    $options['defaultDate'] = ['default' => ''];
    $options['start'] = ['default' => ''];
    $options['end'] = ['default' => ''];
    $options['title'] = ['default' => ''];
    $options['duration'] = ['default' => ''];
    $options['rrule'] = ['default' => ''];
    $options['bundle_type'] = ['default' => ''];
    $options['tax_field'] = ['default' => ''];
    $options['color_bundle'] = ['default' => []];
    $options['color_taxonomies'] = ['default' => []];
    $options['vocabularies'] = ['default' => ''];
    $options['right_buttons'] = [
      'default' => [
        'dayGridMonth',
        'timeGridWeek',
        'timeGridDay',
        'listYear',
      ],
    ];
    $options['left_buttons'] = [
      'default' => 'prev,next today',
    ];
    $options['default_view'] = ['default' => 'dayGridMonth'];
    $options['default_mobile_view'] = ['default' => 'listYear'];
    $options['mobile_width'] = ['default' => 768];
    $options['nav_links'] = ['default' => 1];
    $options['timeFormat'] = ['default' => 'hh:mm a'];
    $options['defaultLanguage'] = ['default' => 'en'];
    $options['languageSelector'] = ['default' => 0];
    $options['allowEventOverlap'] = ['default' => 1];
    $options['updateAllowed'] = ['default' => 1];
    $options['updateConfirm'] = ['default' => 1];
    $options['dialogWindow'] = ['default' => 0];
    $options['createEventLink'] = ['default' => 0];
    $options['openEntityInNewTab'] = ['default' => 1];
    $options['dialogModal'] = ['default' => FALSE];
    $options['dialogCanvas'] = ['default' => FALSE];
    $options['eventLimit'] = ['default' => 2];
    $options['slotDuration'] = ['default' => '00:30:00'];
    $options['minTime'] = ['default' => '00:00:00'];
    $options['maxTime'] = ['default' => '23:59:59'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Remove the grouping setting.
    if (isset($form['grouping'])) {
      unset($form['grouping']);
    }
    $form['default_date_source'] = [
      '#type' => 'radios',
      '#options' => [
        'now' => $this->t('Current date'),
        'first' => $this->t('Date of first view result'),
        'fixed' => $this->t('Fixed value'),
      ],
      '#title' => $this->t('Default date source'),
      '#default_value' => (isset($this->options['default_date_source'])) ? $this->options['default_date_source'] : '',
      '#description' => $this->t('Source of the initial date displayed when the calendar first loads.'),
    ];
    // Default date of the calendar.
    $form['defaultDate'] = [
      '#type' => 'date',
      '#title' => $this->t('Default date'),
      '#default_value' => (isset($this->options['defaultDate'])) ? $this->options['defaultDate'] : '',
      '#description' => $this->t('Fixed initial date displayed when the calendar first loads.'),
      '#states' => [
        'visible' => [
          [':input[name="style_options[default_date_source]"]' => ['value' => 'fixed']],
        ],
      ],
    ];
    // All selected fields.
    $field_names = $this->displayHandler->getFieldLabels();
    $entity_type = $this->view->getBaseEntityType()->id();
    // Field name of start date.
    $form['start'] = [
      '#title' => $this->t('Start Date Field'),
      '#type' => 'select',
      '#options' => $field_names,
      '#default_value' => (!empty($this->options['start'])) ? $this->options['start'] : '',
    ];
    // Field name of end date.
    $form['end'] = [
      '#title' => $this->t('End Date Field'),
      '#type' => 'select',
      '#options' => $field_names,
      '#empty_value' => '',
      '#default_value' => (!empty($this->options['end'])) ? $this->options['end'] : '',
    ];
    // Field name of title.
    $form['title'] = [
      '#title' => $this->t('Title Field'),
      '#type' => 'select',
      '#options' => $field_names,
      '#default_value' => (!empty($this->options['title'])) ? $this->options['title'] : '',
    ];
    // Display settings.
    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('Display'),
      '#description' => $this->t('Calendar display settings.'),
    ];
    $fullcalendar_displays = [
      'dayGridMonth' => $this->t('Month'),
      'timeGridWeek' => $this->t('Week'),
      'timeGridDay' => $this->t('Day'),
      'listYear' => $this->t('List (Year)'),
      'listMonth' => $this->t('List (Month)'),
      'listWeek' => $this->t('List (Week)'),
      'listDay' => $this->t('List (Day)'),
    ];
    // Right side buttons.
    $display_defaults = (empty($this->options['right_buttons'])) ? [] : $this->options['right_buttons'];
    if (is_string($display_defaults)) {
      $display_defaults = explode(',', $display_defaults);
    }
    // Left side buttons.
    $form['left_buttons'] = [
      '#type' => 'textfield',
      '#fieldset' => 'display',
      '#default_value' => (empty($this->options['left_buttons'])) ? [] : $this->options['left_buttons'],
      '#title' => $this->t('Left side buttons'),
      '#description' => $this->t(
        'Left side buttons. Buttons are separated by commas or space. See the %fullcalendar_doc for available buttons.',
        [
          '%fullcalendar_doc' => Link::fromTextAndUrl($this->t('Fullcalendar documentation'), Url::fromUri('https://fullcalendar.io/docs/v4/header', ['attributes' => ['target' => '_blank']]))->toString(),
        ]
      ),
    ];
    $form['right_buttons'] = [
      '#type' => 'checkboxes',
      '#fieldset' => 'display',
      '#options' => $fullcalendar_displays,
      '#default_value' => $display_defaults,
      '#title' => $this->t('Display toggles'),
      '#description' => $this->t('Shown as buttons on the right side of the calendar view. See the %fullcalendar_doc.',
          [
            '%fullcalendar_doc' => Link::fromTextAndUrl($this->t('Fullcalendar "Views" documentation'), Url::fromUri('https://fullcalendar.io/docs/v4', ['attributes' => ['target' => '_blank']]))->toString(),
          ]),
    ];
    // Default view.
    $form['default_view'] = [
      '#type' => 'radios',
      '#fieldset' => 'display',
      '#options' => $fullcalendar_displays,
      '#default_value' => (empty($this->options['default_view'])) ? 'month' : $this->options['default_view'],
      '#title' => $this->t('Default view'),
    ];
    // Default mobile view.
    $form['default_mobile_view'] = [
      '#type' => 'radios',
      '#fieldset' => 'display',
      '#options' => $fullcalendar_displays,
      '#default_value' => (empty($this->options['default_mobile_view'])) ? 'month' : $this->options['default_mobile_view'],
      '#title' => $this->t('Default mobile view'),
    ];
    // Mobile width.
    $form['mobile_width'] = [
      '#fieldset' => 'display',
      '#type' => 'textfield',
      '#title' => $this->t('Mobile maximum width'),
      '#default_value' => (isset($this->options['mobile_width'])) ? $this->options['mobile_width'] : 768,
      '#size' => 4,
    ];
    // First day.
    $form['firstDay'] = [
      '#type' => 'radios',
      '#fieldset' => 'display',
      '#options' => [
        '0' => $this->t('Sunday'),
        '1' => $this->t('Monday'),
        '2' => $this->t('Tuesday'),
        '3' => $this->t('Wednesday'),
        '4' => $this->t('Thursday'),
        '5' => $this->t('Friday'),
        '6' => $this->t('Saturday'),
      ],
      '#default_value' => (empty($this->options['firstDay'])) ? '0' : $this->options['firstDay'],
      '#title' => $this->t('First Day'),
    ];
    // MinTime.
    $form['minTime'] = [
      '#type' => 'datetime',
      '#fieldset' => 'display',
      '#title' => $this->t('Start time'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#default_value' => new DrupalDateTime(!empty($this->options['minTime']) ? $this->options['minTime'] : '2000-01-01 00:00:00'),
      '#required' => TRUE,
    ];
    // MaxTime.
    $form['maxTime'] = [
      '#type' => 'datetime',
      '#fieldset' => 'display',
      '#title' => $this->t('End time'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#default_value' => new DrupalDateTime(!empty($this->options['maxTime']) ? $this->options['maxTime'] : '2000-01-01 23:59:59'),
      '#required' => TRUE,
    ];
    // Nav Links.
    $form['nav_links'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => (!isset($this->options['nav_links'])) ? 1 : $this->options['nav_links'],
      '#title' => $this->t('Day/Week are links'),
      '#description' => $this->t('If this option is selected, day/week names will be linked to navigation views.'),
    ];
    // Time format.
    $form['timeFormat'] = [
      '#fieldset' => 'display',
      '#type' => 'textfield',
      '#title' => $this->t('Time Format settings for month view'),
      '#default_value' => (isset($this->options['timeFormat'])) ? $this->options['timeFormat'] : 'hh:mm a',
      '#description' => $this->t('See %momentjs_doc for available formatting options. <br />Leave it blank to use the default format "hh:mm a".<br />Set it to [ ] if you do not want Fullcalendar View to prepend Title Field with any time at all.', [
        '%momentjs_doc' => Link::fromTextAndUrl($this->t('MomentJS’s formatting characters'), Url::fromUri('http://momentjs.com/docs/#/displaying/format/', ['attributes' => ['target' => '_blank']]))->toString(),
      ]),
      '#size' => 20,
    ];
    // Allow/disallow event overlap.
    $form['allowEventOverlap'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => (!isset($this->options['allowEventOverlap'])) ? 1 : $this->options['allowEventOverlap'],
      '#title' => $this->t('Allow calendar events to overlap'),
      '#description' => $this->t('If this option is selected, calendar events are allowed to overlap (default).'),
    ];
    // Allow/disallow event editing.
    $form['updateAllowed'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => (!isset($this->options['updateAllowed'])) ? 1 : $this->options['updateAllowed'],
      '#title' => $this->t('Allow event editing.'),
      '#description' => $this->t('If this option is selected, editing by dragging and dropping an event will be enabled.'),
    ];
    // Event update JS confirmation dialog.
    $form['updateConfirm'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => (!isset($this->options['updateConfirm'])) ? 1 : $this->options['updateConfirm'],
      '#title' => $this->t('Event update confirmation pop-up dialog.'),
      '#description' => $this->t('If this option is selected, a confirmation dialog will pop-up after dragging and dropping an event.'),
    ];
    // Language and Localization.
    $locale = [
      'current_lang' => $this->t('Current active language on the page'),
      'en' => 'English',
      'af' => 'Afrikaans',
      'ar-dz' => 'Arabic - Algeria',
      'ar-kw' => 'Arabic - Kuwait',
      'ar-ly' => 'Arabic - Libya',
      'ar-ma' => 'Arabic - Morocco',
      'ar-sa' => 'Arabic - Saudi Arabia',
      'ar-tn' => 'Arabic - Tunisia',
      'ar' => 'Arabic',
      'bg' => 'Bulgarian',
      'ca' => 'Catalan',
      'cs' => 'Czech',
      'da' => 'Danish',
      'de-at' => 'German - Austria',
      'de-ch' => 'German - Switzerland',
      'de' => 'German',
      'el' => 'Greek',
      'en-au' => 'English - Australia',
      'en-ca' => 'English - Canada',
      'en-gb' => 'English - United Kingdom',
      'en-ie' => 'English - Ireland',
      'en-nz' => 'English - New Zealand',
      'es-do' => 'Spanish - Dominican Republic',
      'es-us' => 'Spanish - United States',
      'es' => 'Spanish',
      'et' => 'Estonian',
      'eu' => 'Basque',
      'fa' => 'Farsi',
      'fi' => 'Finnish',
      'fr-ca' => 'French - Canada',
      'fr-ch' => 'French - Switzerland',
      'fr' => 'French',
      'gl' => 'Galician',
      'he' => 'Hebrew',
      'hi' => 'Hindi',
      'hr' => 'Croatian',
      'hu' => 'Hungarian',
      'id' => 'Indonesian',
      'is' => 'Icelandic',
      'it' => 'Italian',
      'ja' => 'Japanese',
      'kk' => 'Kannada',
      'ko' => 'Korean',
      'lb' => 'Lebanon',
      'lt' => 'Lithuanian',
      'lv' => 'Latvian',
      'mk' => 'FYRO Macedonian',
      'ms-my' => 'Malay - Malaysia',
      'ms' => 'Malay',
      'nb' => 'Norwegian (Bokmål) - Norway',
      'nl-be' => 'Dutch - Belgium',
      'nl' => 'Dutch',
      'nn' => 'Norwegian',
      'pl' => 'Polish',
      'pt-br' => 'Portuguese - Brazil',
      'pt' => 'Portuguese',
      'ro' => 'Romanian',
      'ru' => 'Russian',
      'sk' => 'Slovak',
      'sl' => 'Slovenian',
      'sq' => 'Albanian',
      'sr-cyrl' => 'Serbian - Cyrillic',
      'sr' => 'Serbian',
      'sv' => 'Swedish',
      'th' => 'Thai',
      'tr' => 'Turkish',
      'uk' => 'Ukrainian',
      'vi' => 'Vietnamese',
      'zh-cn' => 'Chinese - China',
      'zh-tw' => 'Chinese - Taiwan',
    ];
    // Default Language.
    $form['defaultLanguage'] = [
      '#title' => $this->t('Default Language'),
      '#fieldset' => 'display',
      '#type' => 'select',
      '#options' => $locale,
      '#default_value' => (!empty($this->options['defaultLanguage'])) ? $this->options['defaultLanguage'] : 'en',
    ];
    // Language Selector Switch.
    $form['languageSelector'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => (empty($this->options['languageSelector'])) ? 0 : $this->options['languageSelector'],
      '#title' => $this->t('Allow client to select language.'),
    ];
    $form['dialogWindow'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => (empty($this->options['dialogWindow'])) ? 0 : $this->options['dialogWindow'],
      '#title' => $this->t('Show event description in dialog window.'),
      '#description' => $this->t('If this option is selected, the description (the last field in the fields list) will show in a dialog window once clicking on the event.'),
    ];
    // Open details in new window.
    $form['openEntityInNewTab'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => !isset($this->options['openEntityInNewTab']) ? 1 : $this->options['openEntityInNewTab'],
      '#title' => $this->t('Open entities (calendar items) into new tabs'),
    ];
    $this->buildOptionsFormGoogleCalendar($form, $form_state);
    // Open event link target in modal popup.
    $form['dialogModal'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => !isset($this->options['dialogModal']) ? FALSE : $this->options['dialogModal'],
      '#title' => $this->t('Open event title link target in a modal popup'),
    ];
    // Open event link target in sidebar canvas.
    $form['dialogCanvas'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => !isset($this->options['dialogCanvas']) ? FALSE : $this->options['dialogCanvas'],
      '#title' => $this->t('Open event title link target in a Sidebar Canvas'),
      '#states' => [
        'visible' => [
          ':input[name="style_options[dialogModal]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    // Create new event link.
    $form['createEventLink'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => (empty($this->options['createEventLink'])) ? 0 : $this->options['createEventLink'],
      '#title' => $this->t('Create a new event via the Off-Canvas dialog.'),
      '#description' => $this->t('If this option is selected, there will be an Add Event link below the calendar that provides the ability to create an event In-Place.'),
    ];
    // Event limit displayed on a day.
    $form['eventLimit'] = [
      '#type' => 'textfield',
      '#fieldset' => 'display',
      '#default_value' => (isset($this->options['eventLimit'])) ? $this->options['eventLimit'] : 2,
      '#title' => $this->t('Limits the number of events'),
      '#description' => $this->t('Limits the number of events displayed on a day. The rest will show up in a popover.'),
    ];
    // Legend colors.
    $form['colors'] = [
      '#type' => 'details',
      '#title' => $this->t('Legend Colors'),
      '#description' => $this->t('Set color value of legends for each content type or each taxonomy.'),
    ];
    $form['slotDuration'] = [
      '#type' => 'textfield',
      '#fieldset' => 'display',
      '#title' => $this->t('Slot duration'),
      '#description' => $this->t('The frequency for displaying time slots.'),
      '#size' => 8,
      '#maxlength' => 8,
      '#default_value' => (isset($this->options['slotDuration'])) ? $this->options['slotDuration'] : '00:30:00',
    ];

    $moduleHandler = $this->moduleHandler;
    if ($moduleHandler->moduleExists('taxonomy')) {
      // All vocabularies.
      $cabNames = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->getQuery()->accessCheck(TRUE)->execute();
      // Taxonomy reference field.
      $tax_fields = [];
      // Find out all taxonomy reference fields of this View.
      foreach ($field_names as $field_name => $lable) {
        $field_conf = FieldStorageConfig::loadByName($entity_type, $field_name) ?: FieldStorageConfig::loadByName('user', $field_name);
        if (empty($field_conf)) {
          continue;
        }
        if ($field_conf->getType() == 'entity_reference') {
          $tax_fields[$field_name] = $lable;
        }
      }
      // Field name of event taxonomy.
      $form['tax_field'] = [
        '#title' => $this->t('Event Taxonomy Field'),
        '#description' => $this->t('In order to specify colors for event taxonomies, you must select a taxonomy reference field for the View.'),
        '#type' => 'select',
        '#options' => $tax_fields,
        '#empty_value' => '',
        '#disabled' => empty($tax_fields),
        '#fieldset' => 'colors',
        '#default_value' => (!empty($this->options['tax_field'])) ? $this->options['tax_field'] : '',
      ];
      // Color for vocabularies.
      $form['vocabularies'] = [
        '#title' => $this->t('Vocabularies'),
        '#type' => 'select',
        '#options' => $cabNames,
        '#empty_value' => '',
        '#fieldset' => 'colors',
        '#description' => $this->t('Specify which vocabulary is using for calendar event color. If the vocabulary selected is not the one that the taxonomy field belonging to, the color setting would be ignored.'),
        '#default_value' => (!empty($this->options['vocabularies'])) ? $this->options['vocabularies'] : '',
        '#states' => [
          // Only show this field when the 'tax_field' is selected.
          'invisible' => [
            [':input[name="style_options[tax_field]"]' => ['value' => '']],
          ],
        ],
        '#ajax' => [
          'callback' => [static::class, 'taxonomyColorCallback'],
          'event' => 'change',
          'wrapper' => 'color-taxonomies-div',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Verifying entry...'),
          ],
        ],
      ];
    }

    if (!isset($form_state->getUserInput()['style_options'])) {
      // Taxonomy color input boxes.
      $form['color_taxonomies'] = $this->taxonomyColorService->colorInputBoxs($this->options['vocabularies'], $this->options['color_taxonomies']);
    }
    // Content type colors.
    $form['color_bundle'] = [
      '#type' => 'details',
      '#title' => $this->t('Colors for Bundle Types'),
      '#description' => $this->t('Specify colors for each bundle type. If taxonomy color is specified, this settings would be ignored.'),
      '#fieldset' => 'colors',
    ];
    // All bundle types.
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    // Options list.
    $bundlesList = [];
    foreach ($bundles as $id => $bundle) {
      $label = $bundle['label'];
      $bundlesList[$id] = $label;
      // Content type colors.
      $form['color_bundle'][$id] = [
        '#title' => $label,
        '#default_value' => $this->options['color_bundle'][$id] ?? '#3a87ad',
        '#type' => 'color',
      ];
    }

    // Recurring event.
    $form['recurring'] = [
      '#type' => 'details',
      '#title' => $this->t('Recurring event settings'),
        // '#description' =>  $this->t('Settings for recurring event.'),.
    ];
    // Field name of rrules.
    $form['rrule'] = [
      '#title' => $this->t('RRule Field for recurring events.'),
      '#description' => $this->t('You can generate an valid rrule string via <a href=":tool-url" target="_blank">the online toole</a><br><a href=":doc-url" target="_blank">See the documentation</a> for more about RRule.',
          [
            ':tool-url' => 'https://jakubroztocil.github.io/rrule/',
            ':doc-url' => 'https://github.com/jakubroztocil/rrule',
          ]),
      '#type' => 'select',
      '#empty_value' => '',
      '#fieldset' => 'recurring',
      '#options' => $field_names,
      '#default_value' => (!empty($this->options['rrule'])) ? $this->options['rrule'] : '',
    ];
    // Field name of rrules.
    $form['duration'] = [
      '#fieldset' => 'recurring',
      '#title' => $this->t('Event duration field.'),
      '#description' => $this->t('For specifying the end time of each recurring event instance. The field value should be a string in the format hh:mm:ss.sss, hh:mm:sss or hh:mm. For example, "05:00" signifies 5 hours.'),
      '#type' => 'select',
      '#empty_value' => '',
      '#options' => $field_names,
      '#empty_value' => '',
      '#default_value' => (!empty($this->options['duration'])) ? $this->options['duration'] : '',
      '#states' => [
        // Only show this field when the 'rrule' is specified.
        'invisible' => [
          [':input[name="style_options[rrule]"]' => ['value' => '']],
        ],
      ],
    ];

    // New event bundle type.
    $form['bundle_type'] = [
      '#title' => $this->t('Event bundle (Content) type'),
      '#description' => $this->t('The bundle (content) type of a new event. Once this is set, you can create a new event by double clicking a calendar entry.'),
      '#type' => 'select',
      '#options' => array_merge(['' => t('None')], $bundlesList),
      '#default_value' => (!empty($this->options['bundle_type'])) ? $this->options['bundle_type'] : '',
    ];
    // Extra CSS classes.
    $form['classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS classes'),
      '#default_value' => (isset($this->options['classes'])) ? $this->options['classes'] : '',
      '#description' => $this->t('CSS classes for further customization of this view.'),
    ];
  }

  /**
   * Options form validation handle function.
   *
   * @see \Drupal\views\Plugin\views\PluginBase::validateOptionsForm()
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $style_options = &$form_state->getValue('style_options');
    $selected_displays = $style_options['right_buttons'];
    $default_display = $style_options['default_view'];
    $default_mobile_display = $style_options['default_mobile_view'];
    $mobile_width = $style_options['mobile_width'];

    if (!in_array($default_display, array_filter(array_values($selected_displays)))) {
      $form_state->setErrorByName('style_options][default_view', $this->t('The default view must be one of the selected display toggles.'));
    }

    $this->validateOptionsFormGoogleCalendar($form, $form_state);

    if (!in_array($default_mobile_display, array_filter(array_values($selected_displays)))) {
      $form_state->setErrorByName('style_options][default_mobile_view', $this->t('The default mobile view must be one of the selected display toggles.'));
    }
    if (!preg_match('#^\d+$#', $mobile_width)) {
      $form_state->setErrorByName('style_options][mobile_width', $this->t('Mobile width must be an integer.'));
    }

  }

  /**
   * @return $this
   */
  protected function validateOptionsFormGoogleCalendar(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue(['style_options', 'fetchGoogleHolidays']))) {
      return $this;
    }

    $values = $form_state->getValue(['style_options', 'googleHolidaysSettings']);
    if ($values['googleCalendarAPIKey'] === '') {
      $form_state->setError(
        $form['style_options']['googleHolidaysSettings']['googleCalendarAPIKey'],
        $this->t('This field is required if "Fetch and display holidays from Google Calendar" is checked.')
      );
    }

    if ($values['googleCalendarGroup'] === '') {
      $form_state->setError(
        $form['style_options']['googleHolidaysSettings']['googleCalendarGroup'],
        $this->t('This field is required if "Fetch and display holidays from Google Calendar" is checked.')
      );
    }

    return $this;
  }

  /**
   * Options form submit handle function.
   *
   * @see \Drupal\views\Plugin\views\PluginBase::submitOptionsForm()
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $options = &$form_state->getValue('style_options');
    // As the color pickup element, here has to use getUserInput().
    $input_value = $form_state->getUserInput();
    $input_colors = $input_value['style_options']['color_taxonomies'] ?? [];
    // Save the input of colors.
    foreach ($input_colors as $id => $color) {
      if (!empty($color)) {
        $options['color_taxonomies'][Xss::filter($id)] = Xss::filter($color);
      }
    }
    $options['minTime'] = $options['minTime']->format("H:i:s");
    $options['maxTime'] = $options['maxTime']->format("H:i:s");
    $options['right_buttons'] = isset($options['right_buttons']) ? implode(',', array_filter(array_values($options['right_buttons']))) : 'dayGridMonth,timeGridWeek,timeGridDay,listYear';

    // Sanitize user input.
    $options['timeFormat'] = Xss::filter($options['timeFormat']);

    parent::submitOptionsForm($form, $form_state);
  }

  /**
   * Taxonomy colors Ajax callback function.
   */
  public static function taxonomyColorCallback(array &$form, FormStateInterface $form_state) {
    $options = $form_state->getValue('style_options');
    $vid = $options['vocabularies'];
    // This is a static function,
    // has to get the service in this way.
    $taxonomy_color_service = \Drupal::service('fullcalendar_view.taxonomy_color');

    if (isset($options['color_taxonomies'])) {
      $defaultValues = $options['color_taxonomies'];
    }
    else {
      $defaultValues = [];
    }
    // Taxonomy color boxes.
    $form['color_taxonomies'] = $taxonomy_color_service->colorInputBoxs($vid, $defaultValues, TRUE);

    return $form['color_taxonomies'];
  }

  /**
   * Should the output of the style plugin be rendered even if it's a empty view.
   */
  public function evenEmpty() {
    // An empty calendar should be displayed if there are no calendar items.
    return TRUE;
  }

  /**
   *
   */
  protected function buildOptionsFormGoogleCalendar(array &$form, FormStateInterface $form_state) {
    $form['fetchGoogleHolidays'] = [
      '#type' => 'checkbox',
      '#fieldset' => 'display',
      '#default_value' => !empty($this->options['fetchGoogleHolidays']),
      '#title' => $this->t('Fetch and display public holidays from Google Calendar'),
    ];

    $form['googleHolidaysSettings'] = [
      '#type' => 'details',
      '#fieldset' => 'display',
      '#title' => $this->t('Google Calendar Holidays Settings,'),
      '#description' => $this->t('Settings for fetching holidays from Google Calendar.'),
      '#states' => [
        'open' => [
          [
            ':input[name="style_options[fetchGoogleHolidays]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],

      'googleCalendarAPIKey' => [
        '#type' => 'textfield',
        '#fieldset' => 'display',
        '#default_value' => $this->options['googleHolidaysSettings']['googleCalendarAPIKey'] ?? '',
        '#title' => $this->t('Google Calendar API Key'),
        '#description' => $this->t(
          'You can get an API Key following the procedure outlined <a href=":url" target="_blank">here</a>.',
          [
            ':url' => 'https://fullcalendar.io/docs/google-calendar',
          ],
        ),
        '#states' => [
          'required' => [
            ':input[name="style_options[fetchGoogleHolidays]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
      'googleCalendarGroup' => [
        '#type' => 'select',
        '#options' => $this->getGoogleCalendarHolidayGroupsOptions(),
        '#empty_value' => '',
        '#fieldset' => 'display',
        '#default_value' => $this->options['googleHolidaysSettings']['googleCalendarGroup'] ?? '',
        '#title' => $this->t('Select holidays to display'),
        '#states' => [
          'required' => [
            ':input[name="style_options[fetchGoogleHolidays]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
      'renderGoogleHolidaysAsBackground' => [
        '#type' => 'checkbox',
        '#fieldset' => 'display',
        '#default_value' => !empty($this->options['googleHolidaysSettings']['renderGoogleHolidaysAsBackground']),
        '#title' => $this->t('Render the holidays as background'),
        '#description' => t('Check to render the holidays as a background color only, not showing them as events.'),
      ],
    ];

    return $this;
  }

  /**
   * List of holiday groups.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   List of holiday groups.
   */
  protected function getGoogleCalendarHolidayGroupsOptions(): array {
    return [
      'en.christian#holiday@group.v.calendar.google.com' => $this->t('Christian Holidays'),
      'en.judaism#holiday@group.v.calendar.google.com' => $this->t('Jewish Holidays'),
      'en.islamic#holiday@group.v.calendar.google.com' => $this->t('Muslim Holidays'),
      'en.orthodox_christianity#holiday@group.v.calendar.google.com' => $this->t('Orthodox Holidays'),
      'en.af#holiday@group.v.calendar.google.com' => $this->t('Holidays in Afghanistan'),
      'en.al#holiday@group.v.calendar.google.com' => $this->t('Holidays in Albania'),
      'en.dz#holiday@group.v.calendar.google.com' => $this->t('Holidays in Algeria'),
      'en.ad#holiday@group.v.calendar.google.com' => $this->t('Holidays in Andorra'),
      'en.ao#holiday@group.v.calendar.google.com' => $this->t('Holidays in Angola'),
      'en.ar#holiday@group.v.calendar.google.com' => $this->t('Holidays in Argentina'),
      'en.am#holiday@group.v.calendar.google.com' => $this->t('Holidays in Armenia'),
      'en.aw#holiday@group.v.calendar.google.com' => $this->t('Holidays in Aruba'),
      'en.australian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Australia'),
      'en.austrian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Austria'),
      'en.az#holiday@group.v.calendar.google.com' => $this->t('Holidays in Azerbaijan'),
      'en.bs#holiday@group.v.calendar.google.com' => $this->t('Holidays in Bahamas'),
      'en.bh#holiday@group.v.calendar.google.com' => $this->t('Holidays in Bahrain'),
      'en.bd#holiday@group.v.calendar.google.com' => $this->t('Holidays in Bangladesh'),
      'en.bb#holiday@group.v.calendar.google.com' => $this->t('Holidays in Barbados'),
      'en.by#holiday@group.v.calendar.google.com' => $this->t('Holidays in Belarus'),
      'en.be#holiday@group.v.calendar.google.com' => $this->t('Holidays in Belgium'),
      'en.bj#holiday@group.v.calendar.google.com' => $this->t('Holidays in Benin'),
      'en.bm#holiday@group.v.calendar.google.com' => $this->t('Holidays in Bermuda'),
      'en.bo#holiday@group.v.calendar.google.com' => $this->t('Holidays in Bolivia'),
      'en.ba#holiday@group.v.calendar.google.com' => $this->t('Holidays in Bosnia and Herzegovina'),
      'en.bw#holiday@group.v.calendar.google.com' => $this->t('Holidays in Botswana'),
      'en.brazilian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Brazil'),
      'en.bulgarian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Bulgaria'),
      'en.bf#holiday@group.v.calendar.google.com' => $this->t('Holidays in Burkina Faso'),
      'en.bi#holiday@group.v.calendar.google.com' => $this->t('Holidays in Burundi'),
      'en.kh#holiday@group.v.calendar.google.com' => $this->t('Holidays in Cambodia'),
      'en.cm#holiday@group.v.calendar.google.com' => $this->t('Holidays in Cameroon'),
      'en.canadian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Canada'),
      'en.cv#holiday@group.v.calendar.google.com' => $this->t('Holidays in Cape Verde'),
      'en.ky#holiday@group.v.calendar.google.com' => $this->t('Holidays in Cayman Islands'),
      'en.cf#holiday@group.v.calendar.google.com' => $this->t('Holidays in Central African Republic'),
      'en.td#holiday@group.v.calendar.google.com' => $this->t('Holidays in Chad'),
      'en.cl#holiday@group.v.calendar.google.com' => $this->t('Holidays in Chile'),
      'en.china#holiday@group.v.calendar.google.com' => $this->t('Holidays in China'),
      'en.co#holiday@group.v.calendar.google.com' => $this->t('Holidays in Colombia'),
      'en.km#holiday@group.v.calendar.google.com' => $this->t('Holidays in Comoros'),
      'en.cg#holiday@group.v.calendar.google.com' => $this->t('Holidays in Congo'),
      'en.cr#holiday@group.v.calendar.google.com' => $this->t('Holidays in Costa Rica'),
      'en.croatian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Croatia'),
      'en.cu#holiday@group.v.calendar.google.com' => $this->t('Holidays in Cuba'),
      'en.cy#holiday@group.v.calendar.google.com' => $this->t('Holidays in Cyprus'),
      'en.czech#holiday@group.v.calendar.google.com' => $this->t('Holidays in Czech Republic'),
      'en.ci#holiday@group.v.calendar.google.com' => $this->t('Holidays in C\u00f4te d\'Ivoire'),
      'en.kp#holiday@group.v.calendar.google.com' => $this->t('Holidays in Democratic People\'s Republic of Korea'),
      'en.danish#holiday@group.v.calendar.google.com' => $this->t('Holidays in Denmark'),
      'en.do#holiday@group.v.calendar.google.com' => $this->t('Holidays in Dominican Republic'),
      'en.ec#holiday@group.v.calendar.google.com' => $this->t('Holidays in Ecuador'),
      'en.eg#holiday@group.v.calendar.google.com' => $this->t('Holidays in Egypt'),
      'en.sv#holiday@group.v.calendar.google.com' => $this->t('Holidays in El Salvador'),
      'en.gq#holiday@group.v.calendar.google.com' => $this->t('Holidays in Equatorial Guinea'),
      'en.er#holiday@group.v.calendar.google.com' => $this->t('Holidays in Eritrea'),
      'en.ee#holiday@group.v.calendar.google.com' => $this->t('Holidays in Estonia'),
      'en.et#holiday@group.v.calendar.google.com' => $this->t('Holidays in Ethiopia'),
      'en.fo#holiday@group.v.calendar.google.com' => $this->t('Holidays in Faroe Islands'),
      'en.fj#holiday@group.v.calendar.google.com' => $this->t('Holidays in Fiji'),
      'en.finnish#holiday@group.v.calendar.google.com' => $this->t('Holidays in Finland'),
      'en.french#holiday@group.v.calendar.google.com' => $this->t('Holidays in France'),
      'en.ga#holiday@group.v.calendar.google.com' => $this->t('Holidays in Gabon'),
      'en.gm#holiday@group.v.calendar.google.com' => $this->t('Holidays in Gambia'),
      'en.ge#holiday@group.v.calendar.google.com' => $this->t('Holidays in Georgia'),
      'en.german#holiday@group.v.calendar.google.com' => $this->t('Holidays in Germany'),
      'en.gh#holiday@group.v.calendar.google.com' => $this->t('Holidays in Ghana'),
      'en.gi#holiday@group.v.calendar.google.com' => $this->t('Holidays in Gibraltar'),
      'en.greek#holiday@group.v.calendar.google.com' => $this->t('Holidays in Greece'),
      'en.gl#holiday@group.v.calendar.google.com' => $this->t('Holidays in Greenland'),
      'en.gd#holiday@group.v.calendar.google.com' => $this->t('Holidays in Grenada'),
      'en.gt#holiday@group.v.calendar.google.com' => $this->t('Holidays in Guatemala'),
      'en.gn#holiday@group.v.calendar.google.com' => $this->t('Holidays in Guinea'),
      'en.gw#holiday@group.v.calendar.google.com' => $this->t('Holidays in Guinea-Bissau'),
      'en.ht#holiday@group.v.calendar.google.com' => $this->t('Holidays in Haiti'),
      'en.va#holiday@group.v.calendar.google.com' => $this->t('Holidays in Holy See (Vatican City State)'),
      'en.hn#holiday@group.v.calendar.google.com' => $this->t('Holidays in Honduras'),
      'en.hong_kong#holiday@group.v.calendar.google.com' => $this->t('Holidays in Hong Kong'),
      'en.hungarian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Hungary'),
      'en.is#holiday@group.v.calendar.google.com' => $this->t('Holidays in Iceland'),
      'en.indian#holiday@group.v.calendar.google.com' => $this->t('Holidays in India'),
      'en.indonesian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Indonesia'),
      'en.iq#holiday@group.v.calendar.google.com' => $this->t('Holidays in Iraq'),
      'en.irish#holiday@group.v.calendar.google.com' => $this->t('Holidays in Ireland'),
      'en.ir#holiday@group.v.calendar.google.com' => $this->t('Holidays in Islamic Republic of Iran'),
      'en.jewish#holiday@group.v.calendar.google.com' => $this->t('Holidays in Israel'),
      'en.italian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Italy'),
      'en.jm#holiday@group.v.calendar.google.com' => $this->t('Holidays in Jamaica'),
      'en.japanese#holiday@group.v.calendar.google.com' => $this->t('Holidays in Japan'),
      'en.jo#holiday@group.v.calendar.google.com' => $this->t('Holidays in Jordan'),
      'en.kz#holiday@group.v.calendar.google.com' => $this->t('Holidays in Kazakhstan'),
      'en.ke#holiday@group.v.calendar.google.com' => $this->t('Holidays in Kenya'),
      'en.kw#holiday@group.v.calendar.google.com' => $this->t('Holidays in Kuwait'),
      'en.kg#holiday@group.v.calendar.google.com' => $this->t('Holidays in Kyrgyzstan'),
      'en.latvian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Latvia'),
      'en.lb#holiday@group.v.calendar.google.com' => $this->t('Holidays in Lebanon'),
      'en.ls#holiday@group.v.calendar.google.com' => $this->t('Holidays in Lesotho'),
      'en.lr#holiday@group.v.calendar.google.com' => $this->t('Holidays in Liberia'),
      'en.ly#holiday@group.v.calendar.google.com' => $this->t('Holidays in Libya'),
      'en.li#holiday@group.v.calendar.google.com' => $this->t('Holidays in Liechtenstein'),
      'en.lithuanian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Lithuania'),
      'en.lu#holiday@group.v.calendar.google.com' => $this->t('Holidays in Luxembourg'),
      'en.mg#holiday@group.v.calendar.google.com' => $this->t('Holidays in Madagascar'),
      'en.mw#holiday@group.v.calendar.google.com' => $this->t('Holidays in Malawi'),
      'en.malaysia#holiday@group.v.calendar.google.com' => $this->t('Holidays in Malaysia'),
      'en.ml#holiday@group.v.calendar.google.com' => $this->t('Holidays in Mali'),
      'en.mt#holiday@group.v.calendar.google.com' => $this->t('Holidays in Malta'),
      'en.mq#holiday@group.v.calendar.google.com' => $this->t('Holidays in Martinique'),
      'en.mu#holiday@group.v.calendar.google.com' => $this->t('Holidays in Mauritius'),
      'en.yt#holiday@group.v.calendar.google.com' => $this->t('Holidays in Mayotte'),
      'en.mexican#holiday@group.v.calendar.google.com' => $this->t('Holidays in Mexico'),
      'en.md#holiday@group.v.calendar.google.com' => $this->t('Holidays in Moldova'),
      'en.mc#holiday@group.v.calendar.google.com' => $this->t('Holidays in Monaco'),
      'en.me#holiday@group.v.calendar.google.com' => $this->t('Holidays in Montenegro'),
      'en.ma#holiday@group.v.calendar.google.com' => $this->t('Holidays in Morocco'),
      'en.mz#holiday@group.v.calendar.google.com' => $this->t('Holidays in Mozambique'),
      'en.na#holiday@group.v.calendar.google.com' => $this->t('Holidays in Namibia'),
      'en.dutch#holiday@group.v.calendar.google.com' => $this->t('Holidays in Netherlands'),
      'en.new_zealand#holiday@group.v.calendar.google.com' => $this->t('Holidays in New Zealand'),
      'en.ni#holiday@group.v.calendar.google.com' => $this->t('Holidays in Nicaragua'),
      'en.ne#holiday@group.v.calendar.google.com' => $this->t('Holidays in Niger'),
      'en.ng#holiday@group.v.calendar.google.com' => $this->t('Holidays in Nigeria'),
      'en.norwegian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Norway'),
      'en.om#holiday@group.v.calendar.google.com' => $this->t('Holidays in Oman'),
      'en.pk#holiday@group.v.calendar.google.com' => $this->t('Holidays in Pakistan'),
      'en.pa#holiday@group.v.calendar.google.com' => $this->t('Holidays in Panama'),
      'en.py#holiday@group.v.calendar.google.com' => $this->t('Holidays in Paraguay'),
      'en.pe#holiday@group.v.calendar.google.com' => $this->t('Holidays in Peru'),
      'en.philippines#holiday@group.v.calendar.google.com' => $this->t('Holidays in Philippines'),
      'en.polish#holiday@group.v.calendar.google.com' => $this->t('Holidays in Poland'),
      'en.portuguese#holiday@group.v.calendar.google.com' => $this->t('Holidays in Portugal'),
      'en.pr#holiday@group.v.calendar.google.com' => $this->t('Holidays in Puerto Rico'),
      'en.qa#holiday@group.v.calendar.google.com' => $this->t('Holidays in Qatar'),
      'en.south_korea#holiday@group.v.calendar.google.com' => $this->t('Holidays in Republic of Korea'),
      'en.romanian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Romania'),
      'en.russian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Russian Federation'),
      'en.rw#holiday@group.v.calendar.google.com' => $this->t('Holidays in Rwanda'),
      'en.re#holiday@group.v.calendar.google.com' => $this->t('Holidays in R\u00e9union'),
      'en.sh#holiday@group.v.calendar.google.com' => $this->t('Holidays in Saint Helena, Ascension and Tristan da Cunha'),
      'en.sm#holiday@group.v.calendar.google.com' => $this->t('Holidays in San Marino'),
      'en.st#holiday@group.v.calendar.google.com' => $this->t('Holidays in Sao Tome and Principe'),
      'en.saudiarabian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Saudi Arabia'),
      'en.sn#holiday@group.v.calendar.google.com' => $this->t('Holidays in Senegal'),
      'en.rs#holiday@group.v.calendar.google.com' => $this->t('Holidays in Serbia'),
      'en.sc#holiday@group.v.calendar.google.com' => $this->t('Holidays in Seychelles'),
      'en.sl#holiday@group.v.calendar.google.com' => $this->t('Holidays in Sierra Leone'),
      'en.singapore#holiday@group.v.calendar.google.com' => $this->t('Holidays in Singapore'),
      'en.slovak#holiday@group.v.calendar.google.com' => $this->t('Holidays in Slovakia'),
      'en.slovenian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Slovenia'),
      'en.so#holiday@group.v.calendar.google.com' => $this->t('Holidays in Somalia'),
      'en.sa#holiday@group.v.calendar.google.com' => $this->t('Holidays in South Africa'),
      'en.ss#holiday@group.v.calendar.google.com' => $this->t('Holidays in South Sudan'),
      'en.spain#holiday@group.v.calendar.google.com' => $this->t('Holidays in Spain'),
      'en.lk#holiday@group.v.calendar.google.com' => $this->t('Holidays in Sri Lanka'),
      'en.sd#holiday@group.v.calendar.google.com' => $this->t('Holidays in Sudan'),
      'en.sr#holiday@group.v.calendar.google.com' => $this->t('Holidays in Suriname'),
      'en.sz#holiday@group.v.calendar.google.com' => $this->t('Holidays in Swaziland'),
      'en.swedish#holiday@group.v.calendar.google.com' => $this->t('Holidays in Sweden'),
      'en.ch#holiday@group.v.calendar.google.com' => $this->t('Holidays in Switzerland'),
      'en.sy#holiday@group.v.calendar.google.com' => $this->t('Holidays in Syrian Arab Republic'),
      'en.taiwan#holiday@group.v.calendar.google.com' => $this->t('Holidays in Taiwan'),
      'en.th#holiday@group.v.calendar.google.com' => $this->t('Holidays in Thailand'),
      'en.cd#holiday@group.v.calendar.google.com' => $this->t('Holidays in The Democratic Republic of the Congo'),
      'en.mk#holiday@group.v.calendar.google.com' => $this->t('Holidays in The Former Yugoslav Republic of Macedonia'),
      'en.tg#holiday@group.v.calendar.google.com' => $this->t('Holidays in Togo'),
      'en.tt#holiday@group.v.calendar.google.com' => $this->t('Holidays in Trinidad and Tobago'),
      'en.tn#holiday@group.v.calendar.google.com' => $this->t('Holidays in Tunisia'),
      'en.turkish#holiday@group.v.calendar.google.com' => $this->t('Holidays in Turkey'),
      'en.vi#holiday@group.v.calendar.google.com' => $this->t('Holidays in U.S. Virgin Islands'),
      'en.ug#holiday@group.v.calendar.google.com' => $this->t('Holidays in Uganda'),
      'en.ukrainian#holiday@group.v.calendar.google.com' => $this->t('Holidays in Ukraine'),
      'en.ae#holiday@group.v.calendar.google.com' => $this->t('Holidays in United Arab Emirates'),
      'en.uk#holiday@group.v.calendar.google.com' => $this->t('Holidays in United Kingdom'),
      'en.tz#holiday@group.v.calendar.google.com' => $this->t('Holidays in United Republic of Tanzania'),
      'en.usa#holiday@group.v.calendar.google.com' => $this->t('Holidays in United States'),
      'en.uy#holiday@group.v.calendar.google.com' => $this->t('Holidays in Uruguay'),
      'en.ve#holiday@group.v.calendar.google.com' => $this->t('Holidays in Venezuela'),
      'en.vietnamese#holiday@group.v.calendar.google.com' => $this->t('Holidays in Vietnam'),
      'en.ye#holiday@group.v.calendar.google.com' => $this->t('Holidays in Yemen'),
      'en.zm#holiday@group.v.calendar.google.com' => $this->t('Holidays in Zambia'),
      'en.zw#holiday@group.v.calendar.google.com' => $this->t('Holidays in Zimbabwe'),
    ];
  }

}
