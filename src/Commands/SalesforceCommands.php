<?php

namespace Drupal\salesforce\Commands;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\salesforce\Rest\RestException;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryRaw;
use Drupal\salesforce\SFID;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class SalesforceCommands extends SalesforceCommandsBase {

  /**
   * Display information about the current REST API version.
   *
   * @command salesforce:rest-version
   * @aliases sfrv,sf-rest-version
   * @field-labels
   *   label: Label
   *   url: Path
   *   version: Version
   *   login_url: Login URL
   *   latest: Latest Version?
   * @default-fields label,url,version,login_url,latest
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   *   The version info.
   */
  public function restVersion() {
    $version_id = $this->authMan->getProvider()->getApiVersion();
    $versions = $this->client->getVersions();
    $version = $versions[$version_id];
    $latest = array_pop($versions);
    foreach ($version as $key => $value) {
      $rows[$key] = $value;
    }
    $rows['login_url'] = $this->authMan->getCredentials()->getLoginUrl();
    $rows['latest'] = strcmp($version_id, $latest['version']) ? $latest['version'] : 'Yes';
    return new PropertyList($rows);
  }

  /**
   * List the objects that are available in your organization.
   *
   * @command salesforce:list-objects
   * @aliases sflo,sf-list-objects
   * @field-labels
   *   activateable: Activateable
   *   createable: Createable
   *   custom: Custom
   *   customSetting: CustomSetting
   *   deletable: Deletable
   *   deprecatedAndHidden: DeprecatedAndHidden
   *   feedEnabled: FeedEnabled
   *   hasSubtypes: HasSubtypes
   *   isSubtype: IsSubtype
   *   keyPrefix: KeyPrefix
   *   label: Label
   *   labelPlural: LabelPlural
   *   layoutable: Layoutable
   *   mergeable: Mergeable
   *   mruEnabled: MruEnabled
   *   name: Name
   *   queryable: Queryable
   *   replicateable: Replicateable
   *   retrieveable: Retrieveable
   *   searchable: Searchable
   *   triggerable: Triggerable
   *   undeletable: Undeletable
   *   updateable: Updateable
   *   urls: URLs
   * @default-fields name,label,labelPlural
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The objects.
   *
   * @throws \Exception
   */
  public function listObjects() {
    if ($objects = $this->client->objects()) {
      foreach ($objects as $name => $object) {
        $rows[$name] = $object;
        $rows[$name]['urls'] = new TableCell(implode("\n", $rows[$name]['urls']) . "\n");
      }
      return new RowsOfFields($rows);
    }
    throw new \Exception('Could not load any information about available objects.');
  }

  /**
   * Wrap ::interactObject for describe-object.
   *
   * @hook interact salesforce:describe-object
   */
  public function interactDescribeObject(Input $input, Output $output) {
    return $this->interactObject($input, $output);
  }

  /**
   * Wrap ::interactObject for describe-fields.
   *
   * @hook interact salesforce:describe-fields
   */
  public function interactDescribeFields(Input $input, Output $output) {
    return $this->interactObject($input, $output);
  }

  /**
   * Wrap ::interactObject for describe-metadata.
   *
   * @hook interact salesforce:describe-metadata
   */
  public function interactDescribeMetadata(Input $input, Output $output) {
    return $this->interactObject($input, $output);
  }

  /**
   * Wrap ::interactObject for describe-record-types.
   *
   * @hook interact salesforce:describe-record-types
   */
  public function interactDescribeRecordTypes(Input $input, Output $output) {
    return $this->interactObject($input, $output);
  }

  /**
   * Wrap ::interactObject for dump-object.
   *
   * @hook interact salesforce:dump-object
   */
  public function interactDumpObject(Input $input, Output $output) {
    return $this->interactObject($input, $output);
  }

  /**
   * Retrieve all the metadata for an object, including fields.
   *
   * @param string $object
   *   The object name in Salesforce.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option output
   *   Specify an output type.
   *   Options are:
   *     info: (default) Display metadata about an object
   *     fields: Display information about fields that are part of the object
   *     field: Display information about a specific field of an object
   *     raw: Display the complete, raw describe response.
   * @option field
   *   For "field" output type, specify a fieldname.
   * @usage drush sfdo Contact
   *   Show metadata about Contact SObject type.
   * @usage drush sfdo Contact --output=fields
   *   Show addtional metadata about Contact fields.
   * @usage drush sfdo Contact --output=field --field=Email
   *   Show full metadata about Contact.Email field.
   * @usage drush sfdo Contact --output=raw
   *   Display the full metadata for Contact SObject type.
   *
   * @command salesforce:describe-object-deprecated
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|null
   *   Describe result.
   *
   * @throws \Exception
   */
  public function describeObject($object, array $options = [
    'output' => NULL,
    'field' => NULL,
  ]) {
    return $this->describeFields($object);
  }

  /**
   * Dump the raw describe response for given object.
   *
   * @todo create a proper StructuredData return value for this.
   *
   * @command salesforce:dump-object
   * @aliases sf-dump-object
   */
  public function dumpObject($object) {
    $objectDescription = $this->client->objectDescribe($object);
    if (!is_object($objectDescription)) {
      $this->logger()
        ->error(dt('Could not load data for object !object', ['!object' => $object]));
    }
    $this->output()->writeln(print_r($objectDescription->data, 1));
  }

  /**
   * Retrieve object record types.
   *
   * @param string $object
   *   The object name in Salesforce.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|null
   *   The record types, or null if the object was not found.
   *
   * @throws \Exception
   *
   * @command salesforce:describe-record-types
   * @aliases sfdrt,sf-describe-record-types
   *
   * @field-labels
   *   active: Active
   *   available: Available
   *   defaultRecordTypeMapping: Default
   *   developerName: Developer Name
   *   master: Master
   *   name: Name
   *   recordTypeId: Id
   *   urls: URLs
   *
   * @default-fields name,recordTypeId,developerName,active,available,defaultRecordTypeMapping,master
   */
  public function describeRecordTypes($object) {
    $objectDescription = $this->client->objectDescribe($object);
    if (!is_object($objectDescription)) {
      $this->logger()
        ->error(dt('Could not load data for object !object', ['!object' => $object]));
      return;
    }
    $data = $objectDescription->data['recordTypeInfos'];
    // Return if we cannot load any data.
    $rows = [];
    foreach ($data as $rt) {
      $rt['urls'] = implode("\n", $rt['urls']);
      $rows[$rt['developerName']] = $rt;
    }
    return new RowsOfFields($rows);
  }

  /**
   * Retrieve object metadata.
   *
   * @param string $object
   *   The object name in Salesforce.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList|null
   *   The metadata, or null if object was not found.
   *
   * @throws \Exception
   *
   * @command salesforce:describe-metadata
   * @aliases sfdom,sf-describe-metadata
   *
   * @field-labels
   *   actionOverrides: ActionOverrides
   *   activateable: Activateable
   *   compactLayoutable: CompactLayoutable
   *   createable: Createable
   *   custom: Custom
   *   customSetting: CustomSetting
   *   deletable: Deletable
   *   deprecatedAndHidden: DeprecatedAndHidden
   *   feedEnabled: FeedEnabled
   *   hasSubtypes: HasSubtypes
   *   isSubtype: IsSubtype
   *   keyPrefix: KeyPrefix
   *   label: Label
   *   labelPlural: LabelPlural
   *   layoutable: Layoutable
   *   listviewable: Listviewable
   *   lookupLayoutable: LookupLayoutable
   *   mergeable: Mergeable
   *   mruEnabled: MruEnabled
   *   name: Name
   *   namedLayoutInfos: NamedLayoutInfos
   *   networkScopeFieldName: NetworkScopeFieldName
   *   queryable: Queryable
   *   replicateable: Replicateable
   *   retrieveable: Retrieveable
   *   searchLayoutable: SearchLayoutable
   *   searchable: Searchable
   *   supportedScopes: SupportedScopes
   *   triggerable: Triggerable
   *   undeletable: Undeletable
   *   updateable: Updateable
   *   urls: Urls
   */
  public function describeMetadata($object) {
    $objectDescription = $this->client->objectDescribe($object);
    if (!is_object($objectDescription)) {
      $this->logger()->error(dt('Could not load data for object !object', ['!object' => $object]));
      return;
    }
    $data = $objectDescription->data;
    // Return if we cannot load any data.
    unset($data['fields'], $data['childRelationships'], $data['recordTypeInfos']);
    foreach ($data as $k => &$v) {
      if ($k == 'supportedScopes') {
        array_walk($v, function (&$value, $key) {
          $value = $value['name'] . ' (' . $value['label'] . ')';
        });
      }
      if (is_array($v)) {
        if (empty($v)) {
          $v = '';
        }
        else {
          $v = implode("\n", $v) . "\n";
        }
      }
    }
    return new PropertyList($data);
  }

  /**
   * Retrieve all the metadata for an object.
   *
   * @param string $object
   *   The object name in Salesforce.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|null
   *   The fields, or null if the object was not found.
   *
   * @throws \Exception
   *
   * @command salesforce:describe-fields
   * @aliases salesforce:describe-object,sfdo,sfdf,sf-describe-fields
   * @usage drush sfdo Contact
   *   Show metadata about Contact SObject type.
   *
   * @field-labels
   *   aggregatable: Aggregatable
   *   aiPredictionField: AiPredictionField
   *   autoNumber: AutoNumber
   *   byteLength: ByteLength
   *   calculated: Calculated
   *   calculatedFormula: CalculatedFormula
   *   cascadeDelete: CascadeDelete
   *   caseSensitive: CaseSensitive
   *   compoundFieldName: CompoundFieldName
   *   controllerName: ControllerName
   *   createable: Createable
   *   custom: Custom
   *   defaultValue: DefaultValue
   *   defaultValueFormula: DefaultValueFormula
   *   defaultedOnCreate: DefaultedOnCreate
   *   dependentPicklist: DependentPicklist
   *   deprecatedAndHidden: DeprecatedAndHidden
   *   digits: Digits
   *   displayLocationInDecimal: DisplayLocationInDecimal
   *   encrypted: Encrypted
   *   externalId: ExternalId
   *   extraTypeInfo: ExtraTypeInfo
   *   filterable: Filterable
   *   filteredLookupInfo: FilteredLookupInfo
   *   formulaTreatNullNumberAsZero: FormulaTreatNullNumberAsZero
   *   groupable: Groupable
   *   highScaleNumber: HighScaleNumber
   *   htmlFormatted: HtmlFormatted
   *   idLookup: IdLookup
   *   inlineHelpText: InlineHelpText
   *   label: Label
   *   length: Length
   *   mask: Mask
   *   maskType: MaskType
   *   name: Name
   *   nameField: NameField
   *   namePointing: NamePointing
   *   nillable: Nillable
   *   permissionable: Permissionable
   *   picklistValues: PicklistValues
   *   polymorphicForeignKey: PolymorphicForeignKey
   *   precision: Precision
   *   queryByDistance: QueryByDistance
   *   referenceTargetField: ReferenceTargetField
   *   referenceTo: ReferenceTo
   *   relationshipName: RelationshipName
   *   relationshipOrder: RelationshipOrder
   *   restrictedDelete: RestrictedDelete
   *   restrictedPicklist: RestrictedPicklist
   *   scale: Scale
   *   searchPrefilterable: SearchPrefilterable
   *   soapType: SoapType
   *   sortable: Sortable
   *   type: Type
   *   unique: Unique
   *   updateable: Updateable
   *   writeRequiresMasterRead: WriteRequiresMasterRead
   *
   * @default-fields label,name,type
   */
  public function describeFields($object) {
    $objectDescription = $this->client->objectDescribe($object);
    // Return if we cannot load any data.
    if (!is_object($objectDescription)) {
      $this->logger()->error(dt('Could not load data for object !object', ['!object' => $object]));
      return;
    }

    foreach ($objectDescription->getFields() as $field => $data) {
      if (!empty($data['picklistValues'])) {
        $fix_data = [];
        foreach ($data['picklistValues'] as $value) {
          $fix_data[] = $value['value'] . ' (' . $value['label'] . ')';
        }
        $data['picklistValues'] = $fix_data;
      }
      foreach ($data as $k => &$v) {
        if (is_array($v)) {
          $v = implode("\n", $v);
        }
      }
      $rows[$field] = $data;
    }
    return new RowsOfFields($rows);
  }

  /**
   * Lists the resources available for the current API version.
   *
   * @command salesforce:list-resources
   * @aliases sflr,sf-list-resources
   * @field-labels
   *   resource: Resource
   *   url: URL
   * @default-fields resource,url
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|null
   *   The resources, or null if resources failed to load.
   */
  public function listResources() {
    $resources = $this->client->listResources();
    if ($resources) {
      foreach ($resources->resources as $resource => $url) {
        $rows[$url] = ['resource' => $resource, 'url' => $url];
      }
      $this->output()->writeln("The following resources are available:");
      return new RowsOfFields($rows);
    }
    $this->logger()->error('Could not obtain a list of resources!');
  }

  /**
   * Read a Salesforce ID interactively.
   *
   * @hook interact salesforce:read-object
   */
  public function interactReadObject(Input $input, Output $output) {
    if (!$input->getArgument('id')) {
      if (!$answer = $this->io()->ask('Enter the Salesforce id to fetch')) {
        throw new UserAbortException();
      }
      $input->setArgument('id', $answer);
    }
  }

  /**
   * Retrieve all the data for an object with a specific ID.
   *
   * @param string $id
   *   A Salesforce ID.
   *
   * @throws \Exception
   *
   * @todo create a proper StructuredData return value
   *
   * @command salesforce:read-object
   * @aliases sfro,sf-read-object
   */
  public function readObject($id) {
    $name = $this->client->getObjectTypeName(new SFID($id));
    if ($object = $this->client->objectRead($name, $id)) {
      $this->output()->writeln(dt("!type with id !id", [
        '!type' => $object->type(),
        '!id' => $object->id(),
      ]));
      $this->output()->writeln(print_r($object->fields(), 1));
    }
  }

  /**
   * Fetch an object type and object data interactively.
   *
   * @hook interact salesforce:create-object
   */
  public function interactCreateObject(Input $input, Output $output) {
    $format = $input->getOption('encoding');
    if (empty($format)) {
      $input->setOption('encoding', 'query');
      $format = 'query';
    }
    elseif (!in_array($input->getOption('encoding'), ['query', 'json'])) {
      throw new \Exception('Invalid encoding');
    }

    $this->interactObject($input, $output, 'Enter the object type to be created');

    if (!$data = $this->io()->ask('Enter the object data to be created')) {
      throw new UserAbortException();
    }
    $params = [];
    switch ($format) {
      case 'query':
        parse_str($data, $params);
        if (empty($params)) {
          throw new \Exception(dt('Error when decoding data'));
        }
        break;

      case 'json':
        $params = json_decode($data, TRUE);
        if (json_last_error()) {
          throw new \Exception(dt('Error when decoding data: !error', ['!error' => json_last_error_msg()]));
        }
        break;

    }
    $this->input()->setArgument('data', $params);
  }

  /**
   * Create an object with specified data.
   *
   * @param string $object
   *   The object type name in Salesforce (e.g. Account).
   * @param mixed $data
   *   The data to use when creating the object (default is JSON format).
   *   Use '-' to read the data from STDIN.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option encoding
   *   Format to parse the object. Use  "json" for JSON (default) or "query"
   *   for data formatted like a query string, e.g. 'Company=Foo&LastName=Bar'.
   *   Defaults to "query".
   *
   * @field-labels
   *   status: Status
   *   id: Id
   *   errors: Errors
   * @default-fields status,id,errors
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   *   The create() response.
   *
   * @command salesforce:create-object
   * @aliases sfco,sf-create-object
   */
  public function createObject($object, $data, array $options = ['encoding' => 'query']) {
    try {
      $result = $this->client->objectCreate($object, $data);
      return new PropertyList([
        'status' => 'Success',
        'id' => (string) $result,
        'errors' => '',
      ]);
    }
    catch (RestException $e) {
      return new PropertyList([
        'status' => 'Fail',
        'id' => '',
        'errors' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Wrap ::interactObject() for query-object.
   *
   * @hook interact salesforce:query-object
   */
  public function interactQueryObject(Input $input, Output $output) {
    return $this->interactObject($input, $output, 'Enter the object to be queried');
  }

  /**
   * Query an object using SOQL with specified conditions.
   *
   * @param string $object
   *   The object type name in Salesforce (e.g. Account).
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @return \Drupal\salesforce\Commands\QueryResult
   *   The query result.
   *
   * @throws \Exception
   *
   * @option where
   *   A WHERE clause to add to the SOQL query
   * @option fields
   *   A comma-separated list fields to select in the SOQL query. If absent, an
   *   API call is used to find all fields
   * @option limit
   *   Integer limit on the number of results to return for the query.
   * @option order
   *   Comma-separated fields by which to sort results. Make sure to enclose in
   *   quotes for any whitespace.
   *
   * @command salesforce:query-object
   * @aliases sfqo,sf-query-object
   */
  public function queryObject($object, array $options = [
    'format' => 'table',
    'where' => NULL,
    'fields' => NULL,
    'limit' => NULL,
    'order' => NULL,
  ]) {
    $query = new SelectQuery($object);

    if (!$options['fields']) {
      $object = $this->client->objectDescribe($object);
      $query->fields = array_keys($object->getFields());
    }
    else {
      $query->fields = explode(',', $options['fields']);
      // Query must include Id.
      if (!in_array('Id', $query->fields)) {
        $query->fields[] = 'Id';
      }
    }

    $query->limit = $options['limit'];

    if ($options['where']) {
      $query->conditions = [[$options['where']]];
    }

    if ($options['order']) {
      $query->order = [];
      $orders = explode(',', $options['order']);
      foreach ($orders as $order) {
        list($field, $dir) = preg_split('/\s+/', $order, 2);
        $query->order[$field] = $dir;
      }
    }
    return $this->returnQueryResult(new QueryResult($query, $this->client->query($query)));
  }

  /**
   * Execute a SOQL query.
   *
   * @param string $query
   *   The query to execute.
   *
   * @return \Drupal\salesforce\Commands\QueryResult
   *   The query result.
   *
   * @command salesforce:execute-query
   * @aliases sfeq,soql,sf-execute-query
   */
  public function executeQuery($query) {
    $query = new SelectQueryRaw($query);
    return $this->returnQueryResult(new QueryResult($query, $this->client->query($query)));
  }

  /**
   * Lists authentication providers.
   *
   * @command salesforce:list-providers
   * @aliases sflp
   * @field-labels
   *   default: Default
   *   label: Label
   *   name: Name
   *   status: Token Status
   * @default-fields label,name,default,status
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The auth provider details.
   */
  public function listAuthProviders() {
    $rows = [];
    foreach ($this->authMan->getProviders() as $provider) {

      $rows[] = [
        'default' => $this->authMan->getConfig()->id() == $provider->id() ? '✓' : '',
        'label' => $provider->label(),
        'name' => $provider->id(),
        'status' => $provider->getPlugin()->hasAccessToken() ? 'Authorized' : 'Missing',
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Refresh the named authentication token, or the default if none specified.
   *
   * @param string $providerName
   *   The name of the authentication provider.
   *
   * @command salesforce:refresh-token
   * @aliases sfrt
   *
   * @return string
   *   Message indicating success or failure.
   *
   * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
   *   For missing token.
   */
  public function refreshToken($providerName = '') {
    // If no provider name given, use the default.
    if (empty($providerName)) {
      $providerName = $this->authMan->getConfig()->id();
    }

    if ($provider = SalesforceAuthConfig::load($providerName)) {
      $auth = $provider->getPlugin();
      $token = $auth->hasAccessToken() ? $auth->getAccessToken() : new StdOAuth2Token();
      $auth->refreshAccessToken($token);
      return "Access token refreshed for $providerName";
    }
    return "Provider $providerName not found.";
  }

  /**
   * Revoke the named authentication token, or the default if none specified.
   *
   * @param string $providerName
   *   The name of the authentication provider.
   *
   * @command salesforce:revoke-token
   * @aliases sfrvk
   *
   * @return string
   *   Message indicating success or failure.
   *
   * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
   *   For missing token.
   */
  public function revokeToken($providerName = '') {
    // If no provider name given, use the default.
    if (empty($providerName)) {
      $providerName = $this->authMan->getConfig()->id();
    }

    if ($provider = SalesforceAuthConfig::load($providerName)) {
      $auth = $provider->getPlugin();
      $auth->revokeAccessToken();
      return "Access token revoked for $providerName";
    }
    return "Provider $providerName not found.";
  }

}
