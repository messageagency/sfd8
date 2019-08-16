<?php

namespace Drupal\Tests\salesforce_mapping\Functional;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\Node;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\PushParams;
use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group salesforce_mapping
 */
class PushParamsTest extends BrowserTestBase {

  public static $modules = ['typed_data', 'dynamic_entity_reference', 'salesforce_mapping', 'salesforce_mapping_test'];

  /**
   * Tests something.
   */
  public function testSomething() {
    $mapping = SalesforceMapping::load('test_mapping');

    $date = date(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, \Drupal::time()->getRequestTime());
    // Entity 1 is the target reference.
    $entity1 = Node::create([
        'type' => 'salesforce_mapping_test_content',
        'title' => 'Test Example',
      ]
    );
    $entity1->save();

    // Mapped Object to be used for RelatedIDs push params property.
    $mappedObject = MappedObject::create([
      'drupal_entity' => $entity1,
      'salesforce_mapping' => $mapping,
      'salesforce_id' => '0123456789ABCDEFGH',
      'salesforce_link' => NULL,
    ]);
    $mappedObject->save();

    // Entity 2 to be mapped to Salesforce.
    $entity2 = Node::create([
      'type' => 'salesforce_mapping_test_content',
      'title' => 'Test Example 2',
      'field_salesforce_test_bool' => 1,
      'field_salesforce_test_date' => $date,
      'field_salesforce_test_email' => 'test2@example.com',
      'field_salesforce_test_link' => 'https://example.com',
      'field_salesforce_test_reference' => $entity1,
    ]);
    $entity2->save();

    // Create a PushParams and assert it's created as we expect.
    $pushParams = new PushParams($mapping, $entity2);
    $expected = [
      'FirstName' => 'SALESFORCE TEST',
      'Email' => 'test2@example.com',
      'Birthdate' => date('c', \Drupal::time()->getRequestTime()),
      'd5__Do_Not_Mail__c' => TRUE,
      'ReportsToId' => '0123456789ABCDEFGH',
      'RecordTypeId' => '012i0000001B15mAAC',
      'Description' => 'https://example.com',
    ];
    $actual = $pushParams->getParams();
    $this->assertEquals(ksort($expected), ksort($actual));
  }

}
