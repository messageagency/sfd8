<?php

namespace Drupal\salesforce\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Consumer\SalesforceCredentials;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Service\Exception\MissingRefreshTokenException;

/**
 * Fallback for broken / missing plugin.
 *
 * @Plugin(
 *   id = "broken",
 *   label = @Translation("Broken or missing provider"),
 *   credentials_class = "Drupal\salesforce\Consumer\SalesforceCredentials"
 * )
 */
class Broken extends SalesforceAuthProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getCredentials() {
    return new SalesforceCredentials('', '', '');
  }

  /**
   * {@inheritdoc}
   */
  public function refreshAccessToken(TokenInterface $token) {
    throw new MissingRefreshTokenException();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->messenger()->addError($this->t('Auth provider for %id is missing or broken.', ['%id' => $this->id]));
    return $form;
  }

}
