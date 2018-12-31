<?php

namespace Drupal\salesforce\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

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
    $row['url'] = $plugin->getLoginUrl();
    $row['key'] = substr($plugin->getConsumerKey(), 0, 16) . '...';
    $row['type'] = $plugin->label();
    return $row + parent::buildRow($entity);
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

    return $header + parent::buildHeader();
  }

}
