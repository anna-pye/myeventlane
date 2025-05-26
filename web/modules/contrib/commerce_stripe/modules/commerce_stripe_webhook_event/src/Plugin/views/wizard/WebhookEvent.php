<?php

namespace Drupal\commerce_stripe_webhook_event\Plugin\views\wizard;

use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * Defines a wizard for the commerce_stripe_webhook_event table.
 *
 * @ViewsWizard(
 *   id = "commerce_stripe_webhook_event",
 *   module = "commerce_stripe_webhook_event",
 *   base_table = "commerce_stripe_webhook_event",
 *   title = @Translation("Commerce Stripe Webhook Events")
 * )
 */
class WebhookEvent extends WizardPluginBase {

  /**
   * Set the created column.
   *
   * @var string
   */
  protected $createdColumn = 'received';

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'view commerce stripe webhook event';

    return $display_options;
  }

}
