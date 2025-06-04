<?php

namespace Drupal\commerce_stripe_webhook_event\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form before purging the webhook events.
 *
 * @internal
 */
class WebhookEventPurgeConfirmForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->connection = $container->get('database');
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_stripe_webhook_event_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to purge webhook events?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('commerce_stripe_webhook_event.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->getRequest()->getSession()->remove('commerce_stripe_webhook_event_overview_filter');
    $retention_time = $this->config('commerce_stripe_webhook_event')->get('retention_time');
    $request_time = $this->time->getRequestTime();
    $this->connection->delete('commerce_stripe_webhook_event')
      ->condition('status', '0', '>')
      ->condition('processed', '0', '>')
      ->condition('processed', ($request_time - $retention_time), '<')
      ->execute();
    $this->messenger()->addStatus($this->t('Webhook events purged.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
