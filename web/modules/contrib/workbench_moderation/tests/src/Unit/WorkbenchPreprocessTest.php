<?php

namespace Drupal\Tests\workbench_moderation\Unit;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\node\Entity\Node;
use Drupal\Tests\UnitTestCase;
use Drupal\workbench_moderation\WorkbenchPreprocess;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests WorkbenchPreprocess.
 *
 * @coversDefaultClass \Drupal\workbench_moderation\WorkbenchPreprocess
 * @group workbench_moderation
 */
class WorkbenchPreprocessTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::isLatestVersionPage
   * @dataProvider routeNodeProvider
   */
  public function testIsLatestVersionPage($route_name, $route_nid, $check_nid, $result, $message) {
    $workbench_preprocess = new WorkbenchPreprocess($this->setupCurrentRouteMatch($route_name, $route_nid));
    $node = $this->setupNode($check_nid);
    $this->assertEquals($result, $workbench_preprocess->isLatestVersionPage($node), $message);
  }

  /**
   * Route node provider.
   */
  public static function routeNodeProvider() {
    return [
      ['entity.node.canonical', 1, 1, FALSE,
        'Not on the latest version tab route.',
      ],
      ['entity.node.latest_version', 1, 1, TRUE,
        'On the latest version tab route, with the route node.',
      ],
      ['entity.node.latest_version', 1, 2, FALSE,
        'On the latest version tab route, with a different node.',
      ],
    ];
  }

  /**
   * Mock the current route matching object.
   *
   * @param string $routeName
   *   Route.
   * @param int $nid
   *   Node id.
   *
   * @return \Drupal\Core\Routing\CurrentRouteMatch
   *   Returns current route.
   */
  protected function setupCurrentRouteMatch($routeName, $nid) {
    $route_match = $this->prophesize(CurrentRouteMatch::class);
    $route_match->getRouteName()->willReturn($routeName);
    $route_match->getParameter('node')->willReturn($this->setupNode($nid));

    return $route_match->reveal();
  }

  /**
   * Mock a node object.
   *
   * @param int $nid
   *   Node id.
   *
   * @return \Drupal\node\Entity\Node
   *   Returns node.
   */
  protected function setupNode($nid) {
    $node = $this->prophesize(Node::class);
    $node->id()->willReturn($nid);

    return $node->reveal();
  }

}
