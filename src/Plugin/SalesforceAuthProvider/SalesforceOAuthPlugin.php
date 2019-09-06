<?php

namespace Drupal\salesforce\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\salesforce\Consumer\OAuthCredentials;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;

/**
 * Salesforce OAuth user-agent flow auth provider plugin.
 *
 * @Plugin(
 *   id = "oauth",
 *   label = @Translation("Salesforce OAuth User-Agent"),
 *   credentials_class = "\Drupal\salesforce\Consumer\OAuthCredentials"
 * )
 */
class SalesforceOAuthPlugin extends SalesforceAuthProviderPluginBase {

  /**
   * Credentials.
   *
   * @var \Drupal\salesforce\Consumer\OAuthCredentials
   */
  protected $credentials;

  /**
   * {@inheritdoc}
   */
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
      '#default_value' => $this->getCredentials()->getConsumerKey(),
    ];

    $form['consumer_secret'] = [
      '#title' => $this->t('Salesforce consumer secret'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer secret of the Salesforce remote application.'),
      '#required' => TRUE,
      '#default_value' => $this->getCredentials()->getConsumerSecret(),
    ];

    $form['login_url'] = [
      '#title' => t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getCredentials()->getLoginUrl(),
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

    // Write the config id to private temp store, so that we can use the same
    // callback URL for all OAuth applications in Salesforce.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('user.private_tempstore')->get('salesforce_oauth');
    $tempstore->set('config_id', $form_state->getValue('id'));

    try {
      $path = $this->getAuthorizationEndpoint();
      $query = [
        'redirect_uri' => $this->getCredentials()->getCallbackUrl(),
        'response_type' => 'code',
        'client_id' => $this->getCredentials()->getConsumerKey(),
      ];

      // Send the user along to the Salesforce OAuth login form. If successful,
      // the user will be redirected to {redirect_uri} to complete the OAuth
      // handshake, and thence to the entity listing. Upon failure, the user
      // redirect URI will send the user back to the edit form.
      $form_state->setResponse(new TrustedRedirectResponse(Url::fromUri($path . '?' . http_build_query($query))->toString()));
    }
    catch (\Exception $e) {
      $form_state->setError($form, $this->t("Error during authorization: %message", ['%message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumerSecret() {
    return $this->getCredentials()->getConsumerSecret();
  }

}
