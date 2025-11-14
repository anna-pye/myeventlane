<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Controller for rendering the vendor dashboard.
 */
class DashboardController extends ControllerBase {

  protected $entityTypeManager;
  protected $renderer;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, RendererInterface $renderer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

 public function dashboard() {
   $current_user = $this->currentUser();
   $uid = $current_user->id();

   $storage = $this->entityTypeManager->getStorage('node');

   // Published Events
   $published_nids = $storage->getQuery()
     ->accessCheck(TRUE)
     ->condition('type', 'event')
     ->condition('uid', $uid)
     ->condition('status', 1) // ✅ Published only
     ->sort('created', 'DESC')
     ->execute();
   $published_nodes = $storage->loadMultiple($published_nids);

   // Draft Events
   $draft_nids = $storage->getQuery()
     ->accessCheck(TRUE)
     ->condition('type', 'event')
     ->condition('uid', $uid)
     ->condition('status', 0) // ✅ Drafts only
     ->sort('created', 'DESC')
     ->execute();
   $draft_nodes = $storage->loadMultiple($draft_nids);

   $published_cards = [];
   foreach ($published_nodes as $node) {
     if ($node instanceof NodeInterface) {
       $published_cards[] = $this->buildEventCard($node);
     }
   }

   $draft_cards = [];
   foreach ($draft_nodes as $node) {
     if ($node instanceof NodeInterface) {
       $draft_cards[] = $this->buildEventCard($node);
     }
   }

   return [
     '#theme' => 'myeventlane_vendor_dashboard',
     '#cards' => $published_cards,
     '#drafts' => $draft_cards,
     '#attached' => [
       'library' => ['myeventlane_vendor_dashboard/dashboard'],
     ],
   ];
 }

  protected function buildEventCard(NodeInterface $event): array {
    $title = $event->label();
    $type = $event->get('field_ticket_type')->value ?? 'Unknown';

    $image = NULL;
    if ($event->hasField('field_image') && !$event->get('field_image')->isEmpty()) {
      $media = $event->get('field_image')->entity;
      if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
        $file = $media->get('field_media_image')->entity;
        $image = [
          '#theme' => 'image',
          '#uri' => $file->getFileUri(),
          '#alt' => $media->getName(),
          '#attributes' => ['class' => ['mel-card-image']],
        ];
      }
    }

    if (!$event->isPublished()) {
        $type = 'Draft';
      }

    if (!$image) {
      $image = [
        '#theme' => 'image',
        '#uri' => 'public://placeholder.svg',
        '#alt' => 'No image',
        '#attributes' => ['class' => ['mel-card-image', 'mel-image-placeholder']],
      ];
    }

    $venue = $event->get('field_venue')->value ?? 'TBA';
    $ticket_limit = $event->get('field_ticket_limit')->value ?? 'N/A';
    $ticket_sold = 0;

    // ✅ Count RSVPs
    if (
      $type === 'RSVP' &&
      $event->hasField('field_rsvp_target') &&
      !$event->get('field_rsvp_target')->isEmpty()
    ) {
      $event_nid = $event->id();
      $ticket_sold = \Drupal::entityQuery('node')
        ->accessCheck(TRUE)
        ->condition('type', 'rsvp_submission')
        ->condition('field_event', $event_nid)
        ->count()
        ->execute();
    }

    // ✅ Count Paid ticket sales
    if (
      $type === 'Paid' &&
      $event->hasField('field_product_target') &&
      !$event->get('field_product_target')->isEmpty()
    ) {
      $product_ids = array_column($event->get('field_product_target')->getValue(), 'target_id');
      $orders = \Drupal::entityTypeManager()->getStorage('commerce_order')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('state', 'completed')
        // 🚧 Filtering orders by product_id must be done manually, not via EntityQuery.
        ->execute();

      if ($orders) {
        $order_entities = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadMultiple($orders);
        foreach ($order_entities as $order) {
          foreach ($order->getItems() as $item) {
            $variation = $item->getPurchasedEntity();
            if ($variation && in_array($variation->getProductId(), $product_ids)) {
              $ticket_sold += (int) $item->getQuantity();
            }
          }
        }
      }
    }

    $rsvp_link = [];
    $csv_link = [];
    $analytics = [];

    if ($type === 'RSVP') {
      $rsvp_link = Link::fromTextAndUrl('View RSVPs', Url::fromRoute('entity.node.canonical', ['node' => $event->id()]))->toRenderable();
      $csv_url = Url::fromRoute('myeventlane.export_csv', ['node' => $event->id()]);
    }
    elseif ($type === 'Paid') {
      $csv_url = Url::fromRoute('myeventlane.export_csv_paid', ['node' => $event->id()]);
    }

    if (isset($csv_url)) {
      $csv_link = Link::fromTextAndUrl('CSV Export', $csv_url)->toRenderable();
    }

    $analytics = Link::fromTextAndUrl('Analytics', Url::fromRoute('myeventlane.analytics', ['node' => $event->id()]))->toRenderable();

    return [
      '#theme' => 'myeventlane_vendor_dashboard_card',
      '#title' => $title,
      '#type' => $type,
      '#venue' => $venue,
      '#ticket_limit' => $ticket_limit,
      '#count' => $ticket_sold,
      '#image' => $image,
      '#rsvp_link' => $rsvp_link,
      '#csv_link' => $csv_link,
      '#analytics' => $analytics,
    ];
  }

}