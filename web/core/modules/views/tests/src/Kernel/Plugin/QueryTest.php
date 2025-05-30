<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\views\Views;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views_test_data\Plugin\views\query\QueryTest as QueryTestPlugin;

/**
 * Tests query plugins.
 *
 * @group views
 */
class QueryTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  protected function viewsData() {
    $data = parent::viewsData();
    $data['views_test_data']['table']['base']['query_id'] = 'query_test';

    return $data;
  }

  /**
   * Tests query plugins.
   */
  public function testQuery(): void {
    $this->_testInitQuery();
    $this->_testQueryExecute();
    $this->queryMethodsTests();
  }

  /**
   * Tests the ViewExecutable::initQuery method.
   */
  public function _testInitQuery(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->initQuery();
    $this->assertInstanceOf(QueryTestPlugin::class, $view->query);
  }

  public function _testQueryExecute(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->initQuery();
    $view->query->setAllItems($this->dataSet());

    $this->executeView($view);
    $this->assertNotEmpty($view->result, 'Make sure the view result got filled');
  }

  /**
   * Tests methods provided by the QueryPluginBase.
   *
   * @see \Drupal\views\Plugin\views\query\QueryPluginBase
   */
  protected function queryMethodsTests(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->initQuery();
    $this->assertNull($view->query->getLimit(), 'Default to an empty limit.');
    $rand_number = rand(5, 10);
    $view->query->setLimit($rand_number);
    $this->assertEquals($rand_number, $view->query->getLimit(), 'set_limit adapts the amount of items.');
  }

}
