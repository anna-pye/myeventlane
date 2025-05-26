<?php

declare(strict_types = 1);

namespace Drupal\Tests\block_visibility_groups\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the module with Javascript interactions.
 *
 * @group block_visibility_groups
 */
class BlockVisibilityGroupsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_visibility_groups',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and login with user who can administer blocks.
    $this->drupalLogin($this->drupalCreateUser([
      'administer blocks',
      'administer block visibility groups',
    ]));

    \Drupal::service('theme_installer')->install(['olivero']);
  }

  /**
   * Tests that the condition_group plugin can be properly serialized.
   *
   * The condition plugins are serialized when the form state is cached. To
   * trigger that, we use an AJAX element in the block add route.
   */
  public function testFormSerialization(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/structure/block/add/system_powered_by_block');
    $assert_session->optionExists('Region', 'Left sidebar');
    $assert_session->selectExists('Theme')->selectOption('Olivero');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->optionNotExists('Region', 'Left sidebar');
    $assert_session->selectExists('Theme')->selectOption('Stark');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->optionExists('Region', 'Left sidebar');
  }

}
