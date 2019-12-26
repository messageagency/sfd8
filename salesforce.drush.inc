<?php

/**
 * @file
 * Drush integration for Salesforce.
 */

use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\Exception as SalesforceException;

/**
 * Implements hook_drush_command().
 */
function salesforce_drush_command() {
  $items['sf-rest-version'] = [
    'description' => 'Displays information about the current REST API version',
    'aliases' => ['sfrv'],
  ];

  $items['sf-list-objects'] = [
    'description' => 'List the objects that are available in your organization and available to the logged-in user.',
    'aliases' => ['sflo'],
  ];

  $items['sf-describe-object'] = [
    'description' => 'Retrieve all the metadata for an object, including information about each field, URLs, and child relationships.',
    'aliases' => ['sfdo'],
    'arguments' => [
      'object' => 'The object name in Salesforce.',
    ],
    'options' => [
      'output' => "Specify an output type.
Options are:
info: (default) Display metadata about an object
fields: Display information about fields that are part of the object
field-data FIELDNAME: Display information about a specific field that is part of an object
raw: Display the complete, raw describe response.",
    ],
    'examples' => [
      'drush sfdo Contact' => 'Show metadata about Contact SObject type.',
      'drush sfdo Contact --output=fields' => 'Show addtional metadata about Contact fields.',
      'drush sfdo Contact --output=field --field=Email' => 'Show full metadata about Contact.Email field.',
      'drush sfdo Contact --output=raw' => 'Display the full metadata for Contact SObject type.',
    ],
  ];

  $items['sf-list-resources'] = [
    'description' => 'Lists the resources available for the specified API version. It provides the name and URI of each resource.',
    'aliases' => ['sflr'],
  ];

  $items['sf-read-object'] = [
    'description' => 'Retrieve all the data for an object with a specific ID.',
    'aliases' => ['sfro'],
    'arguments' => [
      'id' => 'The object ID in Salesforce.',
    ],
    'options' => [
      'format' => [
        'description' => 'Format to output the object. Use "print_r" for print_r (default), "export" for var_export, and "json" for JSON.',
        'example-value' => 'export',
      ],
    ],
  ];

  $items['sf-create-object'] = [
    'description' => 'Create an object with specified data.',
    'aliases' => ['sfco'],
    'arguments' => [
      'object' => 'The object type name in Salesforce (e.g. Account).',
      'data' => 'The data to use when creating the object (default is JSON format). Use \'-\' to read the data from STDIN.',
    ],
    'options' => [
      'format' => [
        'description' => 'Format to parse the object. Use  "json" for JSON (default) or "query" for data formatted like a query string, e.g. \'Company=Foo&LastName=Bar\'.',
        'example-value' => 'json',
      ],
    ],
  ];

  $items['sf-query-object'] = [
    'description' => 'Query an object using SOQL with specified conditions.',
    'aliases' => ['sfqo'],
    'arguments' => [
      'object' => 'The object type name in Salesforce (e.g. Account).',
    ],
    'options' => [
      'format' => [
        'description' => 'Format to output the objects. Use "print_r" for print_r (default), "export" for var_export, and "json" for JSON.',
        'example-value' => 'export',
      ],
      'where' => [
        'description' => 'A WHERE clause to add to the SOQL query',
      ],
      'fields' => [
        'description' => 'A comma-separated list fields to select in the SOQL query. If absent, an API call is used to find all fields',
      ],
      'limit' => [
        'description' => 'Integer limit on the number of results to return for the query.',
      ],
      'order' => [
        'description' => 'Comma-separated fields by which to sort results. Make sure to enclose in quotes for any whitespace.',
      ],
    ],
  ];

  $items['sf-execute-query'] = [
    'description' => 'Execute a SOQL query.',
    'aliases' => ['sfeq', 'soql'],
    'arguments' => [
      'query' => 'The query to execute.',
    ],
  ];

  $items['sf-pull-query'] = [
    'description' => 'Given a mapping, enqueue records for pull from Salesforce, ignoring modification timestamp. This command is useful, for example, when seeding content for a Drupal site prior to deployment.',
    'aliases' => ['sfpq', 'sfiq'],
    'arguments' => [
      'name' => 'Machine name of the Salesforce Mapping for which to queue pull records.',
    ],
    'options' => [
      'where' => [
        'description' => 'A WHERE clause to add to the SOQL pull query. Default behavior is to query and pull all records.',
      ],
    ],
    'examples' => [
      'drush sfpq' => 'Interactively select a mapping for which to queue records.',
      'drush sfpq user' => 'Query and queue all records for "user" Salesforce mapping.',
      'drush sfpq user --where="Email like \'%foo%\' AND (LastName = \'bar\' OR FirstName = \'bar\')"' => 'Query and queue all records for "user" Salesforce mapping with Email field containing the string "foo" and First or Last name equal to "bar"',
    ],
  ];

  $items['sf-pull-file'] = [
    'description' => 'Given a mapping, enqueue a list of object IDs to be pulled from a CSV file, e.g. a Salesforce report. The first column of the CSV file must be SFIDs. Additional columns will be ignored.',
    'aliases' => ['sfpf', 'sfif'],
    'arguments' => [
      'file' => 'CSV file name of 15- or 18-character Salesforce ids to be pulled. ',
      'name' => 'Machine name of the Salesforce Mapping for which to queue pull records.',
    ],
  ];

  return $items;
}

/**
 * List the resources available for the specified API version.
 *
 * This command provides the name and URI of each resource.
 */
function drush_salesforce_sf_list_resources() {
  _drush_salesforce_deprecated();
  $salesforce = \Drupal::service('salesforce.client');
  $resources = $salesforce->listResources();
  if ($resources) {
    $items[] = ['Resource', 'URL'];
    foreach ($resources->resources as $resource => $url) {
      $items[] = [$resource, $url];
    }
    drush_print("The following resources are available:\n");
    drush_print_table($items);
  }
  else {
    drush_log('Could not obtain a list of resources!', 'error');
  }
}

/**
 * Describes a Salesforce object.
 *
 * Use the --fields option to display information about the fields of an object,
 * or the --field-data option to display information about a single field in an
 * object.
 *
 * @param string $object_name
 *   The name of a Salesforce object to query.
 */
function drush_salesforce_sf_describe_object($object_name = NULL) {
  _drush_salesforce_deprecated();

  if (!$object_name) {
    return drush_log('Please specify an object as an argument.', 'error');
  }
  $salesforce = \Drupal::service('salesforce.client');

  $object = $salesforce->objectDescribe($object_name);

  // Return if we cannot load any data.
  if (!is_object($object)) {
    return drush_log(dt('Could not load data for object !object', ['!object' => $object_name]), 'error');
  }

  $output = drush_get_option('output');
  switch ($output) {
    case 'raw':
      drush_print_r($object->data);
      return;

    case 'fields':
      $rows = [['Name', 'Type', 'Label']];
      foreach ($object->fields as $field) {
        $rows[] = [$field['name'], $field['type'], $field['label']];
      }
      drush_print_table($rows, TRUE);
      return;

    case 'field':
      $fieldname = drush_get_option('field');
      if (empty($fieldname)) {
        drush_log(dt('Please specify a field name'), 'error');
        return;
      }
      try {
        $field_data = $object->getField($fieldname);
      }
      catch (\Exception $e) {
        watchdog_exception('salesforce.drush', $e);
        drush_log(dt('Could not load data for field !field on !object object', [
          '!field' => $fieldname,
          '!object' => $object_name,
        ]), 'error');
        return;
      }
      drush_print_r($field_data);
      return;

    default:
      if ($output != 'info') {
        drush_log(dt('Unkonwn output option !output', ['!output' => $output]), 'error');
        return;
      }

      // Display information about the object.
      $rows = [];
      $rows[] = ['Name', $object->name];
      $rows[] = ['Label', $object->label];
      $rows[] = ['Field count', count($object->getFields())];
      $rows[] = ['SFID prefix', $object->keyPrefix];
      $rows[] = [
        'Child Relationships',
        isset($object->childRelationships) ? count($object->childRelationships) : 0,
      ];

      $rows[] = ['Searchable', ($object->searchable == 1) ? 'TRUE' : 'FALSE'];
      $rows[] = ['Creatable', ($object->createable == 1) ? 'TRUE' : 'FALSE'];
      $rows[] = ['Deletable', ($object->deletable == 1) ? 'TRUE' : 'FALSE'];
      $rows[] = ['Mergeable', ($object->mergeable == 1) ? 'TRUE' : 'FALSE'];
      $rows[] = ['Queryable', ($object->queryable == 1) ? 'TRUE' : 'FALSE'];
      drush_print_table($rows);
      return;
  }
}

/**
 * Displays information about the REST API version the site is using.
 */
function drush_salesforce_sf_rest_version() {
  _drush_salesforce_deprecated();

  $salesforce = \Drupal::service('salesforce.client');
  $version_id = $salesforce->getApiVersion();
  $versions = $salesforce->getVersions();
  $version = $versions[$version_id];
  $latest = array_pop($versions);

  foreach ($version as $key => $value) {
    $rows[] = [$key, $value];
  }
  $rows[] = ['login url', $salesforce->getLoginUrl()];
  $rows[] = [
    'latest version',
    strcmp($version_id, $latest['version']) ? $latest['version'] : 'Yes',
  ];
  drush_print_table($rows, TRUE);
}

/**
 * List Salesforce objects.
 *
 * This command lists Salesforce objects that are available in your organization
 * and available to the logged-in user.
 */
function drush_salesforce_sf_list_objects() {
  _drush_salesforce_deprecated();
  $salesforce = \Drupal::service('salesforce.client');
  if ($objects = $salesforce->objects()) {
    print_r($objects);
    drush_print('The following objects are available in your organization and available to the logged-in user.');
    $rows[] = ['Name', 'Label', 'Label Plural'];
    foreach ($objects as $object) {
      $rows[] = [
        $object['name'],
        $object['label'],
        $object['labelPlural'],
      ];
    }
    drush_print_table($rows, TRUE);
  }
  else {
    drush_log('Could not load any information about available objects.', 'error');
  }

}

/**
 * Read a Salesforce object available to the logged-in user.
 *
 * @param string $id
 *   The Salesforce ID.
 *
 * @throws \Exception
 */
function drush_salesforce_sf_read_object($id) {
  _drush_salesforce_deprecated();
  $salesforce = \Drupal::service('salesforce.client');
  try {
    $name = $salesforce->getObjectTypeName(new SFID($id));
    if ($object = $salesforce->objectRead($name, $id)) {
      drush_print(dt('!type with id !id:', [
        '!type' => $object->type(),
        '!id' => $object->id(),
      ]));
      drush_print(drush_format($object->fields()));
    }
  }
  catch (SalesforceException $e) {
    drush_log($e->getMessage(), 'error');
  }
}

/**
 * Create a Salesforce object available to the logged-in user.
 *
 * @param string $name
 *   The object type name, e.g. Account.
 * @param string $data
 *   The object data, or '-' to read from stdin.
 */
function drush_salesforce_sf_create_object($name, $data) {
  _drush_salesforce_deprecated();

  if ($data == '-') {
    $data = stream_get_contents(STDIN);
  }
  $format = drush_get_option('format', 'json');
  $params = [];
  switch ($format) {
    case 'query':
      parse_str($data, $params);
      break;

    case 'json':
      $params = json_decode($data, TRUE);
      break;

    default:
      drush_log(dt('Invalid format'), 'error');
      return;
  }
  $salesforce = \Drupal::service('salesforce.client');
  try {
    if ($result = $salesforce->objectCreate($name, $params)) {
      drush_print_r($result);
    }
  }
  catch (SalesforceException $e) {
    drush_log($e->getMessage(), 'error');
  }
}

/**
 * Query Salesforce objects available to the logged-in user.
 *
 * @param string $name
 *   The object type name, e.g. Account.
 */
function drush_salesforce_sf_query_object($name) {
  _drush_salesforce_deprecated();
  $salesforce = \Drupal::service('salesforce.client');

  $query = new SelectQuery($name);

  $fields = drush_get_option('fields', '');
  if (!$fields) {
    $object = $salesforce->objectDescribe($name);
    $query->fields = array_keys($object->getFields());
  }
  else {
    $query->fields = explode(',', $fields);
  }

  $query->limit = drush_get_option('limit', '');

  if ($where = drush_get_option('where', '')) {
    $query->conditions = [[$where]];
  }

  if ($order = drush_get_option('order', '')) {
    $query->order = [];
    $orders = explode(',', $order);
    foreach ($orders as $order) {
      list($field, $dir) = preg_split('/\s+/', $order, 2);
      $query->order[$field] = $dir;
    }
  }

  try {
    $result = $salesforce->query($query);
  }
  catch (SalesforceException $e) {
    drush_log($e->getMessage(), 'error');
    return;
  }

  foreach ($result->records() as $sfid => $record) {
    drush_print(drush_format([$sfid => $record->fields()]));
  }
  $pretty_query = str_replace('+', ' ', (string) $query);
  if (!$fields) {
    $fields = implode(',', $query->fields);
    $pretty_query = str_replace($fields, ' * ', $pretty_query);
  }
  drush_print(dt("Showing !size of !total records for query:\n!query", [
    '!size' => count($result->records()),
    '!total' => $result->size(),
    '!query' => $pretty_query,
  ]));

}

/**
 * Execute a SOQL query.
 *
 * @param string $query
 *   The query to execute.
 */
function drush_salesforce_sf_execute_query($query = NULL) {
  _drush_salesforce_deprecated();
  if (!$query) {
    drush_log('Please specify a query as an argument.', 'error');
    return;
  }
  $salesforce = \Drupal::service('salesforce.client');
  try {
    $result = $salesforce->apiCall('query?q=' . urlencode($query));
    drush_print(drush_format($result));
  }
  catch (SalesforceException $e) {
    drush_log($e->getMessage(), 'error');
  }
}

/**
 * Get a mapping from the given name, or from user input if name is empty.
 *
 * @param string $name
 *   The mapping name.
 *
 * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface|null
 *   The mapping.
 */
function _salesforce_drush_get_mapping($name = NULL) {
  _drush_salesforce_deprecated();
  $mapping_storage = \Drupal::service('entity_type.manager')
    ->getStorage('salesforce_mapping');

  if (empty($name)) {
    $choices = array_keys($mapping_storage->loadMultiple());
    if (empty($choices)) {
      drush_log(dt('No mappings found.'), 'error');
      return;
    }
    ksort($choices);
    $choice = drush_choice($choices, dt('Enter a number to choose which mapping to use.'));
    if ($choice === FALSE) {
      return;
    }
    $name = $choices[$choice];
  }
  $mapping = $mapping_storage->load($name);
  if (empty($mapping)) {
    drush_log(dt('Mapping !name not found.', ['!name' => $name]), 'error');
  }
  return $mapping;
}

/**
 * Trigger a deprecation error.
 */
function _drush_salesforce_deprecated() {
  trigger_error('Salesforce module support for Drush 8 is deprecated and will be removed in a future release', E_USER_DEPRECATED);
}
