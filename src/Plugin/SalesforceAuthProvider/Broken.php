<?php

namespace Drupal\salesforce\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Consumer\SalesforceCredentials;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Service\Exception\MissingRefreshTokenException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Broken constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->configuration = $configuration;
    $this->pluginDefinition = $plugin_definition;
    $this->id = $plugin_id;
    $this->credentials = new SalesforceCredentials('', '', '');
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->messenger()->addError($this->t('Auth provider for %id is missing or broken.', ['%id' => $this->id]));
    return $form;
  }

}
