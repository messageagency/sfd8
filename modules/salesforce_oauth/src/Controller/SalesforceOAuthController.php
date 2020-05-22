<?php

namespace Drupal\salesforce_oauth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\SalesforceAuthProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * SalesforceOAuthController.
 */
class SalesforceOAuthController extends ControllerBase {

  /**
   * Request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Temp store factory service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $stack, MessengerInterface $messenger, PrivateTempStoreFactory $tempStoreFactory) {
    $this->request = $stack->getCurrentRequest();
    $this->messenger = $messenger;
    $this->tempStore = $tempStoreFactory->get('salesforce_oauth');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('messenger'),
      $container->get('user.private_tempstore')
    );
  }

  /**
   * Pass-through to OAuth plugin.
   */
  public function oauthCallback() {
    if (empty($this->request->get('code'))) {
      throw new AccessDeniedHttpException();
    }
    $configId = $this->tempStore->get('config_id');

    if (empty($configId) || !($config = SalesforceAuthConfig::load($configId)) || !($config->getPlugin() instanceof SalesforceAuthProviderInterface)) {
      $this->messenger->addError($this->t('No OAuth config found. Please try again.'));
      return new RedirectResponse(Url::fromRoute('entity.salesforce_auth.collection')->toString());
    }

    /** @var \Drupal\salesforce\SalesforceAuthProviderInterface $oauth */
    $oauth = $config->getPlugin();
    if (\Drupal::request()->get('code')) {
      try {
        $oauth->requestAccessToken(\Drupal::request()->get('code'));
        $this->messenger()
          ->addStatus($this->t('Successfully connected to Salesforce.'));
        return new RedirectResponse(Url::fromRoute('entity.salesforce_auth.collection')
          ->toString());
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Salesforce auth failed: @message', ['@message' => $e->getMessage() ?: get_class($e)]));
      }
    }
    else {
      $this->messenger()->addError($this->t('Salesforce auth failed: no oauth code received.'));
    }
    return new RedirectResponse(Url::fromRoute('entity.salesforce_auth.edit_form', ['salesforce_auth' => $configId])->toString());
  }

}
