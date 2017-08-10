<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Render\Element;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Base class for entity view builders.
 *
 * @ingroup entity_api
 */
class MappedObjectViewBuilder extends EntityViewBuilder {

  public function buildMultiple(array $build_list) {
    dpm($build_list);
    // Build the view modes and display objects.
    $view_modes = [];
    $entity_type_key = "#{$this->entityTypeId}";
    $view_hook = "{$this->entityTypeId}_view";

    // Find the keys for the ContentEntities in the build; Store entities for
    // rendering by view_mode.
    $children = Element::children($build_list);
    foreach ($children as $key) {
      if (isset($build_list[$key][$entity_type_key])) {
        $entity = $build_list[$key][$entity_type_key];
        if ($entity instanceof FieldableEntityInterface) {
          $view_modes[$build_list[$key]['#view_mode']][$key] = $entity;
        }
      }
    }

    // Build content for the displays represented by the entities.
    foreach ($view_modes as $view_mode => $view_mode_entities) {
      $displays = EntityViewDisplay::collectRenderDisplays($view_mode_entities, $view_mode);
      $this->buildComponents($build_list, $view_mode_entities, $displays, $view_mode);
      foreach (array_keys($view_mode_entities) as $key) {
        // Allow for alterations while building, before rendering.
        $entity = $build_list[$key][$entity_type_key];
        $display = $displays[$entity->bundle()];

        $this->moduleHandler()->invokeAll($view_hook, [&$build_list[$key], $entity, $display, $view_mode]);
        $this->moduleHandler()->invokeAll('entity_view', [&$build_list[$key], $entity, $display, $view_mode]);

        $this->alterBuild($build_list[$key], $entity, $display, $view_mode);

        // Assign the weights configured in the display.
        // @todo: Once https://www.drupal.org/node/1875974 provides the missing
        //   API, only do it for 'extra fields', since other components have
        //   been taken care of in EntityViewDisplay::buildMultiple().
        foreach ($display->getComponents() as $name => $options) {
          if (isset($build_list[$key][$name])) {
            $build_list[$key][$name]['#weight'] = $options['weight'];
          }
        }

        // Allow modules to modify the render array.
        $this->moduleHandler()->alter([$view_hook, 'entity_view'], $build_list[$key], $entity, $display);
      }
    }

    return $build_list;
  }

  /**
   * {@inheritdoc}
   */
  public function viewField(FieldItemListInterface $items, $display_options = []) {
    dpm(func_get_args());
    $output = parent::viewField($items, $display_options);
    dpm($output);
    return $output;
    $entity = $items->getEntity();
    $field_name = $items->getFieldDefinition()->getName();
    $display = $this->getSingleFieldDisplay($entity, $field_name, $display_options);

    $output = [];
    $build = $display->build($entity);
    if (isset($build[$field_name])) {
      $output = $build[$field_name];
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function viewFieldItem(FieldItemInterface $item, $display = []) {
    dpm(func_get_args());
    $output = parent::viewFieldItem($item, $display);
    dpm($output);
    return $output;
    $entity = $item->getEntity();
    $field_name = $item->getFieldDefinition()->getName();

    // Clone the entity since we are going to modify field values.
    $clone = clone $entity;

    // Push the item as the single value for the field, and defer to viewField()
    // to build the render array for the whole list.
    $clone->{$field_name}->setValue([$item->getValue()]);
    $elements = $this->viewField($clone->{$field_name}, $display);

    // Extract the part of the render array we need.
    $output = isset($elements[0]) ? $elements[0] : [];
    if (isset($elements['#access'])) {
      $output['#access'] = $elements['#access'];
    }

    return $output;
  }

}
