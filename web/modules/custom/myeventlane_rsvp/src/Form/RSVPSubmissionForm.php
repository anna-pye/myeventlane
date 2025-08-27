<?php

namespace Drupal\myeventlane_rsvp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\myeventlane_recommend\Service\AffinityManager;

/**
 * RSVP submission form (dynamic, supports event-level extra questions).
 */
class RSVPSubmissionForm extends FormBase {

  public function getFormId(): string {
    return 'myeventlane_rsvp_submission_form';
  }

  /**
   * Build the RSVP form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param int|null $event_id
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, $event_id = NULL): array {
    if (empty($event_id) || !is_numeric($event_id)) {
      $this->messenger()->addError($this->t('This RSVP form is missing its Event context. Please return to the event page and try again.'));
      return [];
    }

    // Load the event node.
    /** @var \Drupal\node\Entity\Node|null $event */
    $event = Node::load((int) $event_id);

    // Get current user.
    $current_user = \Drupal::currentUser();
    $uid = $current_user->isAuthenticated() ? (int) $current_user->id() : 0;

    // Check if user has already RSVPed (for limiting logic).
    $has_rsvped = FALSE;
    if ($event && $event->hasField('field_limit_one_rsvp') && (bool) $event->get('field_limit_one_rsvp')->value && $uid > 0) {
      $query = Database::getConnection()->select('myeventlane_rsvp', 'r')
        ->fields('r', ['id'])
        ->condition('event_nid', (int) $event_id)
        ->condition('uid', $uid)
        ->range(0, 1);
      $has_rsvped = (bool) $query->execute()->fetchField();
    }

    // Add hidden event ID.
    $form['event_id'] = [
      '#type' => 'hidden',
      '#value' => (int) $event_id,
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

    // Dynamically add extra fields from field_attendee_questions (Paragraphs).
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
                $opt = trim((string) $opt);
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

    // ✅ Expose values to template for conditional rendering.
    $form['#type'] = 'rsvp';
    $form['#event'] = $event;
    $form['#user_has_rsvped'] = $has_rsvped;

    return $form;
  }

  /**
   * Submit handler: saves the RSVP row, sends claim email, redirects to Thank You.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    // Extract core RSVP values from the form state.
    $event_id   = (int) (is_array($values['event_id']) ? reset($values['event_id']) : $values['event_id']);
    $first_name = trim((string) ($values['first_name'] ?? ''));
    $last_name  = trim((string) ($values['last_name'] ?? ''));
    $email      = trim((string) ($values['email'] ?? ''));
    $comments   = (string) ($values['comments'] ?? '');

    // Resolve email for logged-in users if field is blank.
    if ($email === '' && \Drupal::currentUser()->isAuthenticated()) {
      $account = \Drupal::entityTypeManager()->getStorage('user')->load(\Drupal::currentUser()->id());
      if ($account && method_exists($account, 'getEmail')) {
        $email = (string) $account->getEmail();
      }
    }

    // Validate email before we do anything else.
    if ($email === '' || !\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address to receive your RSVP confirmation.'));
      return;
    }

    // Collect dynamic extra answers from paragraph-driven fields (kept for future use).
    $extra_answers = [];
    foreach ($form as $key => $element) {
      if (strpos((string) $key, 'extra_') === 0 && isset($values[$key])) {
        $extra_answers[$key] = $values[$key];
      }
    }

    // One-per-user guard (if enabled on event).
    $uid = \Drupal::currentUser()->isAuthenticated() ? (int) \Drupal::currentUser()->id() : 0;
    $event = Node::load($event_id);
    if ($event && $event->hasField('field_limit_one_rsvp') && (bool) $event->get('field_limit_one_rsvp')->value) {
      $connection = Database::getConnection();
      $existing = $connection->select('myeventlane_rsvp', 'r')
        ->fields('r', ['id'])
        ->condition('event_nid', $event_id)
        ->condition('uid', $uid)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($existing) {
        $this->messenger()->addError($this->t('You have already RSVP’d to this event.'));
        return;
      }
    }

    // Insert RSVP.
    $rsvp_id = \Drupal::database()
      ->insert('myeventlane_rsvp')
      ->fields([
        'event_nid'  => $event_id,
        'uid'        => $uid,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'comments'   => $comments,
        'created'    => \Drupal::time()->getCurrentTime(),
      ])
      ->execute();

      // ... inside submitForm(), AFTER you've created/saved the RSVP record:
      $account = \Drupal::currentUser();
      $uid = (int) $account->id();

      if ($uid > 0 && $event instanceof NodeInterface) {
        // Collect the event's topic term IDs.
        $tids = [];
        if ($event->hasField('field_topics') && !$event->get('field_topics')->isEmpty()) {
          foreach ($event->get('field_topics')->referencedEntities() as $term) {
            $tids[] = (int) $term->id();
          }
        }

        if (!empty($tids)) {
          /** @var \Drupal\myeventlane_recommend\Service\AffinityManager $aff */
          $aff = \Drupal::service('myeventlane_recommend.affinity');
          // RSVPs are "lighter" than purchases.
          $aff->bump($uid, $tids, 1.0);
        }
      }

    if (empty($rsvp_id)) {
      \Drupal::logger('myeventlane_rsvp')->error('RSVP insert returned no ID for %mail on event %nid', [
        '%mail' => $email,
        '%nid'  => $event_id,
      ]);
      $this->messenger()->addError($this->t('Something went wrong saving your RSVP. Please try again.'));
      return;
    }

    // Fire claim email (works for guests and logged-in users).
    \Drupal::service('mel_auth_claim.service')->issue($email, [
      'source'   => 'rsvp_success',
      'rsvp_ids' => [$rsvp_id],
    ]);

    // // Success message (will persist across redirect if configured).
    // $this->messenger()->addStatus($this->t('Thanks! We’ve recorded your RSVP and emailed you a confirmation link.'));

    // Compute Thank You redirect (per-event field or global default) + UTM.
    /** @var \Drupal\myeventlane_rsvp\Service\RsvpRedirectResolver $resolver */
    $resolver = \Drupal::service('myeventlane_rsvp.redirect_resolver');
    $redirect_url = $resolver->resolveForEvent((int) $event_id, ['medium' => 'rsvp']);

    // Optionally clear the message if keep_message is disabled.
    $keep = (bool) (\Drupal::config('myeventlane_rsvp.settings')->get('thank_you.keep_message') ?? TRUE);
    if (!$keep) {
      // Clear status messages so destination page is clean.
      drupal_get_messages('status', TRUE);
    }

    // Redirect to the resolved Thank You URL.
    $form_state->setRedirect('myeventlane_rsvp.thankyou', ['event' => $event_id]);

  }
  
}
