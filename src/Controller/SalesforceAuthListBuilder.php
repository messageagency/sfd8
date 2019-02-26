<?php

namespace Drupal\salesforce\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\salesforce\Entity\SalesforceAuthConfig;

/**
 * List builder for salesforce_auth.
 */
class SalesforceAuthListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\salesforce\SalesforceAuthProviderInterface $plugin */
    $plugin = $entity->getPlugin();
    $row['label'] = $entity->label();
    $row['url'] = $plugin->getCredentials()->getLoginUrl();
    $row['key'] = substr($plugin->getCredentials()->getConsumerKey(), 0, 16) . '...';
    $row['type'] = $plugin->label();
    $row['status'] = $plugin->hasAccessToken() ? 'Authorized' : 'Missing';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $operations['edit']['title'] = t('Edit / Re-auth');
    if (!$entity instanceof SalesforceAuthConfig
    || !$entity->getPlugin()->hasAccessToken()
    || !$entity->hasLinkTemplate('revoke')) {
      return $operations;
    }
    // Add a "revoke" action if we have a token.
    $operations['revoke'] = [
      'title' => t('Revoke'),
      'weight' => 20,
      'url' => $this->ensureDestination($entity->toUrl('revoke')),
    ];
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = [
      'data' => $this->t('Label'),
    ];
    $header['url'] = [
      'data' => $this->t('Login URL'),
    ];
    $header['key'] = [
      'data' => $this->t('Consumer Key'),
    ];
    $header['type'] = [
      'data' => $this->t('Auth Type'),
    ];
    $header['status'] = [
      'data' => $this->t('Token Status'),
    ];

    return $header + parent::buildHeader();
  }

}
