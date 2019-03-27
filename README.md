### ABOUT

This module suite implements a mapping functionality between Salesforce objects and Drupal entities. In other words, for each of your supported Drupal entities (e.g. node, user, or entities supported by extensions), you can assign Salesforce objects that will be created / updated when the entity is saved. For each such assignment, you choose which Drupal and Salesforce fields should be mapped to one another.

This suite also includes an API architecture which allows for additional modules to be easily plugged in (e.g. for webforms, contact form submits, etc).

  For a more detailed description of each component module, see below.


### REQUIREMENTS

1) You need a Salesforce account. Developers can register here: http://www.developerforce.com/events/regular/registration.php

2) You will need to create a remote application/connected app for authorization. In Salesforce go to Your Name > Setup > Create > Apps then create a new Connected App. Set the callback URL to: https://<your site>/salesforce/oauth_callback  (must use SSL)

   Select at least 'Perform requests on your behalf at any time' for OAuth Scope as well as the appropriate other scopes for your application.
 
   #### Additional information:
   https://help.salesforce.com/help/doc/en/remoteaccess_about.htm

  3) Your site needs to be SSL enabled to authorize the remote application using OAUTH.

  4) If using the SOAP API, PHP to have been compiled with SOAP web services and OpenSSL support, as per: 
     - http://php.net/soap 
     - http://php.net/openssl

  5) Required modules
     - Entity API - http://drupal.org/project/entity
     - Libraries, only for SOAP API - http://drupal.org/project/libraries


### AUTHORIZATION / CONNECTED APP CONFIGURATION
You can supply your connected app's consumer key, consumer secret, and login URL to the Salesforce Authorization form found at admin/config/salesforce/authorize.  This information will be stored into your site's mutable/exportable configuration and used to authorize your site with Salesforce.

Alternately you can supply or override this configuration using your site's settings.php file.  For example, a developer might add the following to his/her settings.local.php file to connect his/her development environment to a Salesforce sandbox:

  ```php
  $config['salesforce.salesforce_auth.PROVIDER_MACHINE_NAME']['provider_settings']['consumer_key'] = 'foo';
  $config['salesforce.salesforce_auth.PROVIDER_MACHINE_NAME']['provider_settings']['consumer_secret'] = 'bar';
  $config['salesforce.salesforce_auth.PROVIDER_MACHINE_NAME']['provider_settings']['login_url'] = 'https://test.salesforce.com';
  ```

Supplying your connected app configuration exclusively by way of settings.php has additional benefits in terms of security and flexibility:

   - Keeps this sensitive configuration out of the database (and out of version control if the site's configuration is tracked in code).
   - Allows for easily substituting environment-specific overrides for these values.  If you track your site's settings.php file in version control, you can create a settings.local.php file for each of your Salesforce-conencted environments with the connected app configuration appropriate for the specific environment (see default.settings.php for the code to enable this core feature).
   - Reduces the likelihood of a development or staging environment accidentally connecting to your production Salesforce instance.

If you choose the settings.php route, you'll need to supply dummy-values to the form at admin/config/salesforce/authorize.  Rest assured the real values you've specified via settings.php will be used to establish the connection to Salesforce, even though you cannot see them in the configuration form.

### JWT AUTHORIZATION  / CONNECTED APP CONFIGURATION CHANGES
In 8.x-4.0, support for JWT OAuth is added to provide a more robust and secure connection mechanism over the now deprecated encrypt module. All existing installations are encouraged to implement JWT authentication.

To add use JWTs for auth:
1) Create a self-signed x509 certificate and private key pair.

2) In Salesforce, edit an existing remote application/connected app or add a new one. Follow the exisiting directions under REQUIREMENTS above, with the following differences:
   - Under "API (Enable OAuth Settings)" section when editing, check the "Use digital signature" checkbox and then upload the certificate from the cert pair generated in step 1. Make sure to save to upload the key file.
   - Under the app management screen (the Manage link in the app list), click on the "Edit policies" button in the "Connected App Detail" section. In the "OAtuh policies section", select the "Admin approved users are pre-authorized" option for "Permitted Users". Save your changes.
   - On the manage screen, click on the "Manage Profiles" button in the "Profiles" section. Check off the appropriate profiles to allow access to. The "System Administrator" profile should be sufficient for most usa cases. It should match the profile for the account used in Drupal (see below).

3) In Drupal, go to Admin > Configuration > System > Keys and add a key. The key type will be "Authentication" and the key provider will be "File".

4) Place the private key from the cert pair created above into an appropriate location on the web server. Best practice is a directory above DRUPAL_ROOT, where it cannot be served by the webserver. Enter the file name and location in the file location field.

5) With the key set up, go to Admin > Configuration > Salesforce > Salesforce Authentication and add an auth provider.

6) Add a label and then choose the "Salesforce JWT OAuth" option from "Auth provider". Drupal will nw show the needed form.

7) Add the consumer secret for the app, found in Salesforce.

8) Add the account email for the Salesforce user Drupal will be using to authorize.

9) Add the login URL to Salesforce.

10) Select the key option you set up above from the "Private key" field.

11) Save to save the config and validate the settings.

12) From the auth providers listing, select the newly added provider config
  as the default provider and save.

#### Gotchas:
   - Make sure to use the correct Login URL based on live or sandbox Salesforce instance use.
   - Make sure they proper keys are in their respective locations in Drupal and in Salesforce.
   - Make sure the login user is the corrected email, taking into account sandbox login changes.
   - Make sure the consumer key is the correct one in Drupal.
   - Make sure the profiles checked off in Salesforce match the user used to login in Drupal.

setting.php file salesforce config changes:
```php
$config['salesforce.salesforce_auth.PROVIDER_MACHINE_NAME']['provider_settings']['consumer_key'] = 'foo';
$config['salesforce.salesforce_auth.PROVIDER_MACHINE_NAME']['provider_settings']['login_user'] = 'bar@example.org';
$config['salesforce.salesforce_auth.PROVIDER_MACHINE_NAME']['provider_settings']['login_url'] = 'https://test.salesforce.com';
$config['salesforce.salesforce_auth.PROVIDER_MACHINE_NAME']['provider_settings']['encrypt_key'] = 'KEY_MACHONE_NAME';
```

### MODULES

  **Salesforce (salesforce):**\
    Authorization provider and wrapper around the Salesforce REST API.

  **Salesforce Examples (salesforce_example):**\
    Example code samples demonstrating hooks, alters, and events.

  **Salesforce Mapping (salesforce_mapping):**\
    Map Drupal entities to Salesforce fields, including field level mapping.

  **Salesforce Push (salesforce_push):**\
    Push Drupal entity updates into Salesforce.

  **Salesforce Pull (salesforce_pull):**\
    Pull Salesforce object updates into Drupal.

  **Salesforce Logger (salesforce_logger):**\
    Consolidated logging for Salesforce Log events.

  **Salesforce JWT Auth Provider (salesforce_jwt):**\
    Provides key-based Salesforce authentication.

  **Salesforce OAuth user-agent Provider (salesforce_oauth):**\
    Provides user-agent-based Salesforce OAuth authentication.

  **Salesforce Soap (salesforce_soap):**\
    Lightweight wrapper around the SOAP API, using the OAUTH access token, to fill in functional gaps missing in the REST API. Requires the Salesforce PHP Toolkit.
