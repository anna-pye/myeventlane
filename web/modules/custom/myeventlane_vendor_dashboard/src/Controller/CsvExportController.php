<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\File\FileSystemInterface;
use Drupal\commerce_store\Entity\StoreInterface;

class CsvExportController extends ControllerBase {

  protected $fileSystem;
  protected $currentUser; // ❌ Remove type hint to avoid conflict with ControllerBase

  public function __construct(FileSystemInterface $fileSystem, $currentUser) {
    $this->fileSystem = $fileSystem;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('current_user')
    );
  }

  /**
   * Export RSVPs for a given event node.
   */
  public function export($node) {
    $event = Node::load($node);
    if (!$event || $event->bundle() !== 'event') {
      return new Response('Invalid event node.', 404);
    }

    if (!$this->userOwnsEvent($event)) {
      return new Response('Access denied.', 403);
    }

    // 📦 Prepare RSVP CSV data
    $header = ['First Name', 'Last Name', 'Email', 'Submitted At'];
    $rows = [];

    $query = \Drupal::database()->select('myeventlane_rsvp', 'r')
      ->fields('r', ['first_name', 'last_name', 'email', 'created'])
      ->condition('r.event_nid', $node)
      ->orderBy('created', 'DESC');

    $results = $query->execute();
    foreach ($results as $record) {
      $rows[] = [
        $record->first_name,
        $record->last_name,
        $record->email,
        date('Y-m-d H:i:s', $record->created),
      ];
    }

    return $this->generateCsvDownload($rows, 'event_' . $node . '_rsvps.csv');
  }

  /**
   * Export Paid ticket purchases for a given event node.
   */
 public function exportPaid($node) {
   $event = Node::load($node);
   if (!$event || $event->bundle() !== 'event') {
     return new Response('Invalid event node.', 404);
   }

   if (!$this->userOwnsEvent($event)) {
     return new Response('Access denied.', 403);
   }

   // 🧾 Ensure the event has associated products.
   if (!$event->hasField('field_product_target') || $event->get('field_product_target')->isEmpty()) {
     return new Response('No ticket products associated with this event.', 400);
   }

   // Collect product IDs for comparison.
   $product_ids = array_column($event->get('field_product_target')->getValue(), 'target_id');

   // Load all completed orders; we'll filter manually in PHP.
   $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
   $order_ids = $order_storage->getQuery()
     ->accessCheck(TRUE)
     ->condition('state', 'completed')
     ->execute();

   $rows = [];
   $header = ['Order ID', 'Customer Email', 'Variation', 'Quantity', 'Purchased At'];

   if (!empty($order_ids)) {
     $orders = $order_storage->loadMultiple($order_ids);

     foreach ($orders as $order) {
       $email = $order->getEmail() ?? 'N/A';
       $order_id = $order->id();
       $created = date('Y-m-d H:i:s', $order->getCreatedTime());

       foreach ($order->getItems() as $item) {
         $purchased_entity = $item->getPurchasedEntity();
         if ($purchased_entity && method_exists($purchased_entity, 'getProductId')) {
           $product_id = $purchased_entity->getProductId();
           if (in_array($product_id, $product_ids)) {
             $rows[] = [
               $order_id,
               $email,
               $purchased_entity->label(),
               (int) $item->getQuantity(),
               $created,
             ];
           }
         }
       }
     }
   }

   if (empty($rows)) {
     $rows[] = ['-', '-', '-', '-', '-'];
   }

   // 📄 Write CSV.
   // 📄 Save CSV
   $event_name = preg_replace('/[^a-z0-9]+/i', '_', strtolower($event->label()));
   $filename = $event_name . '_orders.csv';
   $filepath = 'temporary://' . $filename;
   $realpath = $this->fileSystem->realpath($filepath);

   $handle = fopen($realpath, 'w');
   fputcsv($handle, $header);
   foreach ($rows as $row) {
     fputcsv($handle, $row);
   }
   fclose($handle);

   $response = new BinaryFileResponse($realpath);
   $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
   return $response;
 }

  /**
   * Common method for generating a CSV download response.
   */
  protected function generateCsvDownload(array $rows, string $filename): BinaryFileResponse {
    $filepath = 'temporary://' . $filename;
    $realpath = $this->fileSystem->realpath($filepath);

    $handle = fopen($realpath, 'w');
    fputcsv($handle, array_keys($rows[0]));
    foreach ($rows as $row) {
      fputcsv($handle, $row);
    }
    fclose($handle);

    $response = new BinaryFileResponse($realpath);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    return $response;
  }

  /**
   * Check whether the current user owns the event (via Store ownership or author).
   */
  protected function userOwnsEvent(Node $event): bool {
    $uid = $this->currentUser->id();

    if ($event->getOwnerId() == $uid) {
      return TRUE;
    }

    if ($event->hasField('field_store') && !$event->get('field_store')->isEmpty()) {
      $store = $event->get('field_store')->entity;
      if ($store instanceof StoreInterface && $store->getOwnerId() == $uid) {
        return TRUE;
      }
    }

    return FALSE;
  }

}