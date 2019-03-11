<?php

namespace Drupal\salesforce_mapping_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Returns responses for devel module routes.
 */
class MappedObjectController extends ControllerBase {

  /**
   * Access callback for Mapped Object entity.
   */
  public function access(AccountInterface $account) {
    if (!$account->hasPermission('administer salesforce')) {
      return AccessResult::forbidden();
    }

    // There must be a better way to get the entity from a route match.
    $param = current(\Drupal::routeMatch()->getParameters()->all());
    if (!is_object($param)) {
      return AccessResult::forbidden();
    }
    $implements = class_implements($param);
    if (empty($implements['Drupal\Core\Entity\EntityInterface'])) {
      return AccessResult::forbidden();
    }
    // Only allow access for entities with mappings.
    return $this->entityTypeManager()
      ->getStorage('salesforce_mapping')
      ->loadByEntity($param)
        ? AccessResult::allowed()
        : AccessResult::forbidden();
  }

  /**
   * Helper function to get entity from router path.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The Drupal entity mapped by the given mapped object.
   *
   * @throws \Exception
   *   If an EntityInterface is not found at the given route.
   *
   * @TODO find a more specific exception class
   */
  private function getEntity(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()->getOption('_salesforce_entity_type_id');
    if (empty($parameter_name)) {
      throw new \Exception('Entity type paramater not found.');
    }

    $entity = $route_match->getParameter($parameter_name);
    if (!$entity || !($entity instanceof EntityInterface)) {
      throw new \Exception('Entity is not of type EntityInterface');
    }

    return $entity;
  }

  /**
   * Helper function to fetch existing MappedObject or create a new one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be mapped.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObject[]
   *   The Mapped Objects corresponding to the given entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getMappedObjects(EntityInterface $entity) {
    // @TODO this probably belongs in a service
    return $this
      ->entityTypeManager()
      ->getStorage('salesforce_mapped_object')
      ->loadByEntity($entity);
  }

  /**
   * List mapped objects for the entity along the current route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *
   * @return array
   *   Array of page elements to render.
   *
   * @throws \Exception
   */
  public function listing(RouteMatchInterface $route_match) {
    $entity = $this->getEntity($route_match);
    $salesforce_mapped_objects = $this->getMappedObjects($entity);
    if (empty($salesforce_mapped_objects)) {
      return [
        '#markup' => $this->t('No mapped objects for %label.', ['%label' => $entity->label()]),
      ];
    }

    // Show the entity view for the mapped object.
    $builder = $this->entityTypeManager()->getListBuilder('salesforce_mapped_object');
    return $builder->setEntityIds(array_keys($salesforce_mapped_objects))->render();
  }

}
