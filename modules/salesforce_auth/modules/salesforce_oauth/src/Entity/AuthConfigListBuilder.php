<?php

namespace Drupal\salesforce_oauth\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * List builder for JWT Auth Configs.
 */
class AuthConfigListBuilder extends ConfigEntityListBuilder {

  /**
   * @param \Drupal\salesforce_jwt\Entity\JWTAuthConfig $entity
   *
   * @return array
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['url'] = $entity->getLoginUrl();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    $operations['revoke'] = [
      'title' => $this->t('Revoke'),
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

    return $header + parent::buildHeader();
  }

}
