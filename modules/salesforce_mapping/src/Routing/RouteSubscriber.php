<?php

namespace Drupal\salesforce_mapping\Routing;

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

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Note the empty operation, so we get the nice clean route "entity.entity-type.salesforce".
      if (!($path = $entity_type->getLinkTemplate('salesforce'))) {
        continue;
      }
      // Create the "listing" route to show all the mapped objects for this entity.
      $route = new Route($path);
      $route
        ->addDefaults([
          '_controller' => "\Drupal\salesforce_mapping\Controller\MappedObjectController::listing",
          '_title' => "Salesforce mapped objects",
        ])
        ->addRequirements([
          '_permission' => 'administer salesforce',
        ])
        ->setOption('_admin_route', TRUE)
        ->setOption('_salesforce_entity_type_id', $entity_type_id)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
      $collection->add("entity.$entity_type_id.salesforce", $route);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
