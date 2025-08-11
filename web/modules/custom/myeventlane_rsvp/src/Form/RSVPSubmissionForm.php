<?php

namespace Drupal\myeventlane_rsvp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Database\Database;

/**
 * RSVP submission form (dynamic, supports event-level extra questions).
 */
class RSVPSubmissionForm extends FormBase {

  public function getFormId() {
    return 'myeventlane_rsvp_submission_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $event_id = NULL) {
    if (empty($event_id) || !is_numeric($event_id)) {
      \Drupal::messenger()->addError($this->t('This RSVP form is missing its Event context. Please return to the event page and try again.'));
      return [];
    }

    // Load the event node
    $event = Node::load($event_id);

    // Get current user
    $current_user = \Drupal::currentUser();
    $uid = $current_user->isAuthenticated() ? $current_user->id() : 0;

    // Check if user has already RSVPed (for limiting logic)
    $has_rsvped = FALSE;
    if ($event && $event->hasField('field_limit_one_rsvp') && $event->get('field_limit_one_rsvp')->value && $uid > 0) {
      $query = Database::getConnection()->select('myeventlane_rsvp', 'r')
        ->fields('r', ['id'])
        ->condition('event_nid', $event_id)
        ->condition('uid', $uid)
        ->range(0, 1);
      $has_rsvped = $query->execute()->fetchField() ? TRUE : FALSE;
    }

    // Add hidden event ID
    $form['event_id'] = [
      '#type' => 'hidden',
      '#value' => $event_id,
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your First Name'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['rsvp-field', 'rsvp-field--name']],
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Last Name'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['rsvp-field', 'rsvp-field--name']],
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your Email'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['rsvp-field', 'rsvp-field--email']],
    ];
    $form['comments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comments or Questions'),
      '#description' => $this->t('Optional: Is there anything you want the organiser to know, or any accessibility needs?'),
      '#attributes' => ['class' => ['rsvp-field', 'rsvp-field--comments']],
      '#placeholder' => $this->t('e.g., I have dietary needs, or will arrive late...'),
    ];

    // Dynamically add extra fields from field_attendee_questions (Paragraphs)
    if ($event && $event->hasField('field_attendee_questions')) {
      foreach ($event->get('field_attendee_questions') as $delta => $ref) {
        $para = $ref->entity;
        if ($para instanceof Paragraph) {
          $label = $para->get('field_label')->value ?? '';
          $type = $para->get('field_type')->value ?? 'textfield';
          $options_raw = $para->get('field_options')->value ?? '';
          $field_name = 'extra_' . $delta;

          switch ($type) {
            case 'textarea':
              $form[$field_name] = [
                '#type' => 'textarea',
                '#title' => $label,
              ];
              break;

            case 'select':
            case 'radio':
            case 'checkboxes':
              $options = [];
              foreach (explode("\n", $options_raw) as $opt) {
                $opt = trim($opt);
                if ($opt !== '') {
                  $options[$opt] = $opt;
                }
              }
              $form[$field_name] = [
                '#type' => $type === 'select' ? 'select' : ($type === 'radio' ? 'radios' : 'checkboxes'),
                '#title' => $label,
                '#options' => $options,
              ];
              break;

            default:
              $form[$field_name] = [
                '#type' => 'textfield',
                '#title' => $label,
              ];
          }
        }
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('RSVP Now'),
      '#attributes' => ['class' => ['button', 'button--primary', 'rsvp-submit-btn']],
    ];

    // ✅ Expose values to template for conditional rendering
    $form['#type'] = 'rsvp';
    $form['#event'] = $event;
    $form['#user_has_rsvped'] = $has_rsvped;

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $event_id = is_array($values['event_id']) ? reset($values['event_id']) : $values['event_id'];
    $current_user = \Drupal::currentUser();
    $uid = $current_user->isAuthenticated() ? $current_user->id() : 0;

    $extra_answers = [];
    foreach ($form as $key => $element) {
      if (strpos($key, 'extra_') === 0 && isset($values[$key])) {
        $extra_answers[$key] = $values[$key];
      }
    }

    // Load event to check one-per-user logic
    $event = Node::load($event_id);
    if ($event && $event->hasField('field_limit_one_rsvp') && $event->get('field_limit_one_rsvp')->value) {
      $connection = Database::getConnection();
      $query = $connection->select('myeventlane_rsvp', 'r')
        ->fields('r', ['id'])
        ->condition('event_nid', $event_id)
        ->condition('uid', $uid)
        ->range(0, 1);
      $existing = $query->execute()->fetchField();

      if ($existing) {
        \Drupal::messenger()->addError($this->t('You have already RSVP’d to this event.'));
        return;
      }
    }

   // Save RSVP and get the new ID in one go.
  $rsvp_id = \Drupal::database()
    ->insert('myeventlane_rsvp')
    ->fields([
      'event_nid'  => $event_nid,
      'uid'        => $uid,
      'first_name' => $first_name,
      'last_name'  => $last_name,
      'email'      => $email,
      'comments'   => $comments,
      'created'    => \Drupal::time()->getCurrentTime(),
    ])
    ->execute();

  // Optional: guard/log if insert failed.
  if (empty($rsvp_id)) {
    \Drupal::logger('myeventlane_rsvp')->error('RSVP insert returned no ID for %mail on event %nid', [
      '%mail' => $email,
      '%nid'  => $event_nid,
    ]);
    $this->messenger()->addError($this->t('Something went wrong saving your RSVP. Please try again.'));
    return;
  }

  // Issue the claim email for anonymous users (or always, if you prefer).
    \Drupal::service('mel_auth_claim.service')->issue($email, [
      'source' => 'rsvp_success',
      'rsvp_ids' => [$rsvp_id],
      // 'order_ids' => [$order_id], // if purchase
    ]);
  }
}