<?php

namespace Drupal\myeventlane_checkout_paragraph\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\myeventlane_checkout_paragraph\Entity\TicketHolderData;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Xss;

/**
 * Service to save ticket holder data during checkout.
 */
class TicketHolderSaver {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Saves attendee data for each ticket in the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $attendee_data
   *   The submitted data keyed by line item ID and field name.
   */
  public function saveAttendees(OrderInterface $order, array $attendee_data): void {
    foreach ($order->getItems() as $item) {
      $line_item_id = $item->id();
      if (!isset($attendee_data[$line_item_id])) {
        continue;
      }

      foreach ($attendee_data[$line_item_id] as $index => $data) {
        // Sanitize inputs
        $sanitized = [
          'first_name' => Xss::filter($data['first_name'] ?? ''),
          'last_name' => Xss::filter($data['last_name'] ?? ''),
          'email' => Xss::filter($data['email'] ?? ''),
        ];

        // Create Paragraph entity
        $paragraph = Paragraph::create([
          'type' => 'attendee_extra_field',
          'field_first_name' => $sanitized['first_name'],
          'field_last_name' => $sanitized['last_name'],
          'field_email' => $sanitized['email'],
        ]);
        $paragraph->save();

        // Link to custom entity
        $ticket_holder = TicketHolderData::create([
          'type' => 'ticket_holder_data',
          'field_order' => $order->id(),
          'field_line_item' => $line_item_id,
          'field_index' => $index,
          'field_data' => $paragraph,
        ]);
        $ticket_holder->save();
      }
    }
  }
}
