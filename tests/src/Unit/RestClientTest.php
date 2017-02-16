<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Exception;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Rest\RestResponse_Describe;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SelectQuery;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

/**
 * @coversDefaultClass \Drupal\salesforce\Rest\RestClient
 * @group salesforce
 */

class RestClientTest extends UnitTestCase {

  static $modules = ['salesforce'];

  public function setUp() {
    parent::setUp();
    $this->salesforce_id = '1234567890abcde';
    $this->methods = [
      'getConsumerKey',
      'getConsumerSecret',
      'getRefreshToken',
      'getAccessToken',
      'refreshToken',
      'getApiEndPoint',
      'httpRequest',
    ];
    
    $this->httpClient = $this->getMock('\GuzzleHttp\Client');
    $this->configFactory = 
      $this->getMockBuilder('\Drupal\Core\Config\ConfigFactory')
        ->disableOriginalConstructor()
        ->getMock();
    $this->state =
      $this->getMockBuilder('\Drupal\Core\State\State')
        ->disableOriginalConstructor()
        ->getMock();
    $this->cache = $this->getMock('\Drupal\Core\Cache\CacheBackendInterface');
  }

  private function initClient($methods = NULL) {
    if (empty($methods)) {
      $methods = $this->methods;
    }

    $args = [$this->httpClient, $this->configFactory, $this->state, $this->cache];

    $this->client = $this->getMock(RestClient::CLASS, $methods, $args);

    if (in_array('getApiEndPoint', $methods)) {
      $this->client->expects($this->any())
        ->method('getApiEndPoint')
        ->willReturn('https://example.com');
    }
    if (in_array('getAccessToken', $methods)) {
      $this->client->expects($this->any())
        ->method('getAccessToken')
        ->willReturn(TRUE);
    }
  }

  /**
   * @covers ::isAuthorized
   */
  public function testAuthorized() {
    $this->initClient();
    $this->client->expects($this->at(0))
      ->method('getConsumerKey')
      ->willReturn($this->randomMachineName());
    $this->client->expects($this->at(1))
      ->method('getConsumerSecret')
      ->willReturn($this->randomMachineName());
    $this->client->expects($this->at(2))
      ->method('getRefreshToken')
      ->willReturn($this->randomMachineName());
    
    $this->assertTrue($this->client->isAuthorized());

    // Next one will fail because mocks only return for specific invocations.
    $this->assertFalse($this->client->isAuthorized());
  }

  /**
   * @covers ::apiCall
   */
  public function testSimpleApiCall() {
    $this->initClient();
    
    // Test that an apiCall returns a json-decoded value.
    $body = array('foo' => 'bar');
    $response = new GuzzleResponse(200, [], json_encode($body));

    $this->client->expects($this->any())
      ->method('httpRequest')
      ->willReturn($response);

    $result = $this->client->apiCall('');
    $this->assertEquals($result, $body);
  }

  /**
   * @covers ::apiCall
   * @expectedException Exception
   */
  public function testExceptionApiCall() {
    $this->initClient();
    
    // Test that SF client throws an exception for non-200 response 
    $response = new GuzzleResponse(456);

    $this->client->expects($this->any())
      ->method('httpRequest')
      ->willReturn($response);

    $result = $this->client->apiCall('');
  }

  /**
   * @covers ::apiCall
   */
  public function testReauthApiCall() {
    $this->initClient();
    
    // Test that apiCall does auto-re-auth after 401 response
    $response_401 = new GuzzleResponse(401);
    $response_200 = new GuzzleResponse(200);

    // First httpRequest() is position 4.
    // @TODO this is extremely brittle, exposes complexity in underlying client. Refactor this.
    $this->client->expects($this->at(3))
      ->method('httpRequest')
      ->willReturn($response_401);
    $this->client->expects($this->at(4))
      ->method('httpRequest')
      ->willReturn($response_200);

    $result = $this->client->apiCall('');
  }

  
  /**
   * @covers ::objects
   */
  public function testObjects() {
    $this->initClient(array_merge($this->methods, ['apiCall']));

    $objects = [
      'sobjects' => [
        'Test' => [
          'updateable' => TRUE,
        ],
        'NonUpdateable' => [
          'updateable' => FALSE,
        ]
      ],
    ];
    $cache = (object)[
      'created' => time(),
      'data' => $objects,
    ];
    unset($cache->data['sobjects']['NonUpdateable']);

    $this->cache->expects($this->at(0))
      ->method('get')
      ->willReturn($cache);
    $this->cache->expects($this->at(1))
      ->method('get')
      ->willReturn(FALSE);
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($objects);

    // First call, from cache:
    $this->assertEquals($cache->data['sobjects'], $this->client->objects());
    
    // Second call, from apiCall()
    $this->assertEquals($cache->data['sobjects'], $this->client->objects());
  }

  /**
   * @covers ::query
   */
  public function testQuery() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $rawQueryResult = [
      'totalSize' => 1,
      'done' => true,
      'records' => [
        0 => [
          'attributes' => [
            'type' => 'Foo',
            'url' => 'Bar'
          ],
          'Id' => $this->salesforce_id,
        ],
      ],
    ];

    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($rawQueryResult);

    // @TODO this doesn't seem like a very good test.
    $this->assertEquals(new SelectQueryResult($rawQueryResult), $this->client->query(new SelectQuery("")));
  }

  /**
   * @covers ::objectDescribe
   *
   * @expectedException Exception
   */
  public function testObjectDescribe() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $name = $this->randomMachineName();
    // @TODO this is fugly, do we need a refactor on RestResponse?
    $restResponse = new RestResponse(
      new GuzzleResponse('200', [], json_encode([
        'name' => $name,
        'fields' => [
          [
            'name' => $this->randomMachineName(),
            'label' => 'Foo Bar',
            $this->randomMachineName() => $this->randomMachineName(),
            $this->randomMachineName() => [
              $this->randomMachineName() => $this->randomMachineName(),
              $this->randomMachineName() => $this->randomMachineName()
            ],
          ],
          [
            'name' => $this->randomMachineName(),
          ],
        ],
      ]))
    );

    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($restResponse);

    // Test that we hit "apiCall" and get expected result:
    $result = $this->client->objectDescribe($name);
    $expected = new RestResponse_Describe($restResponse);
    $this->assertEquals($expected, $result);

    // Test that cache gets set correctly:
    $this->cache->expects($this->any())
      ->method('get')
      ->willReturn((object)[
        'data' => $expected,
        'created' => time()
      ]);

    // Test that we hit cache when we call again.
    // (Otherwise, we'll blow the "once" condition)
    $this->assertEquals($expected, $this->client->objectDescribe($name));

    // @TODO what happens when we provide a name for non-existent SF table?
    // 404 exception?

    // Test that we throw an exception if name is not provided.
    $this->client->objectDescribe('');
  }

  /**
   * @covers ::objectCreate
   */
  public function testObjectCreate() {

  }

  /**
   * @covers ::objectUpsert
   */
  public function testObjectUpsert() {

  }

  /**
   * @covers ::objectUpdate
   */
  public function testObjectUpdate() {

  }

  /**
   * @covers ::objectRead
   */
  public function testObjectRead() {

  }

  /**
   * @covers ::objectReadbyExternalId
   *
   * @return void
   * @author Aaron Bauman
   */
  public function testObjectReadbyExternalId() {
    
  }

  /**
   * @covers ::objectDelete
   */
  public function testObjectDelete() {

  }

  /**
   * @covers ::getDeleted
   */
  public function getDeleted() {

  }

  /**
   * @covers ::listResources
   */
  public function testListResources() {
    
  }

  /**
   * @covers ::getUpdated
   */
  public function testGetUpdated() {

  }

  /**
   * @covers ::getRecordTypes
   */
  public function testGetRecordTypes() {

  }

  /**
   * @covers ::getRecordTypeIdByDeveloperName
   */
  public function testGetRecordTypeIdByDeveloperName() {

  }

  /**
   * @covers ::getObjectTypeName
   */
  public static function testGetObjectTypeName() {

  }

}
