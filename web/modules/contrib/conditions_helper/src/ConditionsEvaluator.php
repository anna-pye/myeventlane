<?php

declare(strict_types=1);

namespace Drupal\conditions_helper;

use Drupal\Core\Condition\ConditionAccessResolverTrait;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;

/**
 * Service to evaluate a collection of configured condition plugins.
 *
 * This service handles the instantiation of condition plugins, the application
 * of necessary contexts, and the logic to determine if a set of conditions
 * (all or any) are met.
 */
class ConditionsEvaluator {

  use ConditionAccessResolverTrait;

  /**
   * Constructs a new ConditionsEvaluator object.
   *
   * @param \Drupal\Core\Condition\ConditionManager $conditionManager
   *   The condition plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $contextRepository
   *   The context repository service.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $contextHandler
   *   The context handler service.
   */
  public function __construct(
    protected ConditionManager $conditionManager,
    protected ContextRepositoryInterface $contextRepository,
    protected ContextHandlerInterface $contextHandler,
  ) {
  }

  /**
   * Evaluates a set of configured conditions.
   *
   * @param array $configured_conditions
   *   An array of condition plugin configurations, where keys are plugin IDs
   *   and values are their configurations (including context mappings and
   *   negation settings).
   * @param bool $all_must_pass
   *   Determines the evaluation logic: TRUE if all conditions must pass (AND),
   *   FALSE if any condition passing is sufficient (OR). Defaults to TRUE.
   * @param array $additional_contexts
   *   An optional array of \Drupal\Core\Plugin\Context\ContextInterface objects
   *   to supplement or override contexts from the ContextRepository. These are
   *   typically contexts specific to the current operation that might not be
   *   globally available.
   *
   * @return bool
   *   TRUE if the conditions are met according to the specified logic (AND/OR),
   *   FALSE otherwise.
   */
  public function evaluateConditions(array $configured_conditions, bool $all_must_pass = TRUE, array $additional_contexts = []): bool {
    // If there are no conditions to evaluate, return TRUE.
    if (empty($configured_conditions)) {
      return TRUE;
    }

    // If there were conditions.
    if (!empty($configured_conditions)) {
      // Create a condition collection.
      $collection = new ConditionPluginCollection($this->conditionManager, $configured_conditions);
      $collection_array = iterator_to_array($collection);

      $conditions = [];

      // Loop through each condition, verify the contexts are available.
      array_walk($collection_array, function ($condition, $condition_id) use (&$conditions) {
        if ($condition instanceof ContextAwarePluginInterface) {
          // Try to attach any necessary contexts to the condition.
          try {
            $contexts = $this->contextRepository->getRuntimeContexts(array_values($condition->getContextMapping()));
            if (!empty($additional_contexts)) {
              $contexts = array_merge($contexts, array_values($additional_contexts));
            }
            $this->contextHandler->applyContextMapping($condition, $contexts);
          }
          catch (\Exception $e) {
            // Do nothing.
          }
        }
        $conditions[$condition_id] = $condition;
      });

      switch ($all_must_pass) {
        case TRUE:
          return $this->resolveConditions($conditions, 'and');
        case FALSE:
          return $this->resolveConditions($conditions, 'or');
      }
    }
    return TRUE;
  }

}
