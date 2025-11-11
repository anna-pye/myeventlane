<?php

namespace Drupal\Tests\trash\Kernel;

use Drupal\trash_test\Entity\TrashTestEntity;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;

/**
 * Tests views integration for Trash.
 *
 * @group trash
 */
class ViewQueryTest extends TrashKernelTestBase {

  use ViewResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views'];

  /**
   * Tests that deleted entities are excluded from views results.
   */
  public function testQueryWithoutDeletedAccess(): void {
    $entities = [];

    for ($i = 0; $i < 5; $i++) {
      $entity = TrashTestEntity::create();
      $entity->save();
      $entities[] = $entity;
    }

    // Test whether they appear in the view.
    $view = Views::getView('trash_test_view');
    $view->execute('page_1');
    $this->assertIdenticalResultset($view, [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();

    // Delete the first three of them. They should no longer appear in the view.
    for ($i = 0; $i < 3; $i++) {
      $entities[$i]->delete();
    }

    $view = Views::getView('trash_test_view');
    $view->execute('page_1');
    $this->assertIdenticalResultset($view, [
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();
  }

  /**
   * Tests that deleted entities are excluded from views results.
   *
   * @todo Change this test to also add a filter on the 'deleted' field.
   */
  public function testQueryWithDeletedAccess(): void {
    $entities = [];

    for ($i = 0; $i < 5; $i++) {
      $entity = TrashTestEntity::create();
      $entity->save();
      $entities[] = $entity;
    }

    // Test whether they appear in the view.
    $view = Views::getView('trash_test_view');
    $view->execute('page_1');
    $this->assertIdenticalResultset($view, [
      ['id' => 1],
      ['id' => 2],
      ['id' => 3],
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();

    // Delete the first three of them. They should all be individual loadable
    // but no longer accessible via the view.
    for ($i = 0; $i < 3; $i++) {
      $entities[$i]->delete();
    }

    $view = Views::getView('trash_test_view');
    $view->execute('page_2');
    $this->assertIdenticalResultset($view, [
      ['id' => 4],
      ['id' => 5],
    ], ['id' => 'id']);
    $view->destroy();
  }

  /**
   * Tests that entities referencing a deleted entity are excluded from views.
   */
  public function testRelationshipToDeletedEntity(): void {
    // Create three entities: A, B, C.
    $entityA = TrashTestEntity::create();
    $entityA->save();

    $entityB = TrashTestEntity::create(['reference' => $entityA->id()]);
    $entityB->save();

    $entityC = TrashTestEntity::create(['reference' => $entityA->id()]);
    $entityC->save();

    // The view should return the list of entities that reference the entity
    // passed in. Confirm that when we pass in entityA, we get back
    // entityB and entityC.
    $view = Views::getView('trash_test_view_relationship');
    $view->setArguments([$entityA->id()]);
    $view->execute('default');
    $this->assertIdenticalResultset($view, [
      ['trash_test_trash_test_id' => 2],
      ['trash_test_trash_test_id' => 3],
    ], ['trash_test_trash_test_id' => 'trash_test_trash_test_id']);
    $view->destroy();
    // Re-enable trash. Executing the view disabled it, and the post render hook
    // that re-enables it automatically isn't executed due to the way we're
    // executing the view.
    \Drupal::service('trash.manager')->setTrashContext('active');

    // Now move EntityB to the trash.
    $entityB->delete();

    // The same view should no longer include entityB in its result set since
    // it's in the trash.
    $view = Views::getView('trash_test_view_relationship');
    $view->setArguments([$entityA->id()]);
    $view->execute('default');
    $this->assertIdenticalResultset($view, [
      ['trash_test_trash_test_id' => 3],
    ], ['trash_test_trash_test_id' => 'trash_test_trash_test_id']);
    $view->destroy();
    \Drupal::service('trash.manager')->setTrashContext('active');
  }

}
