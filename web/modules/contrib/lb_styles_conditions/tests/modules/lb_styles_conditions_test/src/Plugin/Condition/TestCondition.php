<?php

declare(strict_types=1);

namespace Drupal\lb_styles_conditions_test\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a test condition.
 *
 * This is a direct copy from the field_visibility_conditions_test module.
 *
 * @Condition(
 *   id = "test_condition",
 *   label = @Translation("Test condition"),
 * )
 */
class TestCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['condition_met'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Condition is met'),
      '#default_value' => $this->configuration['condition_met'] ?? FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['condition_met'] = $form_state->getValue('condition_met');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'condition_met' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    return !empty($this->configuration['condition_met']);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): TranslatableMarkup|string {
    return $this->t('Test condition is @state', [
      '@state' => !empty($this->configuration['condition_met']) ? $this->t('met') : $this->t('not met'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function isNegated(): bool {
    // The negate property is handled by the condition plugin system itself.
    // This method is often not needed unless overriding core behavior.
    return $this->configuration['negate'] ?? FALSE;
  }

}
