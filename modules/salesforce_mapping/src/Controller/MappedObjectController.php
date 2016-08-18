<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Controller\MappedObjectController.
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
use Drupal\salesforce_mapping\Entity\MappedObject;

/**
 * Returns responses for devel module routes.
 */
class MappedObjectController extends ControllerBase {

  /**
   * Helper function to get entity from router path
   * e.g. get User from user/1/salesforce
   *
   * @param RouteMatchInterface $route_match 
   * @return EntityInterface
   * @throws Exception if an EntityInterface is not found at the given route
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
   * Helper function to fetch existing MappedObject or create a new one
   *
   * @param EntityInterface $entity
   *   The entity to be mapped.
   *
   * @return MappedObject
   */
  private function getMappedObject(EntityInterface $entity) {
    // @TODO this probably belongs in a service
    $result = $this
      ->entityManager()
      ->getStorage('salesforce_mapped_object')
      ->loadByProperties(array(
        'entity_id' => $entity->id(),
        'entity_type_id' => $entity->getEntityTypeId()
    ));

    // @TODO change this to allow one-to-many mapping support.
    if (!empty($result)) {
      return current($result);
    }
    // If an existing mapping was not found, return a new stub instead.
    return new MappedObject(array(
      'entity_id' => array(LanguageInterface::LANGCODE_DEFAULT => $entity->id()),
      'entity_type_id' => array(LanguageInterface::LANGCODE_DEFAULT => $entity->getEntityTypeId()),
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
    if ($salesforce_mapped_object->isNew()) {
      return array('#markup' => $this->t(
        'Object is not mapped. <a href="@href">Use the edit form</a> to push or manually set mapping.',
        array('@href' => $entity->toUrl('salesforce_edit')->toString())));
    }

    // show the entity view for the mapped object
    $builder = $this->entityTypeManager()->getViewBuilder('salesforce_mapped_object');
    return $builder->view($salesforce_mapped_object);
  }

  /**
   * Show the MappedObject entity add/edit form
   *
   * @param RouteMatchInterface $route_match
   * @return array
   *   Renderable form array
   */
  public function edit(RouteMatchInterface $route_match) {
    // Show the "create" form
    $entity = $this->getEntity($route_match);
    $salesforce_mapped_object = $this->getMappedObject($entity);
    $form = $this->entityFormBuilder()->getForm($salesforce_mapped_object, 'edit');
    // @TODO add validation for fieldmap options
    // @TODO add validation of SFID input
    // @TODO create a new field / data type for SFID (?)
    return $form;
  }

  /**
   * Show the MappedObject delete form.
   *
   * @param RouteMatchInterface $route_match 
   * @return array
   *   Renderable form array
   */
  public function delete(RouteMatchInterface $route_match) {
    $entity = $this->getEntity($route_match);
    $salesforce_mapped_object = $this->getMappedObject($entity);
    if ($salesforce_mapped_object->isNew()) {
      return array('#markup' => 'Object is not mapped. Use the edit form to push or manually set mapping.');
    }
    $form = $this->entityFormBuilder()->getForm($salesforce_mapped_object, 'delete');
    return $form;
  }

}
