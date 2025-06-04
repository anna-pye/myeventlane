<?php

namespace Drupal\search_api_taxonomy_machine_name\Plugin\views\filter;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy_machine_name\Plugin\views\filter\TaxonomyIndexMachineName;

/**
 * Filtering by taxonomy machine name.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("search_api_taxonomy_machine_name")
 */
class SearchApiTaxonomyMachineName extends TaxonomyIndexMachineName {

  use SearchApiFilterTrait;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['hierarchy_parent'] = ['default' => 0];
    $options['hierarchy_max_depth'] = ['default' => NULL];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildExtraOptionsForm($form, $form_state);

    $form['hierarchy_parent'] = [
      '#type' => 'number',
      '#title' => $this->t('Start at level'),
      '#default_value' => $this->options['hierarchy_parent'] ?? 0,
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="options[hierarchy]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hierarchy_max_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Max depth'),
      '#default_value' => $this->options['hierarchy_max_depth'] ?? NULL,
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="options[hierarchy]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateExtraOptionsForm($form, FormStateInterface $form_state): void {
    $options = $form_state->getValue('options');
    if (isset($options['hierarchy_max_depth']) && $options['hierarchy_max_depth'] === '') {
      $options['hierarchy_max_depth'] = NULL;
      $form_state->setValue('options', $options);
    }
  }

  /**
   * Override of the method in the extended TaxonomyIndexMachineName class to apply the new hierarchy settings.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form state interface.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function valueForm(array &$form, FormStateInterface $form_state) {
    $vocabulary = $this->vocabularyStorage->load($this->options['vid']);
    if ($vocabulary === NULL && $this->options['limit']) {
      $form['markup'] = [
        '#markup' => '<div class="js-form-item form-item">' . $this->t('An invalid vocabulary is selected. Please change it in the options.') . '</div>',
      ];
      return;
    }

    if ($this->options['type'] === 'textfield') {
      $terms = $this->value ? Term::loadMultiple(($this->value)) : [];
      $form['value'] = [
        '#title' => $this->options['limit'] ? $this->t('Select terms from vocabulary @voc', ['@voc' => $vocabulary->label()]) : $this->t('Select terms'),
        '#type' => 'textfield',
        '#default_value' => EntityAutocomplete::getEntityLabels($terms),
      ];

      if ($this->options['limit']) {
        $form['value']['#type'] = 'entity_autocomplete';
        $form['value']['#target_type'] = 'taxonomy_term';
        $form['value']['#selection_settings']['target_bundles'] = [$vocabulary->id()];
        $form['value']['#tags'] = TRUE;
        $form['value']['#process_default_value'] = FALSE;
      }
    }
    else {
      if (!empty($this->options['hierarchy']) && $this->options['limit']) {
        // This is the only change in comparison with the overridden method.
        $tree = $this->termStorage->loadTree($vocabulary->id(), $this->options['hierarchy_parent'], $this->options['hierarchy_max_depth'], TRUE);
        $options = [];
        $options_attributes = [];

        if ($tree) {
          foreach ($tree as $term) {
            $options[$term->get('machine_name')
              ->get(0)->value] = \Drupal::service('entity.repository')
              ->getTranslationFromContext($term)
              ->label();

            $options_attributes[$term->get('machine_name')
              ->get(0)->value] = ['class' => ['level-' . $term->depth]];
          }
        }
      }
      else {
        $options = [];
        $query = \Drupal::entityQuery('taxonomy_term')
          // @todo Sorting on vocabulary properties -
          //   https://www.drupal.org/node/1821274.
          ->sort('weight')
          ->sort('name')
          ->addTag('taxonomy_term_access');
        if ($this->options['limit']) {
          $query->condition('vid', $vocabulary->id());
        }
        $terms = Term::loadMultiple($query->accessCheck(TRUE)->execute());
        foreach ($terms as $term) {
          $options[$term->get('machine_name')
            ->get(0)->value] = \Drupal::service('entity.repository')
            ->getTranslationFromContext($term)
            ->label();
        }
      }

      $default_value = (array) $this->value;

      if ($exposed = $form_state->get('exposed')) {
        $identifier = $this->options['expose']['identifier'];

        if (!empty($this->options['expose']['reduce'])) {
          $options = $this->reduceValueOptions($options);

          if (!empty($this->options['expose']['multiple']) && empty($this->options['expose']['required'])) {
            $default_value = [];
          }
        }

        if (empty($this->options['expose']['multiple'])) {
          if (empty($this->options['expose']['required']) && (empty($default_value) || !empty($this->options['expose']['reduce']))) {
            $default_value = 'All';
          }
          elseif (empty($default_value)) {
            $keys = array_keys($options);
            $default_value = array_shift($keys);
          }
          // Due to #1464174 there is a chance that array('')
          // was saved in the admin ui. Let's choose a safe default value.
          elseif ($default_value == ['']) {
            $default_value = 'All';
          }
          else {
            $copy = $default_value;
            $default_value = array_shift($copy);
          }
        }
      }
      $form['value'] = [
        '#type' => 'select',
        '#title' => $this->options['limit'] ? $this->t('Select terms from vocabulary @voc', ['@voc' => $vocabulary->label()]) : $this->t('Select terms'),
        '#multiple' => TRUE,
        '#options' => $options,
        '#options_attributes' => $options_attributes ?? [],
        '#size' => min(9, count($options)),
        '#default_value' => $default_value,
      ];

      $user_input = $form_state->getUserInput();
      if ($exposed && isset($identifier) && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $default_value;
        $form_state->setUserInput($user_input);
      }
    }

    if (!$form_state->get('exposed')) {
      // Retain the helper option.
      $this->helper->buildOptionsForm($form, $form_state);

      // Show help text if not exposed to end users.
      $form['value']['#description'] = t('Leave blank for all. Otherwise, the first selected term will be the default instead of "Any".');
    }
  }

}
