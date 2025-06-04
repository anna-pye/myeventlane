<?php

namespace Drupal\taxonomy_machine_name\Plugin\migrate\source\d7;

use Drupal\migrate\Row;
use Drupal\taxonomy\Plugin\migrate\source\d7\Term;

/**
 * Taxonomy machine name source from database.
 *
 * @MigrateSource(
 *   id = "d7_taxonomy_machine_name_term",
 *   source_module = "taxonomy_machine_name"
 * )
 */
class TaxonomyMachineNameTerm extends Term {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    $fields =& $query->getFields();
    if (isset($fields['machine_name'])) {
      unset($fields['machine_name']);
    }

    $query->addField('tv', 'machine_name', 'vocabulary_machine_name');
    $query->addField('td', 'machine_name', 'term_machine_name');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Set the 'machine name' property to the vocabulary machine name which is
    // what the parent expects.
    $row->setSourceProperty('machine_name', $row->getSourceProperty('vocabulary_machine_name'));
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'vocabulary_machine_name' => $this->t('Vocabulary machine name'),
      'term_machine_name' => $this->t('Term machine name'),
    ];
    return parent::fields() + $fields;
  }

}
