Salesforce Encrypt [deprecated](https://www.drupal.org/node/3034230) in favor ot OAuth plugin providers including JWT Oauth support.

### Contributors (11)

acrosman, aaronbauman, chrisolof, gcb, mariacha1, tjhellmann, jonnyeom, asherry, Selva.M, cwcorrigan, dkosbob

### Changelog

**Issues**: 16 issues resolved.

Changes since [8.x-3.1](https://www.drupal.org/project/sfd8/releases/8.x-3.1):

####

* https://www.drupal.org/project/salesforce/issues/2899460 [#49](https://www.drupal.org/node/49)

#### Bug

* [#3029568](https://www.drupal.org/node/3029568) by [chrisolof](https://www.drupal.org/u/chrisolof), [aaronbauman](https://www.drupal.org/u/aaronbauman): Data too long for column 'salesforce_mapping'
* [#3030592](https://www.drupal.org/node/3030592) by [chrisolof](https://www.drupal.org/u/chrisolof), [aaronbauman](https://www.drupal.org/u/aaronbauman): Disconnected SOAP client service can stop update.php from running
* [#3027875](https://www.drupal.org/node/3027875) by [acrosman](https://www.drupal.org/u/acrosman), [aaronbauman](https://www.drupal.org/u/aaronbauman), [chrisolof](https://www.drupal.org/u/chrisolof): escapeSoqlValue not handling all strings correctly
* [#3027846](https://www.drupal.org/node/3027846) by [tjhellmann](https://www.drupal.org/u/tjhellmann), [aaronbauman](https://www.drupal.org/u/aaronbauman): PHP Warning from array_key_exists() in /src/SelectQuery.php
* [#3024550](https://www.drupal.org/node/3024550) - better exception handling on mapping forms
* [#3024372](https://www.drupal.org/node/3024372) by [acrosman](https://www.drupal.org/u/acrosman): Managed Apps can generate alphanumeric consumer secret
* [#3018788](https://www.drupal.org/node/3018788) by [dkosbob](https://www.drupal.org/u/dkosbob): fix logic in getRecordTypeIdByDeveloperName()

#### Feature

* [#3028158](https://www.drupal.org/node/3028158) by [acrosman](https://www.drupal.org/u/acrosman), [aaronbauman](https://www.drupal.org/u/aaronbauman): Enabling typed data for extended properties
* [#2900041](https://www.drupal.org/node/2900041) by [aaronbauman](https://www.drupal.org/u/aaronbauman), [gcb](https://www.drupal.org/u/gcb), [mariacha1](https://www.drupal.org/u/mariacha1): Validate salesforce_endpoint when accessing salesforce_identity
* [#2866367](https://www.drupal.org/node/2866367) by [acrosman](https://www.drupal.org/u/acrosman): Sanitize SOQL queries

#### Plan

* [#3034228](https://www.drupal.org/node/3034228) - Remove salesforce_encrypt module

#### Task

* Restore [#2975835](https://www.drupal.org/node/2975835) from 3.x
* [#2899460](https://www.drupal.org/node/2899460) by [jonnyeom](https://www.drupal.org/u/jonnyeom), [aaronbauman](https://www.drupal.org/u/aaronbauman), [asherry](https://www.drupal.org/u/asherry), [Selva.M](https://www.drupal.org/u/selva.m), [cwcorrigan](https://www.drupal.org/u/cwcorrigan): Handling of field properties
* [#3018851](https://www.drupal.org/node/3018851) by [cwcorrigan](https://www.drupal.org/u/cwcorrigan): Move mapping-related methods out of salesforce core module, and into salesforce_mapping module
* [#2799421](https://www.drupal.org/node/2799421) by [chrisolof](https://www.drupal.org/u/chrisolof): add SOAP Client via salesforce_soap sub-module
