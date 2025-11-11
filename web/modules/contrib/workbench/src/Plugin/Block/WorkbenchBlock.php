<?php

namespace Drupal\workbench\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Register a block that other modules may hook into.
 *
 * @Block(
 *   id = "workbench_block",
 *   admin_label = @Translation("Workbench information")
 * )
 */
class WorkbenchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a WorkbenchBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $items = $this->moduleHandler->invokeAll('workbench_block');
    if (empty($items)) {
      return [];
    }
    return [
      '#markup' => '<div class="workbench-info-block">' . implode('<br />', $items) . '</div>',
      '#attached' => [
        'library' => ['workbench/workbench.block'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access workbench');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.path'];
  }

}
