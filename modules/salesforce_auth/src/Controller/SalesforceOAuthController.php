<?php

namespace Drupal\salesforce_auth\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\MetadataBubblingUrlGenerator;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce_auth\Entity\SalesforceAuthConfig;
use Drupal\salesforce_oauth\SalesforceAuthProvider;
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
  public function __construct(Client $http_client, StateInterface $state, ConfigFactoryInterface $configFactory, SalesforceAuthMa) {
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
      $container->get('salesforce_auth')
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
    if (empty($configId) || !($config = SalesforceAuthConfig::load($configId))) {
      \Drupal::messenger()->addError('No OAuth config found. Please try again.');
      return new RedirectResponse(Url::fromRoute('entity.salesforce_oauth_config.collection'));
    }

    /** @var \Drupal\salesforce_auth\Plugin\SalesforceAuthProvider\SalesforceOAuthPlugin $oauth */
    $oauth = $config->getPlugin();
    $form_params = [
      'code' => $this->request()->get('code'),
      'grant_type' => 'authorization_code',
      'client_id' => $oauth->getConsumerKey(),
      'client_secret' => $oauth->getConsumerSecret(),
      'redirect_uri' => $this->getAuthCallbackUrl(),
    ];
    $url = $config->getPlugin()->getAccessTokenEndpoint();
    $headers = [
      // This is an undocumented requirement on SF's end.
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    $response = $this->http_client->post($url, ['headers' => $headers, 'form_params' => $form_params]);
    $config->getPlugin()->handleAuthResponse($response, $this->oauth->getToken($configId));
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
