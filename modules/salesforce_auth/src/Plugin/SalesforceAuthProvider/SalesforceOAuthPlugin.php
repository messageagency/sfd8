<?php

namespace Drupal\salesforce_auth\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce_auth\Consumer\OAuthCredentials;
use Drupal\salesforce_auth\Entity\SalesforceAuthConfig;
use Drupal\salesforce_auth\SalesforceAuthProviderPluginBase;
use Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface;
use Drupal\salesforce_auth\Token\SalesforceToken;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\Uri;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * @Plugin(
 *   id = "oauth",
 *   label = @Translation("Salesforce OAuth User-Agent")
 * )
 */
class SalesforceOAuthPlugin extends SalesforceAuthProviderPluginBase {

  /** @var \Drupal\salesforce_auth\Consumer\OAuthCredentials */
  protected $credentials;

  const SERVICE_TYPE = 'oauth';
  const LABEL = 'OAuth';

  /**
   * SalesforceOAuthPlugin constructor.
   *
   * @param $id
   * @param \Drupal\salesforce_auth\Consumer\OAuthCredentials $credentials
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   * @param \Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface $storage
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
    return new static($configuration['id'], $cred, $container->get('salesforce_auth.http_client_wrapper'), $container->get('salesforce_auth.token_storage'));
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
    dpm($settings);
    // Write the config id to private temp store, so that we can use the same
    // callback URL for all OAuth applications in Salesforce.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('user.private_tempstore')->get('salesforce_oauth');
    $tempstore->set('config_id', $form_state->getValue('id'));

    try {
      $path = $this->getAuthorizationEndpoint();
      $query = [
        'redirect_uri' => self::getAuthCallbackUrl(),
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
      drupal_set_message(t("Error during authorization: %message", ['%message' => $e->getMessage()]), 'error');
      // $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
    }
  }

  public static function getAuthCallbackUrl() {
    return Url::fromRoute('salesforce_auth.oauth_callback', [], [
      'absolute' => TRUE,
      'https' => TRUE,
    ])->toString();
  }

  public function getConsumerKey() {
    return $this->credentials->getConsumerKey();
  }

  public function getConsumerSecret() {
    return $this->credentials->getConsumerSecret();
  }

  public static function oauthCallback() {
    if (empty(\Drupal::request()->get('code'))) {
      throw new AccessDeniedHttpException();
    }
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('user.private_tempstore')->get('salesforce_oauth');
    $configId = $tempstore->get('config_id');
    if (empty($configId) || !($config = SalesforceAuthConfig::load($configId)) || !($config->getPlugin() instanceof SalesforceOAuthPlugin)) {
      \Drupal::messenger()->addError('No OAuth config found. Please try again.');
      return new RedirectResponse(Url::fromRoute('entity.salesforce_oauth_config.collection'));
    }

    /** @var \Drupal\salesforce_auth\Plugin\SalesforceAuthProvider\SalesforceOAuthPlugin $oauth */
    $oauth = $config->getPlugin();
    return $oauth->finalizeOauth();
  }

  public function finalizeOauth() {
    $form_params = [
      'code' => \Drupal::request()->get('code'),
      'grant_type' => 'authorization_code',
      'client_id' => $this->getConsumerKey(),
      'client_secret' => $this->getConsumerSecret(),
      'redirect_uri' => self::getAuthCallbackUrl(),
    ];
    $url = $this->getAccessTokenEndpoint();
    $headers = [
      // This is an undocumented requirement on SF's end.
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    $response = $this->httpClient->retrieveResponse($url, ['headers' => $headers, 'form_params' => $form_params]);
    $token = $this->parseAccessTokenResponse($response);
    $this->storage->storeAccessToken($this->service(), $token);


    // Initialize identity.
    $headers = [
      'Authorization' => 'OAuth ' . $token->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = $token->getExtraParams();
    $response = $this->httpClient->retrieveResponse(new Uri($data['id']), [], $headers);
    $identity = $this->parseIdentityResponse($response);
    $this->storage->storeIdentity($this->service(), $identity);

    \Drupal::messenger()->addStatus(t('Successfully connected to Salesforce.'));
    return new RedirectResponse(Url::fromRoute('entity.salesforce_auth.collection')->toString());
  }

}