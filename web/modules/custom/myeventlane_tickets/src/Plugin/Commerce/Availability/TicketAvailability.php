<?php

namespace Drupal\myeventlane_tickets\Plugin\Commerce\Availability;

use Drupal\commerce\Availability\AvailabilityCheckerInterface;
use Drupal\commerce\Availability\AvailabilityResult;
use Drupal\commerce\Context;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\myeventlane_tickets\Service\EventCapacityCalculator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @CommerceAvailability(
 *   id = "myeventlane_ticket_availability",
 *   label = @Translation("MyEventLane Ticket Availability")
 * )
 */
class TicketAvailability implements AvailabilityCheckerInterface, ContainerFactoryPluginInterface {

  public function __construct(private readonly EventCapacityCalculator $capacity) {}

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($container->get('myeventlane_tickets.capacity_calculator'));
  }

  public function applies(ProductVariationInterface $variation, Context $context): bool {
    return $variation->bundle() === 'ticket_variation';
  }

  public function check(ProductVariationInterface $variation, $quantity, Context $context): AvailabilityResult {
    // Sales window (optional).
    $now = new DrupalDateTime('now', new \DateTimeZone('UTC'));
    if ($variation->hasField('field_sales_start') && !$variation->get('field_sales_start')->isEmpty()) {
      $start = $variation->get('field_sales_start')->date;
      if ($start && $now < $start) {
        return AvailabilityResult::unavailable(t('Ticket sales haven’t started.'));
      }
    }
    if ($variation->hasField('field_sales_end') && !$variation->get('field_sales_end')->isEmpty()) {
      $end = $variation->get('field_sales_end')->date;
      if ($end && $now > $end) {
        return AvailabilityResult::unavailable(t('Ticket sales have ended.'));
      }
    }

    // Event-level HARD CAP.
    if ($event = $this->capacity->loadEventFromVariation($variation)) {
      $remainingEvent = $this->capacity->remainingCapacity($event);
      if ($remainingEvent !== NULL) {
        if ($remainingEvent <= 0) {
          return AvailabilityResult::unavailable(t('This event is sold out.'));
        }
        if ($quantity > $remainingEvent) {
          return AvailabilityResult::unavailable(\Drupal::translation()->formatPlural(
            $remainingEvent,
            'Only @count ticket remains for this event.',
            'Only @count tickets remain for this event.'
          ));
        }
      }
    }

    // If we didn’t block here, fall back to other checkers (e.g., Commerce Stock handles per-variation).
    return AvailabilityResult::available();
  }
}
