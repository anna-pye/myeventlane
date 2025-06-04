<?php

namespace Drupal\search_api_taxonomy_machine_name\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds all ancestors' machine names to a hierarchical field.
 *
 * @SearchApiProcessor(
 *   id = "taxonomy_machine_name_hierarchy",
 *   label = @Translation("Index machine name hierarchy"),
 *   description = @Translation("Allows the indexing of taxonomy machine names along with all their ancestors."),
 *   stages = {
 *     "preprocess_index" = -45
 *   }
 * )
 */
class AddHierarchy extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * Index Hierarchy Fields.
   *
   * @var array
   */
  protected array $indexHierarchyFields = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Logger Channel Factory Interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->entityTypeManager = $container->get('entity_type.manager');
    $processor->logger = $container->get('logger.factory');

    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    $processor = new static(['#index' => $index], 'taxonomy_machine_name_hierarchy', []);

    return (bool) $processor->getHierarchyFields();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'fields' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['#description'] = $this->t('Select the fields to which hierarchical data should be added.');

    foreach ($this->getHierarchyFields() as $field_id => $options) {
      $enabled = !empty($this->configuration['fields'][$field_id]['status']);
      $form['fields'][$field_id]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->index->getField($field_id)->getLabel(),
        '#default_value' => $enabled,
      ];
    }

    return $form;
  }

  /**
   * Find all taxonomy term machine name fields.
   *
   * @return string[][]
   *   Returns an array of indexHierarchyFields.
   */
  protected function getHierarchyFields(): array {
    if (!isset($this->indexHierarchyFields[$this->index->id()])) {
      $field_options = [];

      foreach ($this->index->getFields() as $field_id => $field) {
        $dependencies = $field->getDependencies();
        if (!isset($dependencies['module']) || !in_array('taxonomy_machine_name', $dependencies['module'])) {
          continue;
        }

        $field_options[$field_id] = [
          'taxonomy_term-machine_name-parent' => 'Taxonomy Term » Machine Name » Parent',
        ];
      }

      $this->indexHierarchyFields[$this->index->id()] = $field_options;
    }

    return $this->indexHierarchyFields[$this->index->id()];
  }

  /**
   * Add parent taxonomy term machine names to the field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $term
   *   The entity interface.
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field interface.
   */
  protected function addHierarchyValues(EntityInterface $term, FieldInterface $field): void {
    try {
      foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($term->id()) as $taxonomy_term) {
        $machine_name = $taxonomy_term->get('machine_name')->value;
        if (in_array($machine_name, $field->getValues(), TRUE)) {
          continue;
        }

        $field->addValue($machine_name);
      }
    }
    catch (\Exception $exception) {
      $this->logger->get('search_api_taxonomy_machine_name')->error('An error occurred: !message', [
        '!message' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      foreach ($this->configuration['fields'] as $field_id => $property_specifier) {
        if (!$property_specifier['status']) {
          continue;
        }

        $field = $item->getField($field_id);
        if (!$field) {
          continue;
        }

        [$field_name] = explode(':', $field->getPropertyPath());

        try {
          /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
          $entity = $item->getOriginalObject()->getValue();
          if (!$entity->hasField($field_name)) {
            continue;
          }

          foreach ($entity->get($field_name) as $value) {
            $term = $value->get('entity')->getValue();
            if ($term === NULL) {
              continue;
            }

            $this->addHierarchyValues($term, $field);
          }
        }
        catch (\Exception $exception) {
          $this->logger->get('search_api_taxonomy_machine_name')->error('An error occurred: !message', [
            '!message' => $exception->getMessage(),
          ]);
        }
      }
    }
  }

  /**
   * When hierarchy is enabled, make the configured fields multi-value fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index interface.
   * @param array $fields
   *   The field array.
   * @param string $language_id
   *   The language_id string.
   */
  public function alterFieldMapping(IndexInterface $index, array &$fields, string $language_id): void {
    $configuration = $this->getConfiguration();
    if (!isset($configuration['fields'])) {
      return;
    }

    foreach ($configuration['fields'] as $field_name => $property) {
      if (!isset($property['status']) || !$property['status']) {
        continue;
      }

      $fields[$field_name] = 'sm_' . $field_name;
    }
  }

}
