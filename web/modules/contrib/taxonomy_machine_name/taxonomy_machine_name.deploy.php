<?php

/**
 * @file
 * Deploy functions for the Taxonomy Machine Name module.
 */

use Drupal\taxonomy\Entity\Term;

/**
 * Add machine_name for previously created terms.
 */
function taxonomy_machine_name_deploy_update_existing_terms(&$sandbox) {
  if (empty($sandbox['tids'])) {
    // Size of the batch to process.
    $batch_size = 10;

    $tids = \Drupal::entityQuery('taxonomy_term')->notExists('machine_name')->accessCheck(FALSE)->execute();

    $sandbox['total'] = count($tids);
    $sandbox['tids'] = array_chunk($tids, $batch_size);
    $sandbox['succeeded'] = $sandbox['errored'] = $sandbox['processed_chunks'] = 0;
  }

  $entity_type_manager = \Drupal::entityTypeManager();
  $term_storage = $entity_type_manager->getStorage('taxonomy_term');

  // Nothing to do?
  if (!$sandbox['total']) {
    // $sandbox['message'] = t('No terms updated');
    $term_storage->resetCache();
    $sandbox['#finished'] = 1;
    return;
  }

  // Process all terms in this chunk.
  $current_chunk = $sandbox['tids'][$sandbox['processed_chunks']];
  $terms = Term::loadMultiple($current_chunk);

  foreach ($terms as $term) {
    $success = taxonomy_machine_name_update_term($term);
    $success ? $sandbox['succeeded']++ : $sandbox['errored']++;
  }

  // Increment the number of processed chunks to determine when we've finished.
  $sandbox['processed_chunks']++;

  // When we have processed all of the chunks $sandbox['#finished'] will be 1.
  // Then the batch / update runner will consider the job finished.
  $sandbox['#finished'] = $sandbox['processed_chunks'] / count($sandbox['tids']);

  if ($sandbox['#finished'] >= 1) {
    return t(
      '@succeeded terms were updated correctly. @errored terms failed.',
      [
        '@succeeded' => $sandbox['succeeded'],
        '@errored' => $sandbox['errored'],
      ]
    );
  }
}
