<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\MetadataBubblingUrlGenerator;
use Drupal\Core\Url;
use Drupal\salesforce\Controller\SalesforceController;
use Drupal\salesforce\Rest\RestClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\salesforce\Controller\SalesforceController
 * @group salesforce
 */

class SalesforceControllerTest extends UnitTestCase {

  function setUp() {
    parent::setUp();

    $this->example_url = 'https://example.com';

    $this->httpClient = $this->getMock('\GuzzleHttp\Client', ['post']);
    $this->httpClient->expects($this->once())
      ->method('post')
      ->willReturn(new Response());
    $this->configFactory =
      $this->getMockBuilder('\Drupal\Core\Config\ConfigFactory')
        ->disableOriginalConstructor()
        ->getMock();
    $this->state =
      $this->getMockBuilder('\Drupal\Core\State\State')
        ->disableOriginalConstructor()
        ->getMock();
    $this->cache = $this->getMock('\Drupal\Core\Cache\CacheBackendInterface');
    $this->json = $this->getMock(Json::CLASS);

    $args = [$this->httpClient, $this->configFactory, $this->state, $this->cache, $this->json];

    $this->client = $this->getMock(RestClient::class, ['getConsumerKey', 'getConsumerSecret', 'getAuthCallbackUrl', 'getAuthTokenUrl', 'handleAuthResponse'], $args);
    $this->client->expects($this->once())
      ->method('getConsumerKey')
      ->willReturn($this->randomMachineName());
    $this->client->expects($this->once())
      ->method('getConsumerSecret')
      ->willReturn($this->randomMachineName());
    $this->client->expects($this->once())
      ->method('getAuthCallbackUrl')
      ->willReturn($this->example_url);
    $this->client->expects($this->once())
      ->method('getAuthTokenUrl')
      ->willReturn($this->example_url);
    $this->client->expects($this->once())
      ->method('handleAuthResponse')
      ->willReturn($this->client);

    $this->request = new Request(['code' => $this->randomMachineName()]);

    $this->request_stack = $this->getMock(RequestStack::class);
    $this->request_stack->expects($this->exactly(2))
      ->method('getCurrentRequest')
      ->willReturn($this->request);

    $this->url_generator = $this->prophesize(MetadataBubblingUrlGenerator::class);
    $this->url_generator->generateFromRoute('salesforce.authorize',[],["absolute" => true], false)
      ->willReturn('foo/bar');

    $container = new ContainerBuilder();
    $container->set('salesforce.client', $this->client);
    $container->set('http_client', $this->httpClient);
    $container->set('request_stack', $this->request_stack);
    $container->set('url.generator', $this->url_generator->reveal());
    \Drupal::setContainer($container);

  }

  /**
   * @covers ::oauthCallback
   */
  function testOauthCallback() {
    $this->controller = $this->getMock(SalesforceController::class, ['successMessage'], [$this->client, $this->httpClient, $this->url_generator->reveal()]);
    $this->controller
      ->expects($this->once())
      ->method('successMessage')
      ->willReturn(NULL);
    $expected = new RedirectResponse('foo/bar');
    $actual = $this->controller->oauthCallback();
    $this->assertEquals($expected, $actual);
  }

}
