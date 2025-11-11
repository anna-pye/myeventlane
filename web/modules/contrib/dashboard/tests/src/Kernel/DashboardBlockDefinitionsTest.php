<?php

declare(strict_types=1);

namespace Drupal\Tests\Dashboard\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for dashboard block definition for integrations.
 */
#[Group('dashboard')]
class DashboardBlockDefinitionsTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'dashboard',
  ];

  /**
   * Tests which blocks are allowed in navigation.
   */
  #[DataProvider('blockProvider')]
  public function testBlockDefinitions($block_id, $allowed_in_navigation, $allowed_in_block_ui): void {
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = $this->container->get(BlockManagerInterface::class);
    $block_definition = $block_manager->getDefinition($block_id);
    $this->assertSame($allowed_in_block_ui, $block_definition['_block_ui_hidden']);

    if (!$allowed_in_navigation) {
      $this->assertArrayNotHasKey('allow_in_navigation', $block_definition);
    }
    else {
      $this->assertArrayHasKey('allow_in_navigation', $block_definition);
      $this->assertSame($allowed_in_navigation, $block_definition['allow_in_navigation']);
    }
  }

  /**
   * Provider of blocks defined from the dashboard module.
   */
  public static function blockProvider(): \Generator {
    yield 'navigation block' => ['navigation_dashboard', TRUE, TRUE];
    yield 'text block' => ['dashboard_text_block', FALSE, TRUE];
    yield 'site_status block' => ['dashboard_site_status', FALSE, TRUE];
  }

}
