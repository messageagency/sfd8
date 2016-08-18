<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Routing\RouteSubscriber.
 */

namespace Drupal\salesforce_mapping\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  } 
 //  *   links = {
 // *     "canonical" = "/node/{node}",
 // *     "delete-form" = "/node/{node}/delete",
 // *     "edit-form" = "/node/{node}/edit",
 // *     "version-history" = "/node/{node}/revisions",
 // *     "revision" = "/node/{node}/revisions/{node_revision}/view",
 // *   }


  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Note the empty operation, so we get the nice clean route "entity.entity-type.salesforce"
      foreach (array('', 'edit', 'delete') as $op) {
        if ($route = $this->getMappedObjectRoute($entity_type, $op)) {
          $sf_route = !empty($op) ? "salesforce_$op" : 'salesforce';
          $routename = "entity.$entity_type_id.$sf_route";
          $collection->add($routename, $route);
        }
      }
    }
  }

  /**
   * Gets the devel load route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $op
   *   The salesforce mapped object operation. view, edit, add, or delete.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getMappedObjectRoute(EntityTypeInterface $entity_type, $op) {
    if ($path = $entity_type->getLinkTemplate('salesforce')) {
      $entity_type_id = $entity_type->id();
      if (empty($op)) {
        $route = new Route($path);
        $op = 'view';
      }
      else {
        $route = new Route($path . "/$op");
      }
      $route
        ->addDefaults([
          '_controller' => "\Drupal\salesforce_mapping\Controller\MappedObjectController::$op",
          '_title' => "Salesforce mapped object $op",
        ])
        ->addRequirements([
          '_permission' => 'administer salesforce',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_salesforce_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = array('onAlterRoutes', 100);
    return $events;
  }

}