<?php

namespace Drupal\salesforce_mapping_ui\Routing;

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
   * The mappable entity types service.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappableEntityTypesInterface
   */
  protected $mappable;

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
      // If the entity didn't get a salesforce link template added by
      // hook_entity_types_alter(), skip it.
      if (!($path = $entity_type->getLinkTemplate('salesforce'))) {
        continue;
      }

      // Create the "listing" route to show all the mapped objects for this
      // entity.
      $route = new Route($path);
      $route
        ->addDefaults([
          '_controller' => "\Drupal\salesforce_mapping_ui\Controller\MappedObjectController::listing",
          '_title' => "Salesforce mapped objects",
        ])
        ->addRequirements([
          '_custom_access' => '\Drupal\salesforce_mapping_ui\Controller\MappedObjectController::access',
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
