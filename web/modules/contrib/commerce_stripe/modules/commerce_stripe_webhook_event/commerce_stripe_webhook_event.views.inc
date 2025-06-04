<?php

/**
 * @file
 * Provide views data for commerce_stripe_webhook_event.module.
 */

/**
 * Implements hook_views_data().
 */
function commerce_stripe_webhook_event_views_data() {
  $data = [];

  $data['commerce_stripe_webhook_event']['table']['group'] = t('Commerce');
  $data['commerce_stripe_webhook_event']['table']['wizard_id'] = 'commerce_stripe_webhook_event';

  $data['commerce_stripe_webhook_event']['table']['base'] = [
    'field' => 'webhook_event_id',
    'title' => t('Stripe webhook events'),
    'help' => t('Contains a list of webhooks sent by Stripe.'),
  ];

  $data['commerce_stripe_webhook_event']['webhook_event_id'] = [
    'title' => t('Webhook event ID'),
    'help' => t('Unique webhook event ID.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['stripe_event_id'] = [
    'title' => t('Stripe event ID'),
    'help' => t('The Stripe ID of the webhook event.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['type'] = [
    'title' => t('Type'),
    'help' => t('The type of the webhook event.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'commerce_stripe_webhook_event_types',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['payload'] = [
    'title' => t('Payload'),
    'help' => t('The payload of the webhook event.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['signature'] = [
    'title' => t('Signature'),
    'help' => t('The signature of the webhook event.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['reason'] = [
    'title' => t('Reason'),
    'help' => t('The reason for the webhook event status.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['stripe_object_type'] = [
    'title' => t('Stripe object type'),
    'help' => t('The stripe object type for the webhook event.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['stripe_object_id'] = [
    'title' => t('Stripe object id'),
    'help' => t('The stripe object id for the webhook event.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['status'] = [
    'title' => t('Status'),
    'help' => t('The status of the webhook event.'),
    'field' => [
      'id' => 'machine_name',
      'options callback' => 'Drupal\commerce_stripe_webhook_event\WebhookEvent::getStatuses',
    ],
    'filter' => [
      'id' => 'in_operator',
      'options callback' => 'Drupal\commerce_stripe_webhook_event\WebhookEvent::getStatuses',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['ip'] = [
    'title' => t('IP'),
    'help' => t('IP of the server that sent the webhook event.'),
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_stripe_webhook_event']['processed'] = [
    'title' => t('Processed'),
    'help' => t('Date when the event was processed.'),
    'field' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
  ];

  $data['commerce_stripe_webhook_event']['received'] = [
    'title' => t('Received'),
    'help' => t('Date when the event was received.'),
    'field' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
  ];

  return $data;
}
