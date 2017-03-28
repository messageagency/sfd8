<?php

namespace Drupal\Tests\salesforce_encrypt\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce_encrypt\Rest\RestClient;
use Drupal\Core\Config\ConfigFactory;
use GuzzleHttp\Client;
use Drupal\Core\State\State;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\encrypt\EncryptionProfileInterface;

/**
 * @coversDefaultClass \Drupal\salesforce_encrypt\Rest\RestClient
 * @group salesforce
 */

class RestClientTest extends UnitTestCase {

  static $modules = ['key', 'encrypt', 'salesforce', 'salesforce_encrypt'];

  public function setUp() {
    parent::setUp();
    $this->accessToken = 'foo';
    $this->refreshToken = 'bar';
    $this->identity = array('zee' => 'bang');
    
    $this->httpClient = $this->getMock(Client::CLASS);
    $this->configFactory = 
      $this->getMockBuilder(ConfigFactory::CLASS)
        ->disableOriginalConstructor()
        ->getMock();
    $this->state =
      $this->getMockBuilder(State::CLASS)
        ->disableOriginalConstructor()
        ->getMock();
    $this->cache = $this->getMock(CacheBackendInterface::CLASS);
    $this->encryption = $this->getMock(EncryptServiceInterface::CLASS);
    $this->profileManager = $this->getMock(EncryptionProfileManagerInterface::CLASS);
    $this->lock = $this->getMock(LockBackendInterface::CLASS);
    $this->encryptionProfile = $this->getMock(EncryptionProfileInterface::CLASS);
    $this->json = $this->getMock(Json::CLASS);
    $this->client = $this->getMock(RestClient::CLASS, ['getEncryptionProfile'], [$this->httpClient, $this->configFactory, $this->state, $this->cache, $this->json, $this->encryption, $this->profileManager, $this->lock]);
  }

  /**
   * @covers ::getDecrypted
   *
   * getDecrypted is protected, so we get at it through ::getAccessToken
   * This test covers the case where access token is NULL.
   */
  public function testGetDecryptedNull() {
    // Test unencrypted
    $this->state->expects($this->any())
      ->method('get')
      ->willReturn(NULL);
    $this->client->expects($this->at(0))
      ->method('getEncryptionProfile')
      ->willReturn(FALSE);
    $this->client->expects($this->at(1))
      ->method('getEncryptionProfile')
      ->willReturn(TRUE);
    $this->assertNull($this->client->getAccessToken());
    $this->assertFalse($this->client->getAccessToken());
  }

  /**
   * @covers ::getDecrypted
   *
   * This test covers the case where access token is not NULL.
   */
  public function testGetDecryptedNotNull() {
    // Test unencrypted
    $this->state->expects($this->once())
      ->method('get')
      ->willReturn('not null');
    $this->client->expects($this->once())
      ->method('getEncryptionProfile')
      ->willReturn($this->encryptionProfile);
    $this->encryption->expects($this->once())
      ->method('decrypt')
      ->willReturn($this->accessToken);
    $this->assertEquals($this->accessToken, $this->client->getAccessToken());
  }

}
