<?php

namespace Drupal\salesforce_auth\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_auth\Consumer\OAuthCredentials;
use Drupal\salesforce_auth\SalesforceAuthProviderBase;
use Drupal\salesforce_auth\SalesforceAuthProviderPluginInterface;
use Drupal\salesforce_auth\Service\SalesforceOAuth as SalesforceOAuthService;
use Drupal\salesforce_auth\Storage\TokenStorage;
use OAuth\Common\Http\Client\CurlClient;

/**
 * @SalesforceAuthProvider(
 *   id = "oauth",
 *   label = @Translation("Salesforce OAuth User-Agent")
 * )
 */
class SalesforceOAuth extends SalesforceAuthProviderBase implements SalesforceAuthProviderPluginInterface {

  /**
   * The auth provider service.
   * @var \Drupal\salesforce_auth\Service\SalesforceOAuthService
   */
  protected $service;


  /**
   * Service wrapper.
   *
   * @return \Drupal\salesforce_auth\Service\SalesforceOAuthService
   *   The auth service.
   */
  public function service() {
    if (!$this->service) {
      $cred = new OAuthCredentials($this->configuration['consumer_key'], $this->configuration['login_url'], $this->configuration['consumer_secret']);
      $this->service = new SalesforceOAuthService($cred, new CurlClient(), new TokenStorage());
    }
    return $this->service;
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
      '#default_value' => $this->getConfiguration('consumer_key'),
    ];

    $form['consumer_secret'] = [
      '#title' => $this->t('Salesforce consumer secret'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer secret of the Salesforce remote application.'),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration('consumer_secret'),
    ];

    $form['login_url'] = [
      '#title' => t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getConfiguration('login_url') ?: 'https://test.salesforce.com',
      '#description' => t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
dpm($form_state->getValues());
dpm($form);
return;
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('user.private_tempstore')->get('salesforce_oauth');
    $tempstore->set('config_id', $this->entity->id());

    //    $this->sf_client->setConsumerKey($values['consumer_key']);
    //    $this->sf_client->setConsumerSecret($values['consumer_secret']);
    //    $this->sf_client->setLoginUrl($values['login_url']);

    try {
      $path = $form_state->getValue('login_url') . AuthProviderInterface::AUTH_ENDPOINT_PATH;
      $query = [
        'redirect_uri' => Url::fromRoute('salesforce_oauth.oauth_callback', [], [
          'absolute' => TRUE,
          'https' => TRUE,
        ])->toString(),
        'response_type' => 'code',
        'client_id' => $form_state->getValue('consumer_key'),
      ];

      // Send the user along to the Salesforce OAuth login form. If successful,
      // the user will be redirected to {redirect_uri} to complete the OAuth
      // handshake.
      $response = new TrustedRedirectResponse($path . '?' . http_build_query($query), 302);
      $response->send();
      return;
    }
    catch (\Exception $e) {
      drupal_set_message(t("Error during authorization: %message", ['%message' => $e->getMessage()]), 'error');
      //      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
    }


  }

}