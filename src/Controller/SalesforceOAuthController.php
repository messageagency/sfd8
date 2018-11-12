<?php

namespace Drupal\salesforce\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\MetadataBubblingUrlGenerator;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\Plugin\SalesforceAuthProvider\SalesforceOAuthPlugin;
use Drupal\salesforce\SalesforceOAuthPluginInterface;
use Drupal\salesforce_oauth\SalesforceAuthProvider;
use Drupal\salesforce_oauth\Entity\OAuthConfig;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 *
 */
class SalesforceOAuthController extends ControllerBase {

  protected $request;
  protected $messenger;
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

    if (empty($configId) || !($config = SalesforceAuthConfig::load($configId)) || !($config->getPlugin() instanceof SalesforceOAuthPluginInterface)) {
      $this->messenger->addError('No OAuth config found. Please try again.');
      return new RedirectResponse(Url::fromRoute('entity.salesforce_auth.collection')->toString());
    }

    /** @var \Drupal\salesforce\SalesforceOAuthPluginInterface $oauth */
    $oauth = $config->getPlugin();
    if ($oauth->finalizeOauth()) {
      $this->messenger()->addStatus(t('Successfully connected to Salesforce.'));
    }
    else {
      $this->messenger()->addError(t('Salesforce auth failed.'));
    }
    return new RedirectResponse(Url::fromRoute('entity.salesforce_auth.collection')->toString());
  }

}
