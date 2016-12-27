<?php

namespace Drupal\salesforce\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 *
 */
class SalesforceController extends ControllerBase {

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
    $salesforce = salesforce_get_api();

    $data = urldecode(UrlHelper::buildQuery([
      'code' => $_GET['code'],
      'grant_type' => 'authorization_code',
      'client_id' => $salesforce->getConsumerKey(),
      'client_secret' => $salesforce->getConsumerSecret(),
      'redirect_uri' => $salesforce->getAuthCallbackUrl(),
    ]));
    $url = $salesforce->getAuthTokenUrl();
    $headers = [
      // This is an undocumented requirement on SF's end.
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    $http_client = \Drupal::service('http_client');
    $response = $http_client->post($url, ['headers' => $headers, 'body' => $data]);

    $salesforce->handleAuthResponse($response);

    return new RedirectResponse(\Drupal::url('salesforce.authorize', [], ['absolute' => TRUE]));
  }

}
