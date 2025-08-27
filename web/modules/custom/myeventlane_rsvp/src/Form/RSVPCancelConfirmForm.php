<?php

declare(strict_types=1);

namespace Drupal\myeventlane_rsvp\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\myeventlane_rsvp\Entity\RsvpSubmission;

/**
 * Confirm cancellation of an RSVP.
 *
 * Route provides {rsvp} (entity:rsvp_submission) and ?token=... (optional).
 */
final class RSVPCancelConfirmForm extends ConfirmFormBase {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Access\CsrfTokenGenerator */
  protected $csrf;

  /** @var int|null */
  protected $rsvpId;

  /** @var bool */
  protected $tokenValid = FALSE;

  public static function create(ContainerInterface $container): self {
    $instance = new self();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->csrf = $container->get('csrf_token');
    return $instance;
  }

  public function getFormId(): string {
    return 'myeventlane_rsvp_cancel_confirm';
  }

  /**
   * Title callback used in routing.
   */
  public static function getTitle(RsvpSubmission $rsvp = NULL): string {
    return (string) t('Cancel RSVP');
  }

  public function getQuestion(): string {
    return (string) t('Are you sure you want to cancel this RSVP?');
  }

  public function getCancelUrl(): Url {
    // Back to profile or front as fallback.
    if (\Drupal::hasService('myeventlane_rsvp.redirect_resolver')) {
      /** @var \Drupal\myeventlane_rsvp\Service\RsvpRedirectResolver $resolver */
      $resolver = \Drupal::service('myeventlane_rsvp.redirect_resolver');
      return $resolver->getAfterCancelFallback();
    }
    return Url::fromRoute('<front>');
  }

  public function getConfirmText(): string {
    return (string) t('Cancel');
  }

  public function buildForm(array $form, FormStateInterface $form_state, RsvpSubmission $rsvp = NULL): array {
    $this->rsvpId = $rsvp?->id();

    if (!$this->rsvpId) {
      $this->messenger()->addError(t('RSVP not found.'));
      return $this->redirect($this->getCancelUrl()->getRouteName(), $this->getCancelUrl()->getRouteParameters())->send();
    }

    // Validate CSRF token if provided.
    $provided = (string) ($this->getRequest()->query->get('token') ?? '');
    $expected = $this->csrf->get('myeventlane_rsvp:cancel:' . $this->rsvpId);
    $this->tokenValid = hash_equals($expected, $provided);

    if (!$this->tokenValid) {
      $this->messenger()->addWarning(t('Security token missing or expired. You can still confirm below.'));
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\myeventlane_rsvp\Entity\RsvpSubmission|null $rsvp */
    $rsvp = $this->entityTypeManager->getStorage('rsvp_submission')->load($this->rsvpId ?? 0);
    if (!$rsvp) {
      $this->messenger()->addError(t('RSVP not found.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    // If token invalid, still allow explicit confirmation here.
    $rsvp->set('status', 'cancelled')->save();
    $this->messenger()->addStatus(t('Your RSVP was cancelled.'));

    if (\Drupal::hasService('myeventlane_rsvp.redirect_resolver')) {
      /** @var \Drupal\myeventlane_rsvp\Service\RsvpRedirectResolver $resolver */
      $resolver = \Drupal::service('myeventlane_rsvp.redirect_resolver');
      $form_state->setRedirectUrl($resolver->getAfterCancel($rsvp));
      return;
    }

    $form_state->setRedirect('<front>');
  }
}
