<?php

namespace Drupal\commerce_order\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\InlineFormManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of 'commerce_billing_profile'.
 */
#[FieldWidget(
  id: "commerce_billing_profile",
  label: new TranslatableMarkup("Billing information"),
  field_types: ["entity_reference_revisions"],
)]
class BillingProfileWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * Constructs a new BillingProfileWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, InlineFormManager $inline_form_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
    $this->inlineFormManager = $inline_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_inline_form')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $items[$delta]->getEntity();
    $store = $order->getStore();
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    if (!$items[$delta]->isEmpty() && $items[$delta]->entity) {
      $profile = $items[$delta]->entity;
    }
    else {
      $profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => 'customer',
        'uid' => 0,
      ]);
    }
    $wrapper_id = Html::getUniqueId('billing-profile-wrapper');
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';
    $element['#type'] = 'fieldset';
    // Check whether we should hide the profile form behind a button.
    // Note that we hide the profile form by default if the order doesn't
    // reference a billing profile yet.
    $hide_profile_form = $form_state->get('hide_profile_form') ?? $profile->isNew();

    if ($hide_profile_form) {
      $element['add_billing_information'] = [
        '#value' => $this->t('Add billing information'),
        '#name' => 'add_billing_information',
        '#type' => 'submit',
        '#submit' => [[get_class($this), 'addBillingInformationSubmit']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
    }
    else {
      // The "cancel" button shouldn't we shown in case the order references
      // a billing profile already.
      if ($profile->isNew()) {
        $element['hide_profile_form'] = [
          '#value' => $this->t('Cancel'),
          '#name' => 'hide_profile_form',
          '#type' => 'submit',
          '#submit' => [[get_class($this), 'hideProfileFormSubmit']],
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => [get_class($this), 'ajaxCallback'],
            'wrapper' => $wrapper_id,
          ],
          '#weight' => 100,
        ];
      }
      $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
        'profile_scope' => 'billing',
        'available_countries' => $store ? $store->getBillingCountries() : [],
        'address_book_uid' => $order->getCustomerId(),
        'admin' => TRUE,
      ], $profile);
      $element['profile'] = [
        '#parents' => array_merge($element['#field_parents'], [$items->getName(), $delta, 'profile']),
        '#inline_form' => $inline_form,
      ];
      $element['profile'] = $inline_form->buildInlineForm($element['profile'], $form_state);
    }

    // Workaround for massageFormValues() not getting $element.
    $element['array_parents'] = [
      '#type' => 'value',
      '#value' => array_merge($element['#field_parents'], [$items->getName(), 'widget', $delta]),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $new_values = [];
    foreach ($values as $delta => $value) {
      $element = NestedArray::getValue($form, $value['array_parents']);
      if (!isset($element['profile'])) {
        continue;
      }
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $element['profile']['#inline_form'];
      $new_values[$delta]['entity'] = $inline_form->getEntity();
    }
    return $new_values;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_order' && $field_name == 'billing_profile';
  }

  /**
   * Submit callback for the "Add billing information" button.
   */
  public static function addBillingInformationSubmit(array $form, FormStateInterface $form_state) {
    $form_state->set('hide_profile_form', FALSE);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for the "cancel" button that hides the billing profile.
   */
  public static function hideProfileFormSubmit(array $form, FormStateInterface $form_state) {
    $form_state->set('hide_profile_form', TRUE);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $parents = array_slice($parents, 0, -1);
    return NestedArray::getValue($form, $parents);
  }

}
