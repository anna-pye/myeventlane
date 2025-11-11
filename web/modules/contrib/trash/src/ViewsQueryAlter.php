<?php

declare(strict_types=1);

namespace Drupal\trash;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for altering views queries.
 *
 * @internal
 */
class ViewsQueryAlter implements ContainerInjectionInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected TrashManagerInterface $trashManager,
    protected ViewsData $viewsData,
    protected ViewsHandlerManager $joinHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('trash.manager'),
      $container->get('views.views_data'),
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * Implements a hook bridge for hook_views_query_alter().
   *
   * @see hook_views_query_alter()
   */
  public function alterQuery(QueryPluginBase $query): void {
    // Don't alter any non-sql views queries.
    if (!$query instanceof Sql || !$this->trashManager->shouldAlterQueries()) {
      return;
    }

    // Bail out early if the query has already been altered.
    if (in_array('trash_altered', $query->tags, TRUE)) {
      return;
    }

    // Add a deleted condition for every entity table in the query that has
    // trash enabled.
    foreach ($query->getEntityTableInfo() as $info) {
      // Skip entity types without trash integration.
      if (!$this->trashManager->isEntityTypeEnabled($info['entity_type'])) {
        continue;
      }

      $deleted_table_alias = $info['alias'];

      // If this is a revision table, we need to join and use the data table
      // which holds the relevant "deleted" state.
      if ($info['revision']) {
        $entity_type = $this->entityTypeManager->getDefinition($info['entity_type']);
        $id_key = $entity_type->getKey('id');
        $data_table = $entity_type->getDataTable() ?? $entity_type->getBaseTable();

        if (empty($data_table)) {
          throw new \UnexpectedValueException("Missing data table for the {$info['base']} revision table.");
        }

        $definition = [
          'type' => 'LEFT',
          'table' => $data_table,
          'field' => $id_key,
          'left_table' => $info['base'],
          'left_field' => $id_key,
        ];
        $join = $this->joinHandler->createInstance('standard', $definition);

        $deleted_table_alias = $query->addTable($data_table, $info['alias'], $join);
      }

      $this->alterQueryForEntityType($query, $info['entity_type'], $deleted_table_alias);
    }
  }

  /**
   * Alters the entity type tables for a Views query.
   *
   * Adds a data_table.deleted IS NULL condition unless there is a specific
   * filter for the deleted field already.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $deleted_table_alias
   *   The alias of the data table which holds the 'deleted' column.
   */
  protected function alterQueryForEntityType(Sql $query, string $entity_type_id, string $deleted_table_alias): void {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    assert($storage instanceof SqlEntityStorageInterface);
    $table_mapping = $storage->getTableMapping();
    assert($table_mapping instanceof DefaultTableMapping);
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);

    // Try to find out whether any filter (normal or conditional filter) filters
    // by the delete column. In case it does opt out of adding a specific
    // delete column.
    $deleted_table_column = $table_mapping->getFieldColumnName($field_storage_definitions['deleted'], 'value');

    $has_delete_condition = $this->hasDeleteCondition($query, $deleted_table_alias, $deleted_table_column);

    // If we couldn't find any condition that filters out explicitly on deleted,
    // ensure that we just return not deleted entities.
    if (!$has_delete_condition) {
      $query->addWhere(0, "{$deleted_table_alias}.{$deleted_table_column}", NULL, 'IS NULL');
      $query->addTag('trash_altered');
    }
    // Otherwise ignore trash for the duration of this view, so it can load and
    // display deleted entities.
    else {
      $this->trashManager->setTrashContext('ignore');
      $query->addTag('ignore_trash');
    }
  }

  /**
   * Check if any filter of the query contains a delete condition.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param string $deleted_table_alias
   *   The alias of the table to check.
   * @param string $deleted_table_column
   *   Name of delete column.
   *
   * @return bool
   *   <code>TRUE</code> if the query has a delete condition, <code>FALSE</code>
   *   otherwise.
   */
  protected function hasDeleteCondition(Sql $query, string $deleted_table_alias, string $deleted_table_column): bool {
    foreach ($query->where as $group) {
      foreach ($group['conditions'] as $condition) {
        if (!isset($condition['field']) || !is_string($condition['field'])) {
          continue;
        }
        // Note: We use strpos because views for some reason has a field
        // looking like "trash_test.Deleted > 0".
        if (strpos($condition['field'], "{$deleted_table_alias}.{$deleted_table_column}") !== FALSE) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Implements a hook bridge for hook_views_post_render().
   *
   * @see hook_views_post_render()
   */
  public function postRender(ViewExecutable $view): void {
    $query = $view->getQuery();
    if ($query instanceof Sql && in_array('ignore_trash', $query->tags, TRUE)) {
      // Enable trash again after the view has been built.
      $this->trashManager->setTrashContext('active');
    }
  }

}
