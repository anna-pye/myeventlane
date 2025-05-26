<?php

declare(strict_types=1);

namespace Drupal\lb_styles_conditions\Form;

use Drupal\conditions_helper\Form\ConditionSelectorSettingsFormBase;

/**
 * Configure Layout Builder Styles Conditions settings for this site.
 */
class SettingsForm extends ConditionSelectorSettingsFormBase {

  /**
   * Config settings name.
   *
   * @var string
   */
  public const SETTINGS = 'lb_styles_conditions.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lb_styles_conditions_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [static::SETTINGS];
  }

}
