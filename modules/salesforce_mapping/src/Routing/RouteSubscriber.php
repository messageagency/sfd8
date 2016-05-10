<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Routing\RouteSubscriber.
 */

namespace Drupal\salesforce_mapping\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $mappings = \Drupal::entityTypeManager()->getStorage('salesforce_mapping')->loadMultiple();
    if (empty($mappings)) {
      return array();
    }
    foreach ($mappings as $mapping) {
      $route_name = 'entity.' . $mapping->drupal_entity_type . '.canonical';
      if ($route = $collection->get($route_name)) {
        $sf_route_name = 'entity.' . $mapping->drupal_entity_type . '.salesforce';
        $sf_route_path = $route->getPath() . '/salesforce';
        $requirements = $route->getRequirements();
        $requirements['_entity_access'] = 'salesforce_mapped_object.view';
        $route = new Route($sf_route_path, $route->getDefaults(), $requirements, $route->getOptions(), $route->getHost(), $route->getSchemes(), $route->getMethods(), $route->getCondition());
        $collection->add($sf_route_name, $route);
      }
    }
  }
}