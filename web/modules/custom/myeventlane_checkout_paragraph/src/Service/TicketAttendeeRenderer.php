<?php

namespace Drupal\myeventlane_checkout_paragraph\Service;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;

class TicketAttendeeRenderer {

  protected RendererInterface $renderer;

  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Renders ticket holders for an order.
   */
  public function renderForOrder(OrderInterface $order): array {
    $render_array = [];

    foreach ($order->getItems() as $item) {
      if (!$item->hasField('field_ticket_holder')) {
        continue;
      }
      $holders = $item->get('field_ticket_holder')->referencedEntities();
      foreach ($holders as $para) {
        if (!$para instanceof Paragraph) {
          continue;
        }

        $render_array[] = [
          '#theme' => 'attendee_row',
          '#data' => [
            'first' => $para->get('field_first_name')->value,
            'last' => $para->get('field_last_name')->value,
            'email' => $para->get('field_email')->value,
          ],
        ];
      }
    }

    return $render_array;
  }

}