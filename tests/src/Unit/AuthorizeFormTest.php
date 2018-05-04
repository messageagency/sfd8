<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\UnroutedUrlAssembler;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Form\AuthorizeForm;
use Drupal\salesforce\Rest\RestClient;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * @coversDefaultClass \Drupal\salesforce\Form\AuthorizeForm
 * @group salesforce
 */
class AuthorizeFormTest extends UnitTestCase {

  /**
   * Set up for each test.
   */
  public function setUp() {
    parent::setUp();

    $this->example_url = 'https://example.com';
    $this->consumer_key = $this->randomMachineName();

    $this->config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $this->state = $this->prophesize(StateInterface::class);
    $this->client = $this->prophesize(RestClient::class);
    $this->request_stack = $this->prophesize(RequestStack::class);
    $this->obpath = $this->prophesize(OutboundPathProcessorInterface::class);
    $this->logger = $this->prophesize(LoggerChannelFactory::class);
    $this->unrouted_url_assembler = new UnroutedUrlAssembler($this->request_stack->reveal(), $this->obpath->reveal());
    $this->event_dispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');

    $this->client->getAuthCallbackUrl()->willReturn($this->example_url);
    $this->client->getAuthEndpointUrl()->willReturn($this->example_url);
    $this->client->getConsumerKey()->willReturn($this->consumer_key);

    $this->client->setConsumerKey(Argument::any())->willReturn(NULL);
    $this->client->setConsumerSecret(Argument::any())->willReturn(NULL);
    $this->client->setLoginUrl(Argument::any())->willReturn(NULL);

    $container = new ContainerBuilder();
    $container->set('config.factory', $this->config_factory->reveal());
    $container->set('salesforce.client', $this->client->reveal());
    $container->set('state', $this->state->reveal());
    $container->set('unrouted_url_assembler', $this->unrouted_url_assembler);
    $container->set('logger.factory', $this->logger->reveal());
    $container->set('event_dispatcher', $this->event_dispatcher);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm() {
    $form_state = new FormState();
    $form_state->setValues([
      'consumer_key' => $this->consumer_key,
      'consumer_secret' => $this->randomMachineName(),
      'login_url' => $this->example_url,
    ]);

    $form = AuthorizeForm::create(\Drupal::getContainer());
    $form_array = [];
    $form->submitForm($form_array, $form_state);
    /** @var \Drupal\Core\Routing\TrustedRedirectResponse $response */
    $response = $form_state->getResponse();
    $this->assertTrue($response instanceof TrustedRedirectResponse);
    $this->assertEquals($this->example_url . '?redirect_uri=' . urlencode($this->example_url) . '&response_type=code&client_id=' . $form_state->getValue('consumer_key'), $response->getTargetUrl());
  }

}
