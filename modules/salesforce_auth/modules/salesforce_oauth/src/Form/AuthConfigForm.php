<?php

namespace Drupal\salesforce_oauth\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\salesforce_auth\AuthProviderInterface;
use Drupal\salesforce_auth\Form\AuthConfigFormBase;
use Drupal\salesforce_oauth\Entity\OAuthConfig;

/**
 * Entity form for JWT Auth Config.
 */
class AuthConfigForm extends AuthConfigFormBase {

  /**
   * The config entity.
   *
   * @var \Drupal\salesforce_oauth\Entity\OAuthConfig
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->entity;

    $form['creds']['consumer_secret'] = [
      '#title' => $this->t('Salesforce consumer secret'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer secret of the Salesforce remote application.'),
      '#required' => TRUE,
      '#default_value' => $config->getConsumerSecret(),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    parent::save($form, $form_state);
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
