<?php

declare(strict_types=1);

namespace Drupal\lb_styles_conditions;

use Drupal\conditions_helper\ConditionsEvaluator;
use Drupal\conditions_helper\Form\ConditionsFormBuilder;
use Drupal\Core\Condition\ConditionAccessResolverTrait;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder_styles\LayoutBuilderStyleInterface;
use Drupal\lb_styles_conditions\Form\SettingsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for altering layout builder styles forms.
 */
class FormAlters implements ContainerInjectionInterface {

  use ConditionAccessResolverTrait;
  use ContextAwarePluginAssignmentTrait;
  use StringTranslationTrait;

  /**
   * Constructs a FormAlters object.
   */
  public function __construct(
    protected ConditionManager $conditionManager,
    protected ContextRepositoryInterface $contextRepository,
    protected ModuleHandlerInterface $moduleHandler,
    protected ContextHandlerInterface $contextHandler,
    protected ConfigFactoryInterface $configFactory,
    protected EntityRepositoryInterface $entityRepository,
    protected ConditionsFormBuilder $conditionsHelperFormBuilder,
    protected ConditionsEvaluator $conditionsEvaluator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $plugin_manager = $container->get('plugin.manager.condition');
    $context_repository = $container->get('context.repository');
    $module_handler = $container->get('module_handler');
    $context_handler = $container->get('context.handler');
    $config_factory = $container->get('config.factory');
    $entity_repository = $container->get('entity.repository');
    $conditions_helper_form_builder = $container->get('conditions_helper.form_builder');
    $conditions_evaluator = $container->get('conditions_helper.evaluator');

    assert($plugin_manager instanceof ConditionManager);
    assert($context_repository instanceof ContextRepositoryInterface);
    assert($module_handler instanceof ModuleHandlerInterface);
    assert($context_handler instanceof ContextHandlerInterface);
    assert($config_factory instanceof ConfigFactoryInterface);
    assert($entity_repository instanceof EntityRepositoryInterface);
    assert($conditions_helper_form_builder instanceof ConditionsFormBuilder);
    assert($conditions_evaluator instanceof ConditionsEvaluator);

    return new static(
      $plugin_manager,
      $context_repository,
      $module_handler,
      $context_handler,
      $config_factory,
      $entity_repository,
      $conditions_helper_form_builder,
      $conditions_evaluator,
    );
  }

  /**
   * Layout builder styles form alter.
   *
   * @param array $form
   *   The form being altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $form_id
   *   The form ID.
   */
  public function layoutBuilderStylesFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // Grab the style entity from the form.
    $form_object = $form_state->getFormObject();
    $style_entity = NULL;

    // First try using the form's entity property directly.
    if (isset($form['#entity']) && $form['#entity'] instanceof LayoutBuilderStyleInterface) {
      $style_entity = $form['#entity'];
    }
    // Otherwise try to get the entity from the form object if it's an
    // EntityForm.
    elseif ($form_object instanceof EntityFormInterface) {
      $entity = $form_object->getEntity();
      if ($entity instanceof LayoutBuilderStyleInterface) {
        $style_entity = $entity;
      }
    }

    if (!$style_entity instanceof LayoutBuilderStyleInterface) {
      return;
    }

    $form['third_party_settings'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['third_party_settings']['lb_styles_conditions'] = [
      '#type' => 'details',
      '#title' => $this->t('Condition restrictions'),
      '#description' => $this->t('Optionally limit this style to apply only when the following conditions are met.'),
      '#tree' => TRUE,
    ];

    $available_conditions = $this->conditionsHelperFormBuilder->getAvailableConditions(SettingsForm::SETTINGS);
    $available_contexts = $this->contextRepository->getAvailableContexts();
    $stored_values = $style_entity->getThirdPartySettings('lb_styles_conditions') ?? [];
    $this->conditionsHelperFormBuilder->buildConditionsForm($form['third_party_settings']['lb_styles_conditions'], $form_state, $available_conditions, $available_contexts, $stored_values);

    // Add our submission handler to be first.
    array_unshift(
      $form['actions']['submit']['#submit'],
      [$this, 'layoutBuilderStylesFormSubmit']
    );
  }

  /**
   * Submit callback for the form being submitted.
   *
   * @param array $form
   *   The form being altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function layoutBuilderStylesFormSubmit(array &$form, FormStateInterface $form_state): void {
    $this->conditionsHelperFormBuilder->submitConditionsForm(
      $form,
      $form_state,
      ['third_party_settings', 'lb_styles_conditions']
    );
  }

  /**
   * Layout builder block form alter.
   *
   * @param array $form
   *   The form being altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $form_id
   *   The form ID.
   */
  public function alterLayoutBuilderBlockForm(array &$form, FormStateInterface $form_state, $form_id): void {
    /** @var \Drupal\layout_builder\Form\ConfigureBlockFormBase $form_object */
    $form_object = $form_state->getFormObject();

    $block_plugin_id = $form_object
      ->getCurrentComponent()
      ->getPluginId();

    $bundle = FALSE;

    // If this is a reusable block, retrieve the block bundle.
    if (strpos($block_plugin_id, 'block_content:') === 0) {
      $uuid = str_replace('block_content:', '', $block_plugin_id);
      $bundle = $this->entityRepository->loadEntityByUuid('block_content', $uuid)
        ->bundle();
    }

    $styles = _layout_builder_styles_retrieve_by_type(LayoutBuilderStyleInterface::TYPE_COMPONENT);

    array_walk($styles, function ($style) use ($block_plugin_id, $bundle, &$form) {
      /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style */
      $restrictions = $style->getBlockRestrictions();
      $bundle_allowed = FALSE;
      // If this is a re-usable block, propagate any inline_block allowances
      // by comparing the block bundles.
      if ($bundle && in_array('inline_block:' . $bundle, $restrictions)) {
        $bundle_allowed = TRUE;
      }

      if (empty($restrictions) || in_array($block_plugin_id, $restrictions) || $bundle_allowed) {
        $this->evaluateConditions($style, $form);
      }
    });
  }

  /**
   * Layout builder section form alter.
   *
   * @param array $form
   *   The form being altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $form_id
   *   The form ID.
   */
  public function alterLayoutBuilderSectionForm(array &$form, FormStateInterface $form_state, $form_id): void {
    /** @var \Drupal\layout_builder_styles\Form\ConfigureSectionForm $form_object */
    $form_object = $form_state->getFormObject();
    $layout_id = $form_object->getLayout()->getPluginId();
    $all_styles = _layout_builder_styles_retrieve_by_type(LayoutBuilderStyleInterface::TYPE_SECTION);

    array_walk($all_styles, function ($style) use ($layout_id, &$form) {
      /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style */
      $restrictions = $style->getLayoutRestrictions();
      if (empty($restrictions) || in_array($layout_id, $restrictions)) {
        $this->evaluateConditions($style, $form);
      }
    });
  }

  /**
   * Evaluate the conditions for a given style.
   *
   * @param \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style
   *   The style to evaluate.
   * @param array $form
   *   The form being altered.
   */
  protected function evaluateConditions(LayoutBuilderStyleInterface $style, array &$form): void {
    $applicable_conditions = $style->getThirdPartySettings('lb_styles_conditions');
    $result = $this->conditionsEvaluator->evaluateConditions($applicable_conditions);
    if ($result === FALSE) {
      // If one or more failed, remove the style from the form.
      $group_id = $style->getGroup();
      $form_element_name = 'layout_builder_style_' . $group_id;
      if (isset($form[$form_element_name])) {
        unset($form[$form_element_name]);
      }
    }
  }

}
