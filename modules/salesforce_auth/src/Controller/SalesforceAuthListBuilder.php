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
    $row['label'] = $entity->label();
    $row['user'] = $entity->getLoginUser();
    $row['url'] = $entity->getLoginUrl();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = [
      'data' => $this->t('Label'),
    ];
    $header['user'] = [
      'data' => $this->t('Login User'),
    ];
    $header['url'] = [
      'data' => $this->t('Login URL'),
    ];

    return $header + parent::buildHeader();
  }

}
