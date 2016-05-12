<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Controller\SalesforceMappedObjectController.
 */

namespace Drupal\salesforce_mapping\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\HttpFoundation\Request;
use Drupal\salesforce_mapping\Entity\SalesforceMappedObject;

/**
 * Returns responses for devel module routes.
 */
class SalesforceMappedObjectController extends ControllerBase {

  private function getEntity(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()->getOption('_salesforce_entity_type_id');
    if (empty($parameter_name)) {
      throw new Exception('Entity type paramater not found.');
    }

    $entity = $route_match->getParameter($parameter_name);
    if (!$entity || !($entity instanceof EntityInterface)) {
      throw new Exception('Entity is not of type EntityInterface');
    }

    return $entity;
  }

  private function getMappedObject(EntityInterface $entity) {
    // @TODO this probably belongs in a service
    $result = $this
      ->entityManager()
      ->getStorage('salesforce_mapped_object')
      ->loadByProperties(array(
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId()
    ));

    // @TODO change this to allow one-to-many mapping support.
    if (!empty($result)) {
      return current($result);
    }
    // If an existing mapping was not found, return a new stub instead.
    return new SalesforceMappedObject(array(
      'entity_id' => array(LanguageInterface::LANGCODE_DEFAULT => $entity->id()),
      'entity_type' => array(LanguageInterface::LANGCODE_DEFAULT => $entity->getEntityTypeId()),
    ));    
  }

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
    $entity = $this->getEntity($route_match);
    $salesforce_mapped_object = $this->getMappedObject($entity); 
    if (empty($salesforce_mapped_object)) {
      return 'Object is not mapped. Use the edit form to push or manually set mapping.';
    }
    else {
      // show the entity view for the mapped object
    }
  }

  public function edit(RouteMatchInterface $route_match) {
    // Show the "create" form
    $entity = $this->getEntity($route_match);
    $salesforce_mapped_object = $this->getMappedObject($entity);
    $form = $this->entityFormBuilder()->getForm($salesforce_mapped_object, 'edit');
    return $form;
  }

  public function delete(RouteMatchInterface $route_match) {
    $entity = $this->getEntity($route_match);
    $salesforce_mapped_object = $this->getMappedObject($entity);
    if (empty($salesforce_mapped_object)) {
      return array('#markup' => 'This entity is not yet mapped to Salesforce.');
    }
    $form = $this->entityFormBuilder()->getForm($salesforce_mapped_object, 'delete');
    return $form;
  }

}
