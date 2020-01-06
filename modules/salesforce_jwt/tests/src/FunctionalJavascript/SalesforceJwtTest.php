<?php

namespace Drupal\Tests\salesforce_jwt\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\key\Entity\Key;
use Drupal\Tests\key\Functional\KeyTestTrait;

/**
 * Test JWT Auth.
 *
 * @group salesforce_jwt
 */
class SalesforceJwtTest extends WebDriverTestBase {

  /**
   * Default theme required for D9.
   *
   * @var string
   */
  protected $defaultTheme  = 'stark';

  use KeyTestTrait;

  /**
   * Modules.
   *
   * @var array
   */
  public static $modules = [
    'key',
    'typed_data',
    'dynamic_entity_reference',
    'salesforce',
    'salesforce_test_rest_client',
    'salesforce_jwt',
  ];

  /**
   * Admin user to test form.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Id of shared cert key.
   */
  const KEY_ID = 'salesforce_jwt_test_key';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser(['authorize salesforce']);
    $this->drupalLogin($this->adminUser);
    $this->createTestKey(self::KEY_ID, 'authentication', 'file');
    Key::load(self::KEY_ID)
      ->set('key_provider_settings', [
        'file_location' => __DIR__ . '/testKey.pem',
        'strip_line_breaks' => FALSE,
      ])->save();
  }

  /**
   * Test adding a jwt provider plugin.
   */
  public function testJwtAuth() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/config/salesforce/authorize/add');
    $labelField = $page->findField('label');
    $label = $this->randomString();
    $labelField->setValue($label);
    $page->findField('provider')->setValue('jwt');
    $assert_session->assertWaitOnAjaxRequest();
    $edit = [
      'provider_settings[consumer_key]' => 'foo',
      'provider_settings[login_user]' => 'bar',
      'provider_settings[login_url]' => 'https://login.salesforce.com',
      'provider_settings[encrypt_key]' => self::KEY_ID,
    ];
    foreach ($edit as $key => $value) {
      $assert_session->fieldExists($key);
      $page->fillField($key, $value);
    }
    $this->createScreenshot(\Drupal::root() . '/sites/default/files/simpletest/sfjwt-1.png');
    $page->pressButton('Save');

    // Weird behavior from testbot: machine name field doesn't seem to work
    // as expected. Machine name field doesn't appear until after clicking
    // "save", so we fill it and have to click save again. IDKWTF.
    if ($page->findField('id')) {
      $page->fillField('id', strtolower($this->randomMachineName()));
      $this->createScreenshot(\Drupal::root() . '/sites/default/files/simpletest/sfjwt-2.png');
      $page->pressButton('Save');
    }
    $assert_session->assertWaitOnAjaxRequest();
    $this->createScreenshot(\Drupal::root() . '/sites/default/files/simpletest/sfjwt-3.png');
    $assert_session->addressEquals('admin/config/salesforce/authorize/list');
    $assert_session->pageTextContainsOnce($label);
    $assert_session->pageTextContainsOnce('Authorized');
    $assert_session->pageTextContainsOnce('Salesforce JWT OAuth');
  }

}
