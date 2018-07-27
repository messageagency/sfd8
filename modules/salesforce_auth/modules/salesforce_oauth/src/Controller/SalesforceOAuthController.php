<?php

namespace Drupal\salesforce_oauth\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\MetadataBubblingUrlGenerator;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce_oauth\AuthProvider;
use Drupal\salesforce_oauth\Entity\OAuthConfig;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 *
 */
class SalesforceOAuthController extends ControllerBase {

  protected $http_client;
  protected $state;
  protected $mutableConfig;
  protected $immutableConfig;
  protected $configFactory;
  protected $oauth;

  /**
   * {@inheritdoc}
   */
  public function __construct(Client $http_client, StateInterface $state, ConfigFactoryInterface $configFactory, AuthProvider $oauth) {
    $this->http_client = $http_client;
    $this->state = $state;
    $this->configFactory = $configFactory;
    $this->mutableConfig = $this->configFactory->getEditable('salesforce.settings');
    $this->immutableConfig = $this->configFactory->get('salesforce.settings');
    $this->oauth = $oauth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('state'),
      $container->get('config.factory'),
      $container->get('salesforce_oauth.auth_provider')
    );
  }

  /**
   *
   */
  protected function request() {
    return \Drupal::request();
  }

  /**
   * OAuth step 2: Callback for the oauth redirect URI.
   *
   * Complete OAuth handshake by exchanging an authorization code for an access
   * token.
   * @throws \Exception
   *   In case of network or other http client issue.
   */
  public function oauthCallback() {
    // If no code is provided, return access denied.
    if (empty($this->request()->get('code'))) {
      throw new AccessDeniedHttpException();
    }
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('user.private_tempstore')->get('salesforce_oauth');
    $configId = $tempstore->get('config_id');
    if (empty($configId) || !($config = OAuthConfig::load($configId))) {
      \Drupal::messenger()->addError('No OAuth config found. Please try again.');
      return new RedirectResponse(Url::fromRoute('entity.salesforce_oauth_config.collection'));
    }

    $form_params = [
      'code' => $this->request()->get('code'),
      'grant_type' => 'authorization_code',
      'client_id' => $config->getConsumerKey(),
      'client_secret' => $config->getConsumerSecret(),
      'redirect_uri' => $this->getAuthCallbackUrl(),
    ];
    $url = $config->getAuthTokenUrl();
    $headers = [
      // This is an undocumented requirement on SF's end.
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    $response = $this->http_client->post($url, ['headers' => $headers, 'form_params' => $form_params]);
    $this->oauth->handleAuthResponse($response, $this->oauth->getToken($configId));
    \Drupal::messenger()->addStatus(t('Successfully connected to Salesforce.'));
    return new RedirectResponse($config->toUrl('edit-form')->toString());
  }

  /**
   * Helper to build the redirect URL for OAUTH workflow.
   *
   * @return string
   *   Redirect URL.
   */
  protected function getAuthCallbackUrl() {
    return Url::fromRoute('salesforce_oauth.oauth_callback', [], [
      'absolute' => TRUE,
      'https' => TRUE,
    ])->toString();
  }

}
