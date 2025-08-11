<?php

namespace Drupal\commerce_stripe_webhook_event;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LogLevel;
use Stripe\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides functionality for webhook events.
 */
class WebhookEvent {

  public const STATUS_UNPROCESSED = 0;

  public const STATUS_SUCCEEDED = 1;

  public const STATUS_FAILED = 2;

  public const STATUS_SKIPPED = 3;

  /**
   * Gets an array of statuses.
   *
   * @return array
   *   An array of statuses.
   */
  public static function getStatuses(): array {
    return [
      self::STATUS_UNPROCESSED => t('Unprocessed'),
      self::STATUS_SUCCEEDED => t('Succeeded'),
      self::STATUS_FAILED => t('Failed'),
      self::STATUS_SKIPPED => t('Skipped'),
    ];
  }

  /**
   * Gets an array of raw statuses.
   *
   * @return array
   *   An array of raw statuses.
   */
  public static function getStatusesRaw(): array {
    return [
      self::STATUS_UNPROCESSED => 'unprocessed',
      self::STATUS_SUCCEEDED => 'succeeded',
      self::STATUS_FAILED => 'failed',
      self::STATUS_SKIPPED => 'skipped',
    ];
  }

  /**
   * Gathers a list of uniquely defined webhook event types.
   *
   * @return array
   *   List of uniquely defined webhook event types.
   */
  public static function getEventTypes(): array {
    return \Drupal::database()
      ->query('SELECT DISTINCT([type]) FROM {commerce_stripe_webhook_event} ORDER BY [type]')
      ->fetchAllKeyed(0, 0);
  }

  /**
   * Creates a list of webhook event filters that can be applied.
   *
   * @return array
   *   Associative array of filters. The top-level keys are used as the form
   *   element names for the filters, and the values are arrays with the
   *   following elements:
   *   - title: Title of the filter.
   *   - where: The filter condition.
   *   - options: Array of options for the select list for the filter.
   */
  public static function getFilters(): array {
    $filters = [];

    foreach (self::getEventTypes() as $type) {
      $types[$type] = $type;
    }

    if (!empty($types)) {
      $filters['type'] = [
        'title' => t('Type'),
        'where' => 'w.type = ?',
        'options' => $types,
      ];
    }

    $filters['status'] = [
      'title' => t('Status'),
      'where' => 'w.status = ?',
      'options' => self::getStatuses(),
    ];

    return $filters;
  }

  /**
   * Insert a new webhook event.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Stripe\Event $webhook_event
   *   The webhook event.
   * @param string $stripe_signature
   *   The stripe signature.
   *
   * @return int|null
   *   The ID of the webhook event or NULL if it already exists.
   *
   * @throws \Exception
   */
  public static function insert(Request $request, Event $webhook_event, string $stripe_signature): ?int {
    $payload = $request->getContent();

    /** @var \Stripe\ApiResource $event_object */
    $event_object = $webhook_event->data->object;

    $database = \Drupal::database();
    if ($database->select('commerce_stripe_webhook_event')->condition('stripe_event_id', $webhook_event->id)->countQuery()->execute()->fetchField() > 0) {
      // If we have received this event previously, we can ignore it.
      // We might consider logging this, to determine if we are receiving
      // an inordinate amount of repeat events.
      return NULL;
    }
    $webhook_event_id = $database
      ->insert('commerce_stripe_webhook_event')
      ->fields([
        'stripe_event_id' => $webhook_event->id,
        'type' => $webhook_event->type,
        'status' => self::STATUS_UNPROCESSED,
        'payload' => $payload,
        'ip' => $request->getClientIp() ?? '',
        'processed' => 0,
        'received' => \Drupal::time()->getRequestTime(),
        'signature' => $stripe_signature,
        'stripe_object_type' => $event_object->object,
        'stripe_object_id' => $event_object->id,
      ])
      ->execute();
    if ($webhook_event_id > 0) {
      return $webhook_event_id;
    }
    throw new \RuntimeException('Unable to store event');
  }

  /**
   * Update the webhook event status.
   *
   * @param int $webhook_event_id
   *   The id of the webhook event.
   * @param int $status
   *   The status.
   * @param string|null $reason
   *   The reason for the status.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The related entity.
   */
  public static function updateStatus(int $webhook_event_id, int $status, ?string $reason, ?EntityInterface $entity = NULL): void {
    $database = \Drupal::database();
    $fields = [
      'status' => $status,
      'processed' => ($status !== self::STATUS_UNPROCESSED) ? \Drupal::time()->getCurrentTime() : 0,
      'reason' => $reason ?? '',
    ];
    if ($entity !== NULL && $entity->id() > 0) {
      $fields['entity_type'] = $entity->getEntityTypeId();
      $fields['entity_id'] = $entity->id();
    }
    $database->update('commerce_stripe_webhook_event')
      ->fields($fields)
      ->condition('webhook_event_id', $webhook_event_id)
      ->execute();
  }

  /**
   * Fetch a webhook event.
   *
   * @param int $webhook_event_id
   *   The webhook_event_id.
   * @param string[] $status
   *   The status filter.
   *
   * @return \Stripe\Event|null
   *   The stripe webhook event.
   */
  public static function get(int $webhook_event_id, array $status = [self::STATUS_UNPROCESSED]): ?Event {
    $database = \Drupal::database();

    $commerce_stripe_webhook_event = $database->select('commerce_stripe_webhook_event', 'cswe')
      ->fields('cswe')
      ->condition('webhook_event_id', $webhook_event_id)
      ->execute()
      ->fetchObject();
    if ($commerce_stripe_webhook_event && in_array((int) $commerce_stripe_webhook_event->status, $status, TRUE)) {
      $payload = $commerce_stripe_webhook_event->payload;
      $data = Json::decode($payload);
      return Event::constructFrom($data);
    }
    return NULL;
  }

  /**
   * Process a webhook event.
   *
   * @param array $payload
   *   The payload.
   *
   * @return bool
   *   Whether the job successfully completed.
   *
   * @throws \Throwable
   */
  public static function process(array $payload): bool {
    try {
      $webhook_event_id = $payload['webhook_event_id'] ?? NULL;
      $payment_gateway_id = $payload['payment_gateway_id'] ?? NULL;
      if (($webhook_event_id === NULL) || ($payment_gateway_id === NULL)) {
        throw new \RuntimeException('Invalid job payload.');
      }
      $webhook_event = self::get($webhook_event_id);
      if ($webhook_event === NULL) {
        throw new \RuntimeException("No unprocessed webhook event found. ($webhook_event_id)");
      }
      $payment_gateway_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
      /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface|null $payment_gateway */
      $payment_gateway = $payment_gateway_storage->load($payment_gateway_id);
      if ($payment_gateway === NULL) {
        throw new \RuntimeException("Invalid payment gateway. ($payment_gateway_id)");
      }
      /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElement $payment_gateway_plugin */
      $payment_gateway_plugin = $payment_gateway->getPlugin();
      $payment_gateway_plugin->processWebhook($webhook_event_id, $webhook_event);
      return TRUE;
    }
    catch (\Throwable $throwable) {
      \Drupal::logger('commerce_stripe_webhook_event')->log(LogLevel::ERROR, '%type: @message in %function (line %line of %file).', Error::decodeException($throwable));
      throw $throwable;
    }
  }

}
