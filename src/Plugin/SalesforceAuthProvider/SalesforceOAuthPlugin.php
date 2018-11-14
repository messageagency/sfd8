<?php

namespace Drupal\salesforce\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Consumer\OAuthCredentials;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use Drupal\salesforce\SalesforceOAuthPluginInterface;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use Drupal\salesforce\Token\SalesforceToken;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\Uri;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Plugin(
 *   id = "oauth",
 *   label = @Translation("Salesforce OAuth User-Agent")
 * )
 */
class SalesforceOAuthPlugin extends SalesforceAuthProviderPluginBase implements SalesforceOAuthPluginInterface {

  /** @var \Drupal\salesforce\Consumer\OAuthCredentials */
  protected $credentials;

  const SERVICE_TYPE = 'oauth';
  const LABEL = 'OAuth';

  /**
   * SalesforceOAuthPlugin constructor.
   *
   * @param $id
   * @param \Drupal\salesforce\Consumer\OAuthCredentials $credentials
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $storage
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   */
  public function __construct($id, OAuthCredentials $credentials, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage) {
    parent::__construct($credentials, $httpClient, $storage, [], new Uri($credentials->getLoginUrl()));
    $this->id = $id;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(self::defaultConfiguration(), $configuration);
    $cred = new OAuthCredentials($configuration['consumer_key'], $configuration['login_url'], $configuration['consumer_secret']);
    return new static($configuration['id'], $cred, $container->get('salesforce.http_client_wrapper'), $container->get('salesforce.auth_token_storage'));
  }


  public static function defaultConfiguration() {
    $defaults = parent::defaultConfiguration();
    return array_merge($defaults, [
      'consumer_secret' => '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['consumer_key'] = [
      '#title' => t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getConsumerKey()
    ];

    $form['consumer_secret'] = [
      '#title' => $this->t('Salesforce consumer secret'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer secret of the Salesforce remote application.'),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getConsumerSecret()
    ];

    $form['login_url'] = [
      '#title' => t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->credentials->getLoginUrl(),
      '#description' => t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->setConfiguration($form_state->getValues());
    $settings = $form_state->getValue('provider_settings');
    // Write the config id to private temp store, so that we can use the same
    // callback URL for all OAuth applications in Salesforce.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('user.private_tempstore')->get('salesforce_oauth');
    $tempstore->set('config_id', $form_state->getValue('id'));

    try {
      $path = $this->getAuthorizationEndpoint();
      $query = [
        'redirect_uri' => $this->credentials->getCallbackUrl(),
        'response_type' => 'code',
        'client_id' => $settings['consumer_key'],
      ];

      // Send the user along to the Salesforce OAuth login form. If successful,
      // the user will be redirected to {redirect_uri} to complete the OAuth
      // handshake, and thence to the entity listing. Upon failure, the user
      // redirect URI will send the user back to the edit form.
      $response = new TrustedRedirectResponse($path . '?' . http_build_query($query), 302);
      $response->send();
      return;
    }
    catch (\Exception $e) {
      $this->messenger()->addError(t("Error during authorization: %message", ['%message' => $e->getMessage()]));
      // $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
    }
  }

  public function getConsumerSecret() {
    return $this->credentials->getConsumerSecret();
  }

  /**
   * @return bool
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   * @see \Drupal\salesforce\Controller\SalesforceOAuthController
   */
  public function finalizeOauth() {
    $token = $this->requestAccessToken(\Drupal::request()->get('code'));

    // Initialize identity.
    $headers = [
      'Authorization' => 'OAuth ' . $token->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = $token->getExtraParams();
    $response = $this->httpClient->retrieveResponse(new Uri($data['id']), [], $headers);
    $identity = $this->parseIdentityResponse($response);
    $this->storage->storeIdentity($this->service(), $identity);
    return TRUE;
  }

}