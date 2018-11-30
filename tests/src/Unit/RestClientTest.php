<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Rest\RestResponseDescribe;
use Drupal\salesforce\Rest\RestResponseResources;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SelectQuery;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Datetime\TimeInterface;

/**
 * @coversDefaultClass \Drupal\salesforce\Rest\RestClient
 * @group salesforce
 */
class RestClientTest extends UnitTestCase {

  protected static $modules = ['salesforce'];

  /**
   * Set up for each test.
   */
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
    $this->json = $this->getMock(Json::CLASS);
    $this->time = $this->getMock(TimeInterface::CLASS);
  }

  /**
   * @covers ::__construct
   */
  private function initClient($methods = NULL) {
    if (empty($methods)) {
      $methods = $this->methods;
    }

    $args = [
      $this->httpClient,
      $this->configFactory,
      $this->state,
      $this->cache,
      $this->json,
      $this->time,
    ];

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
    $body = ['foo' => 'bar'];
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

    // Test that SF client throws an exception for non-200 response.
    $response = new GuzzleResponse(456);

    $this->client->expects($this->any())
      ->method('httpRequest')
      ->willReturn($response);

    $this->client->apiCall('');
  }

  /**
   * @covers ::apiCall
   */
  public function testReauthApiCall() {
    $this->initClient();

    // Test that apiCall does auto-re-auth after 401 response.
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

    $this->client->apiCall('');
  }

  /**
   * @covers ::objects
   */
  public function testObjects() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $objects = [
      'sobjects' => [
        'Test' => [
          'name' => 'Test',
          'updateable' => TRUE,
        ],
        'NonUpdateable' => [
          'name' => 'NonUpdateable',
          'updateable' => FALSE,
        ],
      ],
    ];
    $cache = (object) [
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
      'done' => TRUE,
      'records' => [
        0 => [
          'attributes' => [
            'type' => 'Foo',
            'url' => 'Bar',
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
              $this->randomMachineName() => $this->randomMachineName(),
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
    $expected = new RestResponseDescribe($restResponse);
    $this->assertEquals($expected, $result);

    // Test that cache gets set correctly:
    $this->cache->expects($this->any())
      ->method('get')
      ->willReturn((object) [
        'data' => $expected,
        'created' => time(),
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
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $restResponse = new RestResponse(
      new GuzzleResponse('200', [], json_encode([
        'id' => $this->salesforce_id,
      ]))
      );

    $sfid = new SFID($this->salesforce_id);
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($restResponse);

    // @TODO this doesn't seem like a very good test.
    $this->assertEquals($sfid, $this->client->objectCreate('', []));

  }

  /**
   * @covers ::objectUpsert
   */
  public function testObjectUpsert() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
      'objectReadbyExternalId',
    ]));
    $createResponse = new RestResponse(
      new GuzzleResponse('200', [], json_encode([
        'id' => $this->salesforce_id,
      ])));

    $updateResponse = new RestResponse(new GuzzleResponse('204', [], ''));

    $sfid = new SFID($this->salesforce_id);
    $sobject = new SObject([
      'id' => $this->salesforce_id,
      'attributes' => ['type' => 'dummy'],
    ]);
    $this->client->expects($this->at(0))
      ->method('apiCall')
      ->willReturn($createResponse);

    $this->client->expects($this->at(1))
      ->method('apiCall')
      ->willReturn($updateResponse);

    $this->client->expects($this->once())
      ->method('objectReadbyExternalId')
      ->willReturn($sobject);

    // Ensure both upsert-create and upsert-update return the same value.
    $this->assertEquals($sfid, $this->client->objectUpsert('', '', '', []));
    $this->assertEquals($sfid, $this->client->objectUpsert('', '', '', []));
  }

  /**
   * @covers ::objectUpdate
   */
  public function testObjectUpdate() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn(NULL);
    $this->assertNull($this->client->objectUpdate('', '', []));
  }

  /**
   * @covers ::objectRead
   */
  public function testObjectRead() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $rawData = [
      'id' => $this->salesforce_id,
      'attributes' => ['type' => 'dummy'],
    ];
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($rawData);
    $this->assertEquals(new SObject($rawData), $this->client->objectRead('', ''));
  }

  /**
   * @covers ::objectReadbyExternalId
   */
  public function testObjectReadbyExternalId() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $rawData = [
      'id' => $this->salesforce_id,
      'attributes' => ['type' => 'dummy'],
    ];
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($rawData);
    $this->assertEquals(new SObject($rawData), $this->client->objectReadByExternalId('', '', ''));
  }

  /**
   * @covers ::objectDelete
   *
   * @expectedException \GuzzleHttp\Exception\RequestException
   */
  public function testObjectDelete() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));

    // 3 tests for objectDelete:
    // 1. test that a successful delete returns null
    // 2. test that a 404 response gets eaten
    // 3. test that any other error response percolates.
    $this->client->expects($this->exactly(3))
      ->method('apiCall');

    $this->client->expects($this->at(0))
      ->method('apiCall')
      ->willReturn(NULL);

    $exception404 = new RequestException('', new GuzzleRequest('', ''), new GuzzleResponse(404, [], ''));
    $this->client->expects($this->at(1))
      ->method('apiCall')
      ->will($this->throwException($exception404));

    // Test the objectDelete throws any other exception.
    $exceptionOther = new RequestException('', new GuzzleRequest('', ''), new GuzzleResponse(456, [], ''));
    $this->client->expects($this->at(2))
      ->method('apiCall')
      ->will($this->throwException($exceptionOther));

    $this->assertNull($this->client->objectDelete('', ''));
    $this->assertNull($this->client->objectDelete('', ''));
    $this->client->objectDelete('', '');
  }

  /**
   * @covers ::listResources
   */
  public function testListResources() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $restResponse = new RestResponse(new GuzzleResponse('204', [], json_encode([
      'foo' => 'bar',
      'zee' => 'bang',
    ])));
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($restResponse);
    $this->assertEquals(new RestResponseResources($restResponse), $this->client->listResources());
  }

  /**
   * @covers ::getRecordTypes
   *
   * @expectedException Exception
   */
  public function testGetRecordTypes() {
    $this->initClient(array_merge($this->methods, ['query']));
    $sObjectType = $this->randomMachineName();
    $developerName = $this->randomMachineName();

    $rawQueryResult = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [
        0 => [
          'attributes' => [
            'type' => 'Foo',
            'url' => 'Bar',
          ],
          'SobjectType' => $sObjectType,
          'DeveloperName' => $developerName,
          'Id' => $this->salesforce_id,
        ],
      ],
    ];
    $recordTypes = [
      $sObjectType => [
        $developerName =>
        new SObject($rawQueryResult['records'][0]),
      ],
    ];
    $cache = (object) [
      'created' => time(),
      'data' => $recordTypes,
    ];

    $this->cache->expects($this->at(1))
      ->method('get')
      ->willReturn(FALSE);
    $this->cache->expects($this->at(2))
      ->method('get')
      ->willReturn($cache);
    $this->cache->expects($this->at(3))
      ->method('get')
      ->willReturn($cache);
    $this->client->expects($this->once())
      ->method('query')
      ->willReturn(new SelectQueryResult($rawQueryResult));

    $this->assertEquals($recordTypes, $this->client->getRecordTypes());

    $this->assertEquals($recordTypes[$sObjectType], $this->client->getRecordTypes($sObjectType));

    $this->client->getRecordTypes('fail');
  }

}
