<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\UnroutedUrlAssembler;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Form\AuthorizeForm;
use Drupal\salesforce\Rest\RestClientInterface;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\salesforce\Form\AuthorizeForm
 * @group salesforce
 */

class AuthorizeFormTest extends UnitTestCase {

  public function setUp() {
    parent::setUp();

    $this->example_url = 'https://example.com';

    $this->config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $this->state = $this->prophesize(StateInterface::class);
    $this->client = $this->prophesize(RestClientInterface::class);
    $this->request_stack = $this->prophesize(RequestStack::class);
    $this->obpath = $this->prophesize(OutboundPathProcessorInterface::class);
    $this->unrouted_url_assembler = new UnroutedUrlAssembler($this->request_stack->reveal(), $this->obpath->reveal());

    $this->client->getAuthCallbackUrl()->willReturn($this->example_url);
    $this->client->getAuthEndpointUrl()->willReturn($this->example_url);

    $this->client->setConsumerKey(Argument::any())->willReturn(NULL);
    $this->client->setConsumerSecret(Argument::any())->willReturn(NULL);
    $this->client->setLoginUrl(Argument::any())->willReturn(NULL);

    $container = new ContainerBuilder();
    $container->set('config.factory', $this->config_factory->reveal());
    $container->set('salesforce.client', $this->client->reveal());
    $container->set('state', $this->state->reveal());
    $container->set('unrouted_url_assembler', $this->unrouted_url_assembler);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm() {
    $form_state = new FormState();
    $form_state->setValues([
      'consumer_key' => $this->randomMachineName(),
      'consumer_secret' => $this->randomMachineName(),
      'login_url' => $this->example_url,
    ]);
    
    $form = AuthorizeForm::create(\Drupal::getContainer());
    $form_array = [];
    $form->submitForm($form_array, $form_state);
    $response = $form_state->getResponse();
    $this->assertTrue($response instanceof TrustedRedirectResponse);
    $this->assertEquals($this->example_url . '?redirect_uri=' . urlencode($this->example_url) . '&response_type=code&client_id=' . $form_state->getValue('consumer_key'), $response->getTargetUrl());
  }

}
