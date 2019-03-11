<?php

namespace Drupal\salesforce_mapping_ui\Plugin\Menu\LocalAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Local action for salesforce mapped objects.
 */
class SalesforceMappedObjectAddLocalAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    // @TODO unclear how to translate this, but needs to be translated:
    return 'Create Mapped Object';
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    // If our local action is appearing contextually on an entity, provide
    // contextual entity paramaters to the add form link.
    $options = parent::getOptions($route_match);
    $entity_type_id = $route_match->getRouteObject()->getOption('_salesforce_entity_type_id');
    if (empty($entity_type_id)) {
      return $options;
    }
    $entity = $route_match->getParameter($entity_type_id);
    if (!$entity || !($entity instanceof EntityInterface)) {
      return $options;
    }
    $options['query'] = ['entity_type_id' => $entity_type_id, 'entity_id' => $entity->id()];
    return $options;
  }

}
