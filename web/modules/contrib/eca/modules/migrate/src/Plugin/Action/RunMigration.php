<?php

namespace Drupal\eca_migrate\Plugin\Action;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs a specified migration.
 */
#[Action(
  id: 'eca_migrate_run_migration',
  label: new TranslatableMarkup('Migrate: Run migration'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Triggers a migration run by ID.'),
  version_introduced: '3.0.0',
)]
class RunMigration extends ConfigurableActionBase {

  /**
   * The migration plugin manager.
   */
  protected MigrationPluginManagerInterface $migrationManager;

  /**
   * The migration instance to run.
   */
  protected MigrationInterface $migration;

  /**
   * The migration result code.
   *
   * @see MigrateExecutable::import()
   */
  private int $migrationResult;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->migrationManager = $container->get('plugin.manager.migration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    $access_result = $this->checkMigrationAccess();
    return ($return_as_object) ? $access_result : $access_result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (!empty($this->configuration['update'])) {
      $this->getMigrationIdMap($this->migration)->prepareUpdate();
    }

    $this->migrationResult = $this->migrationRun($this->migration);
  }

  /**
   * Check if migration instance is successfully created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Whether the migration instance was successfully created.
   */
  protected function checkMigrationAccess(): AccessResultInterface {
    $migration_id = $this->tokenService->replace($this->configuration['migration_id']);
    if (empty($migration_id)) {
      return AccessResult::forbidden('Migration ID is missing.');
    }

    try {
      $migration = $this->migrationManager->createInstance($migration_id);
      if ($migration instanceof MigrationInterface) {
        $this->migration = $migration;
      }
      else {
        return AccessResult::forbidden('Invalid migration instance.');
      }
    }
    catch (PluginException $e) {
      return AccessResult::forbidden('Failed to load migration.');
    }

    return AccessResult::allowed();
  }

  /**
   * Returns the migration id map.
   *
   * This is extracted to make testing easier by mocking the migration id map.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration instance.
   *
   * @return \Drupal\migrate\Plugin\MigrateIdMapInterface
   *   The migration id map.
   */
  protected function getMigrationIdMap(MigrationInterface $migration): MigrateIdMapInterface {
    return $migration->getIdMap();
  }

  /**
   * Run a migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration instance.
   *
   * @return int
   *   The migration result code.
   */
  protected function migrationRun(MigrationInterface $migration): int {
    $migration_id = $migration->id();

    try {
      $executable = new MigrateExecutable($migration, new MigrateMessage());
      $result = $executable->import();
      $this->logger->info($this->t('Migration "@id" run with result code @result.', [
        '@id' => $migration_id,
        '@result' => $result,
      ]));
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('Migration "@id" failed: @message', [
        '@id' => $migration_id,
        '@message' => $e->getMessage(),
      ]));
      $migration->setStatus(MigrationInterface::STATUS_IDLE);
      $result = MigrationInterface::RESULT_FAILED;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'migration_id' => '',
      'update' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['migration_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Migration ID'),
      '#default_value' => $this->configuration['migration_id'],
      '#description' => $this->t('The ID of the migration to run.'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];

    $form['update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update existing records'),
      '#default_value' => $this->configuration['update'],
      '#description' => $this->t('If checked, existing migrated items will be updated.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['migration_id'] = $form_state->getValue('migration_id');
    $this->configuration['update'] = $form_state->getValue('update');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Returns the migration result.
   *
   * @return ?int
   *   The migration result.
   */
  public function getMigrationResult(): ?int {
    return ($this->migrationResult ?? NULL);
  }

}
