<?php

namespace Drupal\myeventlane_checkout_paragraph\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Controller for vendor attendee export.
 */
class AttendeeExportController extends ControllerBase {

  /**
   * Export attendee info for a given event as CSV.
   */
  public function export(NodeInterface $event): StreamedResponse|RedirectResponse {
    $current_user = $this->currentUser();
    $vendor_uid = $event->getOwnerId();

    if (!$current_user->hasPermission('administer nodes') && $current_user->id() != $vendor_uid) {
      $this->messenger()->addError($this->t('You do not have access to export this event.'));
      return $this->redirect('<front>');
    }

    $filename = 'attendees-' . $event->id() . '.csv';

    return new StreamedResponse(function () use ($event) {
      $handle = fopen('php://output', 'w');

      // Build headers with event question labels.
      $question_labels = [];
      if ($event->hasField('field_attendee_questions') && !$event->get('field_attendee_questions')->isEmpty()) {
        foreach ($event->get('field_attendee_questions')->referencedEntities() as $q) {
          $label = $q->get('field_field_label')->value ?? 'Extra';
          $question_labels[] = $label;
        }
      }

      $header = array_merge(['First Name', 'Last Name', 'Email'], $question_labels);
      fputcsv($handle, $header);

      // Iterate order items
      $order_items = \Drupal::entityTypeManager()->getStorage('commerce_order_item')->loadMultiple();
      foreach ($order_items as $item) {
        if (!$item->hasField('field_ticket_holder') || $item->get('field_ticket_holder')->isEmpty()) {
          continue;
        }

        foreach ($item->get('field_ticket_holder')->referencedEntities() as $attendee) {
          if (!$attendee instanceof Paragraph || $attendee->bundle() !== 'attendee_answer') {
            continue;
          }

          $first = $attendee->get('field_first_name')->value ?? '';
          $last = $attendee->get('field_last_name')->value ?? '';
          $email = $attendee->get('field_email')->value ?? '';
          $map = [];

          if ($attendee->hasField('field_attendee_questions') && !$attendee->get('field_attendee_questions')->isEmpty()) {
            foreach ($attendee->get('field_attendee_questions')->referencedEntities() as $q) {
              if ($q instanceof Paragraph && $q->bundle() === 'attendee_extra_field') {
                $label = $q->get('field_field_label')->value ?? 'Extra';
                $answer = $q->get('field_attendee_extra_field')->value ?? '';
                $map[$label] = $answer;
              }
            }
          }

          $row = [$first, $last, $email];
          foreach ($question_labels as $label) {
            $row[] = $map[$label] ?? '';
          }
          fputcsv($handle, $row);
        }
      }

      fclose($handle);
    }, 200, [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
  }

  public function queueExport(NodeInterface $event): RedirectResponse {
    $this->messenger()->addStatus('Export queued (demo).');
    return $this->redirect('<front>');
  }

}