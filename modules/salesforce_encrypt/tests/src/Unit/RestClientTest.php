<?php

namespace Drupal\Tests\salesforce_encrypt\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\State;
use Drupal\Tests\UnitTestCase;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\salesforce_encrypt\Rest\RestClient;
use GuzzleHttp\Client;
use Drupal\Component\Datetime\TimeInterface;

/**
 * @coversDefaultClass \Drupal\salesforce_encrypt\Rest\RestClient
 * @group salesforce
 */
class RestClientTest extends UnitTestCase {

  static public $modules = [
    'key',
    'encrypt',
    'salesforce',
    'salesforce_encrypt',
  ];

  protected $httpClient;
  protected $configFactory;
  protected $state;
  protected $cache;
  protected $json;
  protected $time;
  protected $encryption;
  protected $profileManager;
  protected $lock;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->accessToken = 'foo';
    $this->refreshToken = 'bar';
    $this->identity = ['zee' => 'bang'];

    $this->httpClient = $this->getMock(Client::CLASS);
    $this->configFactory =
      $this->getMockBuilder(ConfigFactory::CLASS)
        ->disableOriginalConstructor()
        ->getMock();
    $this->state =
      $this->getMockBuilder(State::CLASS)
        ->disableOriginalConstructor()
        ->getMock();
    $this->cache = $this->createMock(CacheBackendInterface::CLASS);
    $this->json = $this->createMock('Drupal\Component\Serialization\Json');
    $this->encryption = $this->createMock(EncryptServiceInterface::CLASS);
    $this->profileManager = $this->createMock(EncryptionProfileManagerInterface::CLASS);
    $this->lock = $this->createMock(LockBackendInterface::CLASS);
    $this->encryptionProfile = $this->createMock(EncryptionProfileInterface::CLASS);
    $this->json = $this->createMock(Json::CLASS);
    $this->time = $this->createMock(TimeInterface::CLASS);
    $this->client = $this->getMockBuilder(RestClient::CLASS)
      ->setMethods(['doGetEncryptionProfile'])
      ->setConstructorArgs([
        $this->httpClient,
        $this->configFactory,
        $this->state,
        $this->cache,
        $this->json,
        $this->time,
        $this->encryption,
        $this->profileManager,
        $this->lock,
      ])
      ->getMock();
  }

  /**
   * @covers ::encrypt
   *
   * encrypt is protected, so we get at it through ::getAccessToken
   * This test covers the case where access token is NULL.
   */
  public function testEncryptNull() {
    // Test unencrypted.
    $this->state->expects($this->any())
      ->method('get')
      ->willReturn(NULL);
    $this->client->expects($this->any())
      ->method('doGetEncryptionProfile')
      ->willReturn(NULL);
    $this->assertFalse($this->client->getAccessToken());
  }

  /**
   * @covers ::encrypt
   *
   * This test covers the case where access token is not NULL.
   */
  public function testEncryptNotNull() {
    // Test unencrypted.
    $this->state->expects($this->any())
      ->method('get')
      ->willReturn('not null');
    $this->client->expects($this->any())
      ->method('doGetEncryptionProfile')
      ->willReturn($this->encryptionProfile);
    $this->encryption->expects($this->any())
      ->method('decrypt')
      ->willReturn($this->accessToken);
    $this->assertEquals($this->accessToken, $this->client->getAccessToken());
  }

}
