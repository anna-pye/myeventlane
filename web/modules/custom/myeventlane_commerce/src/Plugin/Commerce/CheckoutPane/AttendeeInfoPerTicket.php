<?php

namespace Drupal\myeventlane_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Attendee information (per ticket) pane.
 *
 * @CommerceCheckoutPane(
 *   id = "myeventlane_attendee_info_per_ticket",
 *   label = @Translation("Attendee information (per ticket)"),
 *   default_step = "order_information",
 *   wrapper_element = "container",
 * )
 */
class AttendeeInfoPerTicket extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * @var \Drupal\myeventlane_commerce\Service\ParagraphQuestionMapper
   */
  protected $questionMapper;

  /**
   * Factory create with checkout flow (Commerce RC/Drupal 11 compatible).
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?CheckoutFlowInterface $checkout_flow = NULL
  ) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->questionMapper = $container->get('myeventlane_commerce.paragraph_question_mapper');
    return $instance;
  }

  /**
   * Visible only if any item belongs to an event with per-ticket capture enabled.
   */
  public function isVisible() {
    foreach ($this->order->getItems() as $item) {
      $event = $this->loadEventFromOrderItem($item);
      if ($event && $event->hasField('field_collect_per_ticket') && $event->get('field_collect_per_ticket')->value) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['#tree'] = TRUE;

    foreach ($this->order->getItems() as $order_item) {
      $event = $this->loadEventFromOrderItem($order_item);
      if (!$event || !$event->get('field_collect_per_ticket')->value) {
        continue;
      }

      $qty = (int) $order_item->getQuantity();
      $wrapper_key = 'item_' . $order_item->id();
      $pane_form[$wrapper_key] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $this->t('@title — Attendees', ['@title' => $order_item->label()]),
      ];

      for ($i = 1; $i <= $qty; $i++) {
        $defaults = $this->getExistingTicketData($order_item, $i);

        $card = [
          '#type' => 'container',
          '#attributes' => ['class' => ['mel-ticket-card']],
          'title' => ['#markup' => '<h3 class="mel-ticket-title">'.$this->t('Ticket @n', ['@n' => $i]).'</h3>'],
          'name' => ['#type' => 'textfield', '#title' => $this->t('Full name'), '#required' => TRUE, '#default_value' => $defaults['name'] ?? ''],
          'email' => ['#type' => 'email', '#title' => $this->t('Email'), '#required' => TRUE, '#default_value' => $defaults['email'] ?? ''],
        ];

        // Vendor-defined questions via Paragraphs mapper.
        $card += $this->questionMapper->buildElements($event, $defaults);

        $pane_form[$wrapper_key]['ticket_' . $i] = $card;
      }
    }

    // UX helper: copy Ticket 1 to all.
    $pane_form['copy_first'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply Ticket 1 details to all tickets'),
    ];

    $cache = new CacheableMetadata();
    $cache->setCacheMaxAge(0);
    $cache->setCacheContexts(['user.roles:authenticated']);
    $cache->setCacheTags(['your_custom_tag']);
    $cache->applyTo($pane_form);

    return $pane_form;
  }

  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Email format validated by #type 'email'.
  }

  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($this->paneFormKey, []);
    unset($values['copy_first']);

    foreach ($this->order->getItems() as $order_item) {
      $key = 'item_' . $order_item->id();
      if (!isset($values[$key])) {
        continue;
      }
      $per_item = $values[$key];
      $store = [];
      foreach ($per_item as $ticket_key => $ticket_values) {
        if (strpos($ticket_key, 'ticket_') !== 0) {
          continue;
        }
        $store[$ticket_key] = $ticket_values;
      }
      $order_item->set('field_attendee_data', $store);
      $order_item->save();
    }
  }

  /**
   * From order item → purchased entity → referenced Event node.
   */
  protected function loadEventFromOrderItem(OrderItemInterface $order_item) {
    $purchased_entity = $order_item->getPurchasedEntity();
    if ($purchased_entity && $purchased_entity->hasField('field_event_ref') && !$purchased_entity->get('field_event_ref')->isEmpty()) {
      return $purchased_entity->get('field_event_ref')->entity;
    }
    return NULL;
  }

  protected function getExistingTicketData(OrderItemInterface $order_item, int $n): array {
    // If you later store JSON/map, hydrate specific ticket_n defaults here.
    return [];
  }

}