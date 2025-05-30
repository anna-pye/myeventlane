<?php

namespace Drupal\commerce_number_pattern\Plugin\Commerce\NumberPattern;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_number_pattern\Attribute\CommerceNumberPattern;
use Drupal\commerce_number_pattern\Sequence;

/**
 * Provides a yearly number pattern.
 */
#[CommerceNumberPattern(
  id: "yearly",
  label: new TranslatableMarkup("Yearly (Reset every year)"),
)]
class Yearly extends SequentialNumberPatternBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'pattern' => '[pattern:year]-[pattern:number]',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldReset(Sequence $current_sequence) {
    // Reset the sequence if the current one is from a previous year.
    $generated_time = DrupalDateTime::createFromTimestamp($current_sequence->getGeneratedTime());
    $current_time = DrupalDateTime::createFromTimestamp($this->time->getCurrentTime());

    return $generated_time->format('Y') != $current_time->format('Y');
  }

}
