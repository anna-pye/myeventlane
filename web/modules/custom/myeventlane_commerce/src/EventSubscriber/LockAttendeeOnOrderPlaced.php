<?php

namespace Drupal\myeventlane_commerce\EventSubscriber;

use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LockAttendeeOnOrderPlaced implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return ['commerce_order.place.post_transition' => 'onOrderPlaced'];
  }

  public function onOrderPlaced(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    foreach ($order->getItems() as $item) {
      if ($item->hasField('field_attendee_data')) {
        // Lock attendee info to prevent edits after order placement.
      }
    }
  }
}
