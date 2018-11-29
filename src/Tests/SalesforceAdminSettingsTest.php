<?php

namespace Drupal\salesforce\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests for salesforce admin settings.
 *
 * @group Salesforce
 */
class SalesforceAdminSettingsTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'salesforce',
    'user',
    'salesforce_test_rest_client',
  ];

  protected $normalUser;
  protected $adminSalesforceUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Admin salesforce user.
    $this->adminSalesforceUser = $this->drupalCreateUser(['administer salesforce', 'authorize salesforce']);
  }

  /**
   * Tests webform admin settings.
   */
  public function testAdminSettings() {
    global $base_url;

    $this->drupalLogin($this->adminSalesforceUser);

    // Salesforce config.
    $config = \Drupal::config('salesforce.settings');
    $this->assertNull($config->get('consumer_key'));
    $this->assertNull($config->get('consumer_secret'));
    $this->assertNull($config->get('login_url'));

    $key = $this->randomMachineName();
    $secret = rand(100000, 10000000);
    $url = 'https://login.salesforce.com';
    $post = [
      'consumer_key' => $key,
      'consumer_secret' => $secret,
      'login_url' => $url,
    ];
    $this->drupalPostForm('admin/config/salesforce/authorize', $post, t('Save configuration'));

    $newurl = parse_url($this->getUrl());

    $query = [];
    parse_str($newurl['query'], $query);

    // Check the redirect URL matches expectations:
    $this->assertEqual($key, $query['client_id']);
    $this->assertEqual('code', $query['response_type']);
    $this->assertEqual(str_replace('http://', 'https://', $base_url) . '/salesforce/oauth_callback', $query['redirect_uri']);

    // Check that our config was updated:
    $config = \Drupal::config('salesforce.settings');
    $this->assertEqual($key, $config->get('consumer_key'));
    $this->assertEqual($secret, $config->get('consumer_secret'));
    $this->assertEqual($url, $config->get('login_url'));

  }

  /**
   * Test that the oauth mechanism appropriately sends a redirect header.
   */
  public function testOauthCallback() {
    $this->drupalLogin($this->adminSalesforceUser);

    $code = $this->randomMachineName();

    // Prevent redirects, and do HEAD only, otherwise we're catching errors. If
    // the oauth callback gets as far as issuing a redirect, then we've
    // succeeded as far as this test is concerned.
    $this->maximumRedirects = 0;
    $this->drupalHead('salesforce/oauth_callback', ['query' => ['code' => $code]]);

    // Confirm that oauth_callback redirected properly. Note that base_url can
    // vary wildly between test environments. Rather than parse this into
    // components, we presume that presence of the expected path fulfills our
    // test expectations.
    $this->assertTrue(strstr($this->drupalGetHeader('location'), 'admin/config/salesforce/authorize'));
  }

}
