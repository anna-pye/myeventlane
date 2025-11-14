<?php

namespace Drupal\myeventlane_checkout_paragraph\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides the Ticket Holder Paragraph checkout pane.
 *
 * @CommerceCheckoutPane(
 *   id = "ticket_holder_paragraph",
 *   label = @Translation("Ticket Holder Information"),
 *   default_step = "order_information",
 * )
 */
final class TicketHolderParagraphPane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $pane_form['#tree'] = TRUE;
    $log = \Drupal::logger('myeventlane_debug');

    $pane_form['intro'] = [
      '#markup' => '<div class="mel-intro"><h2>Enter Your Details</h2><p>Please fill in your information below for each ticket.</p></div>',
    ];

    foreach ($this->order->getItems() as $index => $order_item) {
      if (!$order_item->hasField('field_ticket_holder')) {
        $log->warning('Order item @id missing field_ticket_holder.', ['@id' => $order_item->id()]);
        continue;
      }

      $quantity = (int) $order_item->getQuantity();

      // Event extra questions (templates).
      $event = $order_item->getPurchasedEntity()?->get('field_event')->entity ?? NULL;
      $extra_templates = $event?->get('field_attendee_questions')->referencedEntities() ?? [];
      $log->notice('Order item @id using @count extra question template(s).', [
        '@id' => $order_item->id(),
        '@count' => count($extra_templates),
      ]);

      // Ensure a paragraph per ticket.
      $holders = $order_item->get('field_ticket_holder')->referencedEntities();
      while (count($holders) < $quantity) {
        $para = Paragraph::create(['type' => 'attendee_answer']);

        // Clone templates → child paragraphs for this holder.
        $clones = [];
        foreach ($extra_templates as $tpl) {
          $clone = $tpl->createDuplicate();
          // Clear any default/template answer so checkout starts blank.
          if ($clone->hasField('field_attendee_extra_field')) {
            $clone->set('field_attendee_extra_field', NULL);
          }
          $clone->save();
          $clones[] = $clone;
        }
        if (!empty($clones) && $para->hasField('field_attendee_questions')) {
          $para->set('field_attendee_questions', $clones);
        }
        $para->save();
        $holders[] = $para;
      }

      // Persist the reference list (in case we created new ones).
      $order_item->set('field_ticket_holder', $holders);
      $order_item->save();

      // Build the form for each ticket holder.
      $pane_form['order_items'][$index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Ticket Holder Information for: @title', ['@title' => $order_item->label()]),
        '#tree' => TRUE,
      ];

      foreach ($holders as $delta => $paragraph) {
        if (!$paragraph instanceof Paragraph) {
          continue;
        }

        $fieldset = [
          '#type' => 'details',
          '#title' => $this->t('Ticket @num', ['@num' => $delta + 1]),
          '#open' => TRUE,
        ];

        // Basic attendee fields (parent paragraph).
        $fieldset['field_first_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('First name'),
          '#default_value' => $paragraph->get('field_first_name')->value ?? '',
          '#required' => TRUE,
        ];
        $fieldset['field_last_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Last name'),
          '#default_value' => $paragraph->get('field_last_name')->value ?? '',
          '#required' => TRUE,
        ];
        $fieldset['field_email'] = [
          '#type' => 'email',
          '#title' => $this->t('Email'),
          '#default_value' => $paragraph->get('field_email')->value ?? '',
          '#required' => TRUE,
        ];

        // Dynamic extra questions (child paragraphs of this holder).
        if ($paragraph->hasField('field_attendee_questions') && !$paragraph->get('field_attendee_questions')->isEmpty()) {
          $children = $paragraph->get('field_attendee_questions')->referencedEntities();
          foreach ($children as $q_index => $q) {
            $label = $q->get('field_field_label')->value ?? 'Extra Question';
            $type  = $q->get('field_field_type')->value ?? 'text';
            $req   = (bool) ($q->get('field_required')->value ?? FALSE);
            $field_name = "extra_{$index}_{$delta}_{$q_index}";
            $default = $q->get('field_attendee_extra_field')->value ?? '';

            // Build options from multi-value 'field_options'.
            $options = [];
            foreach ($q->get('field_options')->getValue() ?? [] as $item) {
              $opt = trim($item['value'] ?? '');
              if ($opt !== '') {
                $options[$opt] = $opt;
              }
            }

            switch ($type) {
              case 'select':
                $fieldset[$field_name] = [
                  '#type' => 'select',
                  '#title' => $label,
                  '#options' => $options ?: ['_' => $this->t('No options')],
                  '#default_value' => $default,
                  '#required' => $req,
                ];
                break;

              case 'checkbox':
                $fieldset[$field_name] = [
                  '#type' => 'checkbox',
                  '#title' => $label,
                  '#default_value' => (bool) $default,
                  '#required' => $req,
                ];
                break;

              case 'textarea':
                $fieldset[$field_name] = [
                  '#type' => 'textarea',
                  '#title' => $label,
                  '#rows' => 3,
                  '#default_value' => $default,
                  '#required' => $req,
                ];
                break;

              default:
                $fieldset[$field_name] = [
                  '#type' => 'textfield',
                  '#title' => $label,
                  '#default_value' => $default,
                  '#required' => $req,
                ];
                break;
            }
          }
        }

        $pane_form['order_items'][$index][$delta] = $fieldset;
      }
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $pane_values = $form_state->getValue($this->getPluginId()) ?? [];
    $order_items = $pane_values['order_items'] ?? [];
    if (!is_array($order_items)) {
      return;
    }

    foreach ($order_items as $index => $tickets) {
      foreach ($tickets as $delta => $entry) {
        if (empty($entry['field_first_name'])) {
          $form_state->setErrorByName("{$this->getPluginId()}][order_items][$index][$delta][field_first_name", $this->t('First name is required.'));
        }
        if (empty($entry['field_last_name'])) {
          $form_state->setErrorByName("{$this->getPluginId()}][order_items][$index][$delta][field_last_name", $this->t('Last name is required.'));
        }
        if (empty($entry['field_email'])) {
          $form_state->setErrorByName("{$this->getPluginId()}][order_items][$index][$delta][field_email", $this->t('Email is required.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $log = \Drupal::logger('myeventlane_debug');

    // IMPORTANT: Pane values live under the pane plugin id.
    $pane_values = $form_state->getValue($this->getPluginId()) ?? [];
    $order_items_values = $pane_values['order_items'] ?? NULL;

    $log->notice("submitPaneForm(): pane id=@id; values array? @ok", [
      '@id' => $this->getPluginId(),
      '@ok' => is_array($order_items_values) ? 'YES' : 'NO',
    ]);
    $log->notice('submitPaneForm(): Raw values:<pre>@dump</pre>', [
      '@dump' => print_r($pane_values, TRUE),
    ]);

    if (!is_array($order_items_values)) {
      return;
    }

    foreach ($this->order->getItems() as $index => $order_item) {
      if (!$order_item->hasField('field_ticket_holder')) {
        continue;
      }

      $holders = $order_item->get('field_ticket_holder')->referencedEntities();
      $ticket_values = $order_items_values[$index] ?? [];
      if (!is_array($ticket_values)) {
        continue;
      }

      foreach ($holders as $delta => $paragraph) {
        if (!$paragraph instanceof Paragraph) {
          continue;
        }
        $entry = $ticket_values[$delta] ?? NULL;
        if (!is_array($entry)) {
          continue;
        }

        // Save parent info.
        $paragraph->set('field_first_name', $entry['field_first_name'] ?? '');
        $paragraph->set('field_last_name', $entry['field_last_name'] ?? '');
        $paragraph->set('field_email', $entry['field_email'] ?? '');
        $log->notice('💾 Saved parent fields for paragraph @id (first=@f, last=@l, email=@e).', [
          '@id' => $paragraph->id(),
          '@f' => $entry['field_first_name'] ?? '',
          '@l' => $entry['field_last_name'] ?? '',
          '@e' => $entry['field_email'] ?? '',
        ]);

        // Save child answers.
        if ($paragraph->hasField('field_attendee_questions')) {
          $children = $paragraph->get('field_attendee_questions')->referencedEntities();
          foreach ($children as $q_index => $q) {
            $key = "extra_{$index}_{$delta}_{$q_index}";
            if (array_key_exists($key, $entry)) {
              $answer = $entry[$key];
              if ($q->hasField('field_attendee_extra_field')) {
                // Normalize checkbox to '0'/'1' or bool depending on field type.
                if (is_array($answer)) {
                  $answer = json_encode($answer);
                }
                $q->set('field_attendee_extra_field', $answer);
                $q->save();
                $log->notice('✅ Saved child @child answer: @val', ['@child' => $q->id(), '@val' => (string) $answer]);
              }
            }
          }
        }

        $paragraph->save();
      }

      $order_item->save();
    }

    // Allow checkout to proceed.
  }

}