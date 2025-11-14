<?php

namespace Drupal\myeventlane_rsvp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RSVPEditForm.
 * Provides a form for editing an RSVP submission.
 */
class RSVPEditForm extends FormBase {
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'myeventlane_rsvp_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rsvp_id = NULL) {
    // Basic check: RSVP ID must be present.
    if (empty($rsvp_id)) {
      $this->messenger()->addError($this->t('RSVP not found.'));
      return $form;
    }

    $connection = Database::getConnection();

    // Fetch RSVP record.
    $rsvp = $connection->select('myeventlane_rsvp', 'r')
      ->fields('r')
      ->condition('id', $rsvp_id)
      ->execute()
      ->fetchAssoc();

    if (!$rsvp) {
      $this->messenger()->addError($this->t('RSVP not found.'));
      return $form;
    }

    // Access check: Only the RSVP owner can edit.
    $account = $this->currentUser();
    if ($rsvp['uid'] != $account->id() && !$account->hasPermission('administer site configuration')) {
      $this->messenger()->addError($this->t('Access denied.'));
      return $form;
    }

    // Form wrapper class for SCSS styling.
    $form['#attributes']['class'][] = 'rsvp-edit-form';

    // Hidden RSVP ID (for submit).
    $form['rsvp_id'] = [
      '#type' => 'hidden',
      '#value' => $rsvp_id,
    ];

    // Name fields.
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#default_value' => $rsvp['first_name'],
      '#attributes' => ['class' => ['form-control']],
      '#required' => TRUE,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#default_value' => $rsvp['last_name'],
      '#attributes' => ['class' => ['form-control']],
      '#required' => TRUE,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $rsvp['email'],
      '#attributes' => ['class' => ['form-control']],
      '#required' => TRUE,
    ];

    // Comments (optional).
    $form['comments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comments (optional)'),
      '#default_value' => $rsvp['comments'],
      '#attributes' => ['class' => ['form-control']],
    ];

    // Extra answers (optional, handled as JSON array/string).
    if (!empty($rsvp['extra_answers'])) {
      // Example: parse JSON to array for display/edit, or keep hidden.
      // For now, we HIDE this field from the user, but keep for future use.
      // $extra_answers = json_decode($rsvp['extra_answers'], TRUE) ?? [];
      // $form['extra_answers'] = [
      //   '#type' => 'value',
      //   '#value' => $rsvp['extra_answers'],
      // ];
      // Or, just skip adding to the form for now.
    }

    // Submit button with primary pill style.
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Changes'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Example validation: ensure fields are not empty.
    if (empty(trim($form_state->getValue('first_name')))) {
      $form_state->setErrorByName('first_name', $this->t('First name is required.'));
    }
    if (empty(trim($form_state->getValue('last_name')))) {
      $form_state->setErrorByName('last_name', $this->t('Last name is required.'));
    }
    if (empty(trim($form_state->getValue('email')))) {
      $form_state->setErrorByName('email', $this->t('Email is required.'));
    }
    elseif (!\Drupal::service('email.validator')->isValid($form_state->getValue('email'))) {
      $form_state->setErrorByName('email', $this->t('Enter a valid email address.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rsvp_id = $form_state->getValue('rsvp_id');
    $fields = [
      'first_name' => $form_state->getValue('first_name'),
      'last_name' => $form_state->getValue('last_name'),
      'email' => $form_state->getValue('email'),
      'comments' => $form_state->getValue('comments'),
      // Extra answers would go here if handled.
    ];

    $connection = Database::getConnection();
    $connection->update('myeventlane_rsvp')
      ->fields($fields)
      ->condition('id', $rsvp_id)
      ->execute();

    // Add a styled confirmation message (handled in SCSS).
    $this->messenger()->addStatus([
      '#markup' => '<div class="rsvp-confirmation-card">' .
        $this->t('Your RSVP has been updated!') .
        '</div>',
    ]);

    // Optionally redirect back to the user dashboard after update:
    $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
    $response->send();
    // Or, comment out above and stay on form if you want user to see the confirmation in context.
  }
}
