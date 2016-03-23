<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\SalesforceMappingAccessController
 */

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\salesforce\SalesforceClient;

/**
 * Access controller for the salesforce_mapping entity.
 *
 * @see \Drupal\salesforce_mapping\Entity\SalesforceMapping.
 */
class SalesforceMappingAccessController extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return $account->hasPermission('view salesforce mapping')
          ? AccessResult::allowed()
          : AccessResult::forbidden();
      default:
        // Apparently access controllers don't support dependency injection.
        if (\Drupal::service('salesforce.client')->isAuthorized()) {
          return $account->hasPermission('administer salesforce mapping')
            ? AccessResult::allowed()
            : AccessResult::forbidden();
        }
    }
  }
}
