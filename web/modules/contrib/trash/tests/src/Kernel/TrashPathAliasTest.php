<?php

declare(strict_types=1);

namespace Drupal\Tests\trash\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests path_alias integration in DefaultTrashHandler.
 *
 * @group trash
 */
class TrashPathAliasTest extends TrashKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
  ];

  /**
   * The node storage.
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The path alias storage.
   */
  protected EntityStorageInterface $pathAliasStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    // Enable path_alias for trash.
    $this->enableEntityTypesForTrash(['path_alias']);

    $this->nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $this->pathAliasStorage = \Drupal::entityTypeManager()->getStorage('path_alias');
  }

  /**
   * Creates a node with a path alias.
   */
  protected function createNodeWithPathAlias(array $node_values = [], ?string $alias = NULL): array {
    $node_values += [
      'type' => 'article',
      'title' => 'Test Node',
      'status' => 1,
    ];
    $node = $this->createNode($node_values);

    $alias = $alias ?: '/test-node-' . $node->id();
    $path_alias = PathAlias::create([
      'path' => '/node/' . $node->id(),
      'alias' => $alias,
      'langcode' => $node->language()->getId(),
    ]);
    $path_alias->save();

    return [$node, $path_alias];
  }

  /**
   * Tests that deleting and restoring a node also affects its path alias.
   */
  public function testNodeDeleteAndRestoreWithPathAlias(): void {
    [$node, $path_alias] = $this->createNodeWithPathAlias();
    $node_id = $node->id();
    $alias_id = $path_alias->id();
    $alias_path = $path_alias->getAlias();

    // Verify both entities exist initially.
    $this->assertNotNull(Node::load($node_id));
    $this->assertNotNull(PathAlias::load($alias_id));

    // Delete the node.
    $node->delete();

    // Verify node is deleted (not accessible in active context).
    $this->assertNull(Node::load($node_id));
    $this->assertNull(PathAlias::load($alias_id));

    // Verify both are accessible in ignore context and have same timestamp.
    $deleted_node = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($node_id) {
      return Node::load($node_id);
    });
    $deleted_alias = $this->getTrashManager()->executeInTrashContext('ignore', function () use ($alias_id) {
      return PathAlias::load($alias_id);
    });

    $this->assertNotNull($deleted_node);
    $this->assertNotNull($deleted_alias);
    $this->assertEquals($deleted_node->get('deleted')->value, $deleted_alias->get('deleted')->value);

    // Restore the node using the original entity object.
    $this->nodeStorage->restoreFromTrash([$node]);

    // Verify both node and alias are restored.
    $restored_node = Node::load($node_id);
    $restored_alias = PathAlias::load($alias_id);

    $this->assertNotNull($restored_node);
    $this->assertNotNull($restored_alias);
    $this->assertNull($restored_node->get('deleted')->value);
    $this->assertNull($restored_alias->get('deleted')->value);
    $this->assertEquals($alias_path, $restored_alias->getAlias());
  }

  /**
   * Tests handling of multiple path aliases for a single node.
   */
  public function testMultiplePathAliasesHandled(): void {
    $node = $this->createNode(['type' => 'article', 'title' => 'Multi-Alias Node']);
    $node_path = '/node/' . $node->id();

    // Create multiple aliases for the same node.
    $alias1 = PathAlias::create([
      'path' => $node_path,
      'alias' => '/first-alias',
      'langcode' => 'en',
    ]);
    $alias1->save();

    $alias2 = PathAlias::create([
      'path' => $node_path,
      'alias' => '/second-alias',
      'langcode' => 'en',
    ]);
    $alias2->save();

    // Delete the node.
    $node->delete();

    // Verify all are deleted.
    $this->assertNull(Node::load($node->id()));
    $this->assertNull(PathAlias::load($alias1->id()));
    $this->assertNull(PathAlias::load($alias2->id()));

    // Restore the node and verify that both aliases have been restored as well.
    $this->nodeStorage->restoreFromTrash([$node]);
    $this->assertNotNull(Node::load($node->id()));
    $this->assertNotNull(PathAlias::load($alias1->id()));
    $this->assertNotNull(PathAlias::load($alias2->id()));
  }

  /**
   * Tests that path alias integration is skipped when not enabled.
   */
  public function testPathAliasDisabledSkipsIntegration(): void {
    // Disable path_alias for trash.
    $this->disableEntityTypesForTrash(['path_alias']);

    [$node, $path_alias] = $this->createNodeWithPathAlias();

    // Delete the node.
    $node->delete();

    // Verify that the node is deleted but the alias remains active.
    $this->assertNull(Node::load($node->id()));
    $this->assertNotNull(PathAlias::load($path_alias->id()));
  }

  /**
   * Tests handling of nodes without path aliases.
   */
  public function testNoPathAliasesNoErrors(): void {
    $node = $this->createNode(['type' => 'article', 'title' => 'No Alias Node']);

    // Verify no errors when deleting node without aliases.
    $node->delete();
    $this->assertNull(Node::load($node->id()));

    // Verify no errors when restoring node without aliases.
    $this->nodeStorage->restoreFromTrash([$node]);
    $this->assertNotNull(Node::load($node->id()));
  }

  /**
   * Tests that only aliases matching the entity's path are restored.
   */
  public function testPathFilteringOnRestore(): void {
    $node1 = $this->createNode(['type' => 'article', 'title' => 'Node 1']);
    $node2 = $this->createNode(['type' => 'article', 'title' => 'Node 2']);

    // Create aliases for both nodes.
    $alias1 = PathAlias::create([
      'path' => '/node/' . $node1->id(),
      'alias' => '/filtered-alias-1',
      'langcode' => 'en',
    ]);
    $alias1->save();

    $alias2 = PathAlias::create([
      'path' => '/node/' . $node2->id(),
      'alias' => '/filtered-alias-2',
      'langcode' => 'en',
    ]);
    $alias2->save();

    // Delete both nodes to get the same deletion timestamp.
    $node1->delete();
    $node2->delete();

    // Verify both nodes and aliases are deleted.
    $this->assertNull(Node::load($node1->id()));
    $this->assertNull(Node::load($node2->id()));
    $this->assertNull(PathAlias::load($alias1->id()));
    $this->assertNull(PathAlias::load($alias2->id()));

    // Restore only node1.
    $this->nodeStorage->restoreFromTrash([$node1]);

    // Verify that only node1 and its alias are restored.
    $this->assertNotNull(Node::load($node1->id()));
    $this->assertNotNull(PathAlias::load($alias1->id()));

    // Node2 and its alias should still be deleted.
    $this->assertNull(Node::load($node2->id()));
    $this->assertNull(PathAlias::load($alias2->id()));
  }

}
