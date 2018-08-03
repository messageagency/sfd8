<?php

namespace Drupal\salesforce_auth\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * List builder for JWT Auth Configs.
 */
class SalesforceAuthListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\salesforce_auth\SalesforceAuthProviderInterface $plugin */
    $plugin = $entity->getPlugin();
    $row['label'] = $entity->label();
    $row['url'] = $plugin->getLoginUrl();
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
    $header['type'] = [
      'data' => $this->t('Auth Type'),
    ];
    $header['url'] = [
      'data' => $this->t('Login URL'),
    ];

    return $header + parent::buildHeader();
  }

}
