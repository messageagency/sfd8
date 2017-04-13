<?php

namespace Drupal\salesforce\Rest;

class RestResponse_Describe extends RestResponse {

  /**
   * Array of field definitions for this SObject type, indexed by machine name.
   *
   * @var array
   */
  protected $fields;
  
  /**
   * The name of this SObject type, e.g. "Contact", "Account", "Opportunity"
   *
   * @var string
   */
  protected $name;

  /**
   * Flattened fields mapping field name => field label
   *
   * @var array
   */
  private $field_options;

  /**
   * See https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_sobject_describe.htm
   *
   * @param RestResponse $response
   */
  public function __construct(RestResponse $response) {
    parent::__construct($response->response);

    $this->name = $response->data['name'];
    $this->fields = [];
    // Index fields by machine name, so we don't have to search every time.
    foreach ($response->data['fields'] as $field) {
      $this->fields[$field['name']] = $field;
    }

    foreach ($response->data as $key => $value) {
      if ($key == 'fields') {
        continue;
      }
      $this->$key = $value;
    }
    $this->data = $response->data;
  }

  /**
   * getter
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Return a field definition for the given field name.
   * A single Salesforce field may contain the following keys:
   *    aggregatable
   *    autoNumber
   *    byteLength
   *    calculated
   *    calculatedFormula
   *    cascadeDelete
   *    caseSensitive
   *    controllerName
   *    createable
   *    custom
   *    defaultValue
   *    defaultValueFormula
   *    defaultedOnCreate
   *    dependentPicklist
   *    deprecatedAndHidden
   *    digits
   *    displayLocationInDecimal
   *    encrypted
   *    externalId
   *    extraTypeInfo
   *    filterable
   *    filteredLookupInfo
   *    groupable
   *    highScaleNumber
   *    htmlFormatted
   *    idLookup
   *    inlineHelpText
   *    label
   *    length
   *    mask
   *    maskType
   *    name
   *    nameField
   *    namePointing
   *    nillable
   *    permissionable
   *    picklistValues
   *    precision
   *    queryByDistance
   *    referenceTargetField
   *    referenceTo
   *    relationshipName
   *    relationshipOrder
   *    restrictedDelete
   *    restrictedPicklist
   *    scale
   *    soapType
   *    sortable
   *    type
   *    unique
   *    updateable
   *    writeRequiresMasterRead
   *
   * for more information @see https://developer.salesforce.com/docs/atlas.en-us.apexcode.meta/apexcode/apex_methods_system_fields_describe.htm
   *
   * @param string $field_name
   * @return array field definition
   * @throws Exception if field_name is not defined for this SObject type
   */
  public function getField($field_name) {
    if (empty($this->fields[$field_name])) {
      throw new \Exception("No field $field_name");
    }
    return $this->fields[$field_name];
  }

  /**
   * Return a one-dimensional array of field names => field labels
   *
   * @return array
   */
  public function getFieldOptions() {
    if (!isset($this->field_options)) {
      $this->field_options = array_column($this->fields, 'label', 'name');
      asort($this->field_options);
    }
    return $this->field_options;
  }

}
