<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Controller\SalesforceMappedObjectController.
 */

namespace Drupal\salesforce_mapping\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for devel module routes.
 */
class SalesforceMappedObjectController extends ControllerBase {

  /**
   * Prints the loaded structure of the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function view(RouteMatchInterface $route_match) {
    $output = array();

    $parameter_name = $route_match->getRouteObject()->getOption('_salesforce_entity_type_id');
    $entity = $route_match->getParameter($parameter_name);

    if ($entity && $entity instanceof EntityInterface) {
      $output = array('#markup' => kdevel_print_object($entity));
    }

    return $output;
  }

  public function add(RouteMatchInterface $route_match) {
    return $this->view($route_match);
  }

  public function edit(RouteMatchInterface $route_match) {
    return $this->view($route_match);
  }

  public function delete(RouteMatchInterface $route_match) {
    return $this->view($route_match);
  }

}
