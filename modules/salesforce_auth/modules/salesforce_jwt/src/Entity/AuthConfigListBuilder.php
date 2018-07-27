<?php

namespace Drupal\salesforce_jwt\Entity;

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
