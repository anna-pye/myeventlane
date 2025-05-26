<?php

namespace Drupal\commerce_stripe_webhook_event\Plugin\views\filter;

use Drupal\commerce_stripe_webhook_event\WebhookEvent;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Exposes webhook event types to views module.
 *
 * @ViewsFilter("commerce_stripe_webhook_event_types")
 */
class WebhookEventTypes extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = WebhookEvent::getEventTypes();
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value']['#access'] = !empty($form['value']['#options']);
  }

}
