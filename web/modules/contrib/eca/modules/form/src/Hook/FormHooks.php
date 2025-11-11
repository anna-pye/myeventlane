<?php

namespace Drupal\eca_form\Hook;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Render\Element;
use Drupal\eca\Event\TriggerEvent;

/**
 * Implements form hooks for the ECA Form submodule.
 */
class FormHooks {

  /**
   * Constructs a new FormHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter', order: Order::Last)]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['#form_id']) && ($form['#form_id'] === 'system_modules_uninstall_confirm_form')) {
      // When this module is being uninstalled via UI, it will lead to a fatal.
      // To avoid this, the module uninstall confirm form is not supported.
      // @see https://www.drupal.org/project/eca/issues/3305797
      return;
    }
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }
    $this->triggerEvent->dispatchFromPlugin('form:form_build', $form, $form_state);
    // Add the handlers on class-level, to avoid expensive and possibly faulty
    // serialization of nested object references during form submissions.
    $form['#process'][] = [static::class, 'process'];
    $form['#after_build'][] = [static::class, 'afterBuild'];
    $form['#validate'][] = [static::class, 'validate'];
    $form['#submit'][] = [static::class, 'submit'];
    $this->addSubmitHandler($form);
  }

  /**
   * Implements hook_inline_entity_form_entity_form_alter().
   */
  #[Hook('inline_entity_form_entity_form_alter')]
  public function inlineEntityFormEntityFormAlter(array &$entity_form, FormStateInterface $form_state): void {
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }
    if (!isset($entity_form['#eca_ief_info'])) {
      return;
    }
    $info = &$entity_form['#eca_ief_info'];
    $this->triggerEvent->dispatchFromPlugin('form:ief_build', $entity_form, $form_state, $entity_form['#entity'], $info['parent'], $info['field_name'], $info['delta'], $info['widget_plugin_id']);
    // Pass along the info to the after build callback, but only parent UUID
    // is needed there.
    $info['parent'] = $info['parent']->uuid();
  }

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   *
   * For the "inline_entity_form_complex" widget plugin ID.
   */
  #[Hook('field_widget_single_element_inline_entity_form_complex_form_alter')]
  public function fieldWidgetSingleElementInlineEntityFormComplexFormAlter(array &$element, FormStateInterface $form_state, array &$context): void {
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }

    $delta = $context['delta'] ?? NULL;
    if (isset($element['#ief_id']) && ($triggering_element = &$form_state->getTriggeringElement())) {
      if (isset($triggering_element['#ief_row_delta'])) {
        $delta = $triggering_element['#ief_row_delta'];
      }
      else {
        $entities = $form_state->get([
          'inline_entity_form',
          $element['#ief_id'],
          'entities',
        ]);
        if (!empty($entities)) {
          $delta = count($entities);
        }
        elseif (isset($triggering_element['#ief_form']) && $triggering_element['#ief_form'] === 'add') {
          $delta = $context['items']->count();
        }
      }
    }
    if (!isset($delta)) {
      return;
    }

    // Pass along information for ::alterInlineEntityForm().
    if (isset($element['inline_entity_form'])) {
      $entity_form = &$element['inline_entity_form'];
    }
    elseif (isset($element['entities'][$delta]['form']['inline_entity_form'])) {
      $entity_form = &$element['entities'][$delta]['form']['inline_entity_form'];
    }
    elseif (isset($element['form']['inline_entity_form'])) {
      $entity_form = &$element['form']['inline_entity_form'];
    }
    else {
      return;
    }
    $info = [
      'parent' => $context['items']->getEntity(),
      'field_name' => $context['items']->getFieldDefinition()->getName(),
      'delta' => $delta,
      'widget_plugin_id' => $context['widget']->getPluginId(),
    ];
    $entity_form['#eca_ief_info'] = &$info;
  }

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   *
   * For the "inline_entity_form_simple" widget plugin ID.
   */
  #[Hook('field_widget_single_element_inline_entity_form_simple_form_alter')]
  public function fieldWidgetSingleElementInlineEntityFormSimpleFormAlter(array &$element, FormStateInterface $form_state, array &$context): void {
    $this->fieldWidgetSingleElementInlineEntityFormComplexFormAlter($element, $form_state, $context);
  }

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   *
   * For the "paragraphs" widget plugin ID.
   */
  #[Hook('field_widget_single_element_paragraphs_form_alter')]
  public function fieldWidgetSingleElementParagraphsFormAlter(array &$element, FormStateInterface $form_state, array &$context): void {
    if ($form_state->has('skip_eca')) {
      // When flagged by a component to skip ECA, then skip it.
      return;
    }

    // Skip if subform is null or not an array
    // to prevent InlineEntityFormBuild errors.
    if (!isset($element['subform']) || !is_array($element['subform'])) {
      return;
    }

    $field_name = $context['items']->getFieldDefinition()->getName();
    $delta = $context['delta'];
    $widget_state = WidgetBase::getWidgetState($element['#field_parents'], $field_name, $form_state);
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $widget_state['paragraphs'][$delta]['entity'] ?? $context['items']->get($delta)->entity;
    $parent = $context['items']->getEntity();
    $widget_plugin_id = $context['widget']->getPluginId();

    $this->triggerEvent->dispatchFromPlugin('form:ief_build', $element['subform'], $form_state, $paragraph, $parent, $field_name, $delta, $widget_plugin_id);
  }

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   *
   * For the "entity_reference_paragraphs" widget plugin ID.
   */
  #[Hook('field_widget_single_element_entity_reference_paragraphs_form_alter')]
  public function fieldWidgetSingleElementEntityReferenceParagraphsFormAlter(array &$element, FormStateInterface $form_state, array &$context): void {
    $this->fieldWidgetSingleElementParagraphsFormAlter($element, $form_state, $context);
  }

  /**
   * Add submit handler to nested elements if necessary.
   *
   * Walks through the element array recursively and adds the extra
   * submit-handler to all elements where necessary.
   *
   * @param array $elements
   *   A render array to walk through.
   */
  protected function addSubmitHandler(array &$elements): void {
    foreach (Element::children($elements) as $key) {
      if (is_array($elements[$key])) {
        // Only add our submit handler, when at least one other submit handler
        // is present for the element. The form submitter service calls
        // form-level submit handlers when no submit handler is specified, i.e.
        // either no #submit array is given at all, or the given array is empty.
        // @see \Drupal\Core\Form\FormSubmitter::executeSubmitHandlers()
        if (!empty($elements[$key]['#submit'])) {
          $submit_handler = [static::class, 'submit'];
          // Make sure our submit handler is added only once.
          if (!in_array($submit_handler, $elements[$key]['#submit'], TRUE)) {
            $elements[$key]['#submit'][] = $submit_handler;
          }
        }
        $this->addSubmitHandler($elements[$key]);
      }
    }
  }

  /**
   * Gets the trigger event service.
   *
   * @return \Drupal\eca\Event\TriggerEvent
   *   The trigger event service.
   */
  public static function triggerEvent(): TriggerEvent {
    return \Drupal::service('eca.trigger_event');
  }

  /**
   * Triggers the event to process a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function process(array $form, FormStateInterface $form_state): array {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_process', $form, $form_state);
    }
    return $form;
  }

  /**
   * Triggers the event after form building was completed.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function afterBuild(array $form, FormStateInterface $form_state): array {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_after_build', $form, $form_state);
    }
    return $form;
  }

  /**
   * Triggers the event to validate a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validate(array $form, FormStateInterface $form_state): void {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_validate', $form, $form_state);
    }
  }

  /**
   * Triggers the event to submit a form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function submit(array $form, FormStateInterface $form_state): void {
    if (!$form_state->has('skip_eca')) {
      static::triggerEvent()->dispatchFromPlugin('form:form_submit', $form, $form_state);
    }
  }

}
