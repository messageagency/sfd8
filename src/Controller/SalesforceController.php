<?php

namespace Drupal\salesforce\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\salesforce\Rest\RestClient;
/**
 *
 */
class SalesforceController extends ControllerBase {

  protected $client;

  /**
   * {@inheritdoc}
   */
  public function __construct(RestClient $rest) {
    $this->client = $rest;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('salesforce.client')
    );
  }

  /**
   * OAuth step 2: Callback for the oauth redirect URI.
   *
   * Complete OAuth handshake by exchanging an authorization code for an access
   * token.
   */
  public function oauthCallback() {
    // If no code is provided, return access denied.
    if (!isset($_GET['code'])) {
      throw new AccessDeniedHttpException();
    }

    $data = urldecode(UrlHelper::buildQuery([
      'code' => $_GET['code'],
      'grant_type' => 'authorization_code',
      'client_id' => $this->client->getConsumerKey(),
      'client_secret' => $this->client->getConsumerSecret(),
      'redirect_uri' => $this->client->getAuthCallbackUrl(),
    ]));
    $url = $this->client->getAuthTokenUrl();
    $headers = [
      // This is an undocumented requirement on SF's end.
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    $http_client = \Drupal::service('http_client');
    $response = $http_client->post($url, ['headers' => $headers, 'body' => $data]);

    $this->client->handleAuthResponse($response);

    return new RedirectResponse(\Drupal::url('salesforce.authorize', [], ['absolute' => TRUE]));
  }

}
