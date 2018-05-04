
ABOUT
-----
  This module suite implements a mapping functionality between Salesforce
  objects and Drupal entities. In other words, for each of your supported Drupal
  entities (e.g. node, user, or entities supported by extensions), you can
  assign Salesforce objects that will be created / updated when the entity is
  saved. For each such assignment, you choose which Drupal and Salesforce fields
  should be mapped to one another.

  This suite also includes an API architecture which allows for additional
  modules to be easily plugged in (e.g. for webforms, contact form submits,
  etc).

  For a more detailed description of each component module, see below.


REQUIREMENTS
------------
  1) You need a Salesforce account. Developers can register here:
  http://www.developerforce.com/events/regular/registration.php

  2) You will need to create a remote application/connected app for
  authorization. In Salesforce go to Your Name > Setup > Create > Apps then
  create a new Connected App. Set the callback URL to:
  https://<your site>/salesforce/oauth_callback  (must use SSL)

  Select at least 'Perform requests on your behalf at any time' for OAuth Scope
  as well as the appropriate other scopes for your application.

  Additional information:
  https://help.salesforce.com/help/doc/en/remoteaccess_about.htm

  3) Your site needs to be SSL enabled to authorize the remote application using
  OAUTH.

  4) If using the SOAP API, PHP to have been compiled with SOAP web services and
  OpenSSL support, as per:

  http://php.net/soap
  http://php.net/openssl

  5) Required modules
     Entity API - http://drupal.org/project/entity
     Libraries, only for SOAP API - http://drupal.org/project/libraries


AUTHORIZATION / CONNECTED APP CONFIGURATION
-------------------------------------------
  You can supply your connected app's consumer key, consumer secret, and login
  URL to the Salesforce Authorization form found at
  admin/config/salesforce/authorize.  This information will be stored into
  your site's mutable/exportable configuration and used to authorize your site
  with Salesforce.

  Alternately you can supply or override this configuration using your site's
  settings.php file.  For example, a developer might add the following to
  his/her settings.local.php file to connect his/her development environment to
  a Salesforce sandbox:

  $config['salesforce.settings']['consumer_key'] = 'foo';
  $config['salesforce.settings']['consumer_secret'] = 'bar';
  $config['salesforce.settings']['login_url'] = 'https://test.salesforce.com';

  Supplying your connected app configuration exclusively by way of settings.php
  has additional benefits in terms of security and flexibility:

   - Keeps this sensitive configuration out of the database (and out of version
   control if the site's configuration is tracked in code).
   - Allows for easily substituting environment-specific overrides for these
   values.  If you track your site's settings.php file in version control, you
   can create a settings.local.php file for each of your Salesforce-conencted
   environments with the connected app configuration appropriate for the
   specific environment (see default.settings.php for the code to enable this
   core feature).
   - Reduces the likelihood of a development or staging environment accidentally
   connecting to your production Salesforce instance.

  If you choose the settings.php route, you'll need to supply dummy-values to
  the form at admin/config/salesforce/authorize.  Rest assured the real values
  you've specified via settings.php will be used to establish the connection to
  Salesforce, even though you cannot see them in the configuration form.


MODULES:
--------

  Salesforce (salesforce):
    OAUTH2 authorization and wrapper around the Salesforce REST API.

  Salesforce Mapping (salesforce_mapping)
    Map Drupal entities to Salesforce fields, including field level mapping.

  Salesforce Push (salesforce_push):
    Push Drupal entity updates into Salesforce.

  Salesforce Pull (salesforce_pull):
    Pull Salesforce object updates into Drupal.

  Salesforce Soap (salesforce_soap):
    Lightweight wrapper around the SOAP API, using the OAUTH access token, to
    fill in functional gaps missing in the REST API. Requires the Salesforce PHP
    Toolkit.
