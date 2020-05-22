<?php

namespace Drupal\Tests\salesforce\Functional;

use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test salesforce_requirements().
 *
 * @group salesforce
 */
class StatusPageTest extends BrowserTestBase {

  use StringTranslationTrait;

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
  public static $modules = ['salesforce', 'salesforce_test_rest_client'];

  /**
   * Auth provider manager service.
   *
   * @var \Drupal\salesforce\Tests\TestSalesforceAuthProviderPluginManager
   */
  protected $authMan;

  /**
   * Token service.
   *
   * @var \OAuth\OAuth2\Token\TokenInterface
   */
  protected $authToken;

  /**
   * Auth provider.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderInterface
   */
  protected $authProvider;

  /**
   * Auth config.
   *
   * @var \Drupal\salesforce\Entity\SalesforceAuthConfig
   */
  protected $authConfig;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();
    $this->authMan = \Drupal::service('plugin.manager.salesforce.auth_providers');
    $file = __DIR__ . "/../../../salesforce.install";
    require_once $file;
  }

  /**
   * Test implementation of salesforce_requirements().
   */
  public function testRequirementsHook() {
    // For install and update, requirements hook should be empty return value.
    $this->assertEquals([], salesforce_requirements("install"));
    $this->assertEquals([], salesforce_requirements("update"));
  }

  /**
   * Test requirements when no providers are available.
   */
  public function testAuthProviderRequirementsNoProviders() {
    $this->authMan->setHasProviders(FALSE);
    $requirements = salesforce_get_auth_provider_requirements();
    $this->assertEquals(REQUIREMENT_ERROR, $requirements['severity']);
    $this->assertEquals($this->t('No auth providers have been created. Please <a href="@href">create an auth provider</a> to connect to Salesforce.', ['@href' => Url::fromRoute('entity.salesforce_auth.add_form')->toString()]), $requirements['description']);
  }

  /**
   * Test requirements with providers, but no config.
   */
  public function testAuthProviderRequirementsNoConfig() {
    $this->authMan->setHasProviders(TRUE);
    $this->authMan->setHasConfig(FALSE);
    $requirements = salesforce_get_auth_provider_requirements();
    $this->assertEquals(REQUIREMENT_ERROR, $requirements['severity']);
    $this->assertEquals($this->t('Default auth provider has not been set. Please <a href="@href">choose an auth provider</a> to connect to Salesforce.', ['@href' => Url::fromRoute('salesforce.auth_config')->toString()]), $requirements['description']);
  }

  /**
   * Test requirements with providers and config, but no token.
   */
  public function testAuthProviderRequirementsNoToken() {
    $this->authMan->setHasProviders(TRUE);
    $this->authMan->setHasConfig(TRUE);
    $this->authMan->setHasToken(FALSE);
    $requirements = salesforce_get_auth_provider_requirements();
    $this->assertEquals(REQUIREMENT_ERROR, $requirements['severity']);
    $this->assertEquals($this->t('Salesforce authentication failed. Please <a href="@href">check your auth provider settings</a> to connect to Salesforce.', ['@href' => Url::fromRoute('entity.salesforce_auth.edit_form', ['salesforce_auth' => $this->authMan->getConfig()->id()])->toString()]), $requirements['description']);
  }

  /**
   * Test requirements when everything is in place.
   */
  public function testAuthProviderRequirementsOk() {
    $this->authMan->setHasProviders(TRUE);
    $this->authMan->setHasConfig(TRUE);
    $this->authMan->setHasToken(TRUE);
    $requirements = salesforce_get_auth_provider_requirements();
    $this->assertEquals(REQUIREMENT_OK, $requirements['severity']);
    $this->assertArrayNotHasKey('description', $requirements);
  }

  /**
   * Need to do.
   */
  public function testTlsRequirements() {
    // @TODO write me.
  }

  /**
   * Need to do.
   */
  public function testUsageRequirements() {
    // @TODO write me.
  }

}
