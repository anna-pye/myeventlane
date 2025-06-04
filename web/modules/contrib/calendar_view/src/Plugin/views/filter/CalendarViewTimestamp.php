<?php

namespace Drupal\calendar_view\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\Date;

/**
 * Calendar View filter to "Jump to" a given date and/or time.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("calendar_view_timestamp")
 */
class CalendarViewTimestamp extends Date {

  /**
   * {@inheritDoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    // Check input is an acceptable date format.
    $identifier = $this->options['expose']['identifier'];
    $value = &$form_state->getValue($identifier);
    if (empty($value)) {
      return;
    }
    elseif (!_calendar_view_ensure_timestamp_value($value)) {
      $form_state->setError($form[$identifier], $this->t('Invalid date format.'));
      return;
    }

    // Marks as required to check date format as per core.
    $value = ['value' => $value];
    $this->options['expose']['required'] = TRUE;
    parent::validateExposed($form, $form_state);
  }

  /**
   * No query for us. We just want the query arg to be populated.
   */
  public function query() {
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.query_args:calendar_timestamp']);
  }

}
