<?php

namespace Drupal\salesforce\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\MetadataBubblingUrlGenerator;
use Drupal\salesforce\Rest\RestClientInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 *
 */
class SalesforceController extends ControllerBase {

  protected $client;
  protected $http_client;
  /**
   * {@inheritdoc}
   */
  public function __construct(RestClientInterface $rest, Client $http_client, MetadataBubblingUrlGenerator $url_generator) {
    $this->client = $rest;
    $this->http_client = $http_client;
    $this->url_generator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('salesforce.client'),
      $container->get('http_client'),
      $container->get('url_generator')
    );
  }

  protected function request() {
    return \Drupal::request();
  }

  protected function successMessage() {
    drupal_set_message(t('Successfully connected to %endpoint', ['%endpoint' => $this->client->getInstanceUrl()]));
  }

  /**
   * OAuth step 2: Callback for the oauth redirect URI.
   *
   * Complete OAuth handshake by exchanging an authorization code for an access
   * token.
   */
  public function oauthCallback() {
    // If no code is provided, return access denied.
    if (empty($this->request()->get('code'))) {
      throw new AccessDeniedHttpException();
    }

    $data = urldecode(UrlHelper::buildQuery([
      'code' => $this->request()->get('code'),
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

    $response = $this->http_client->post($url, ['headers' => $headers, 'body' => $data]);

    $this->client->handleAuthResponse($response);

    $this->successMessage();

    return new RedirectResponse($this->url_generator->generateFromRoute('salesforce.authorize', [], ["absolute" => true], false));
  }

}
