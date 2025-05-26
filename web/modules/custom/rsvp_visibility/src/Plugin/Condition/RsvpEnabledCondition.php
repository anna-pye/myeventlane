<?php

namespace Drupal\rsvp_visibility\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a condition to check if RSVP is enabled on an Event node.
 *
 * @Condition(
 *   id = "rsvp_enabled_condition",
 *   label = @Translation("RSVP Enabled on Event"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", required = FALSE, label = @Translation("Node"))
 *   }
 * )
 */
class RsvpEnabledCondition extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if ($this->isNegated()) {
      return !$this->passes();
    }
    return $this->passes();
  }

  /**
   * Checks whether the condition passes.
   */
  protected function passes() {
    $node = $this->getContextValue('node');
    return ($node instanceof NodeInterface &&
            $node->bundle() === 'event' &&
            $node->hasField('field_rsvp_enabled') &&
            $node->get('field_rsvp_enabled')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('RSVP Enabled must be TRUE on Event node');
  }
}
