<?php

namespace Drupal\Tests\salesforce_oauth\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Test OAuth.
 *
 * @group salesforce_oauth
 */
class SalesforceOAuthTest extends WebDriverTestBase {

  /**
   * Default theme required for D9.
   *
   * @var string
   */
  protected $defaultTheme  = 'stark';

  /**
   * Required modules.
   *
   * @var array
   */
  public static $modules = [
    'key',
    'typed_data',
    'dynamic_entity_reference',
    'salesforce',
    'salesforce_test_rest_client',
    'salesforce_oauth'
  ];

  /**
   * Admin user to test form.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser(['authorize salesforce']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test adding an oauth provider plugin.
   */
  public function testOAuth() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/config/salesforce/authorize/add');
    $labelField = $page->findField('label');
    $label = $this->randomString();
    $labelField->setValue($label);
    $page->findField('provider')->setValue('oauth');
    $assert_session->assertWaitOnAjaxRequest();
    $edit = [
      'provider_settings[consumer_key]' => 'foo',
      'provider_settings[consumer_secret]' => 'bar',
      'provider_settings[login_url]' => 'https://login.salesforce.com',
    ];
    foreach ($edit as $key => $value) {
      $assert_session->fieldExists($key);
      $page->fillField($key, $value);
    }
    $this->createScreenshot(\Drupal::root() . '/sites/default/files/simpletest/sfoauth-1.png');
    $page->pressButton('Save');

    // Weird behavior from testbot: machine name field doesn't seem to work
    // as expected. Machine name field doesn't appear until after clicking
    // "save", so we fill it and have to click save again. IDKWTF.
    if ($page->findField('id')) {
      $page->fillField('id', strtolower($this->randomMachineName()));
      $this->createScreenshot(\Drupal::root() . '/sites/default/files/simpletest/sfoauth-2.png');
      $page->pressButton('Save');
    }
    $assert_session->assertWaitOnAjaxRequest();
    $this->createScreenshot(\Drupal::root() . '/sites/default/files/simpletest/sfoauth-3.png');
    // We will have been redirected to a failed salesforce oauth page.
    $assert_session->pageTextContainsOnce('error=invalid_client_id');
  }

  /**
   * Test the oauth provider plugin callback.
   */
  public function testOAuthCallback() {
    // @todo
  }

}
