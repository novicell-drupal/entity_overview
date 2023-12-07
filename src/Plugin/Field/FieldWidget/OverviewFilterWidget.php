<?php

namespace Drupal\entity_overview\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\styles\StylesManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'overview_filter' widget.
 *
 * @FieldWidget(
 *   id = "overview_filter_widget",
 *   label = @Translation("Overview filter"),
 *   description = @Translation("Select options for filtering."),
 *   field_types = {
 *     "overview_filter"
 *   },
 *   multiple_values = TRUE
 * )
 */
class OverviewFilterWidget extends WidgetBase {

  /**
   * @var \Drupal\entity_overview\OverviewManager
   */
  protected $overviewManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, OverviewManager $overviewManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->overviewManager = $overviewManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('entity_overview.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var FieldItemInterface $item */
    $item = $items[$delta];
    if (empty($item)) {
      $fields = [];
    } else {
      $fields = $item->toArray();
    }
    $overview_id = $this->getFieldSetting('overview');
    if (empty($overview_id)) {
      $entity_bundle = $this->getFieldSetting('entity_bundle');
      $overview_id = str_replace('node.', '', $entity_bundle);
    }
    $filter = new OverviewFilter($overview_id, $fields);
    $element = $this->overviewManager->buildOverviewFilterForm($filter, $this->getFieldSetting('allow_facets'));

    // If cardinality is 1, ensure a proper label is output for the field.
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() == 1) {
      $element += [
        '#type' => 'fieldset',
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (empty($values['facets'])) {
      $values['facets'] = [];
    }
    if (is_string($values['facets'])) {
      $values['facets'] = [$values['facets']];
    } elseif (is_array($values['facets'])) {
      $result = [];
      foreach ($values['facets'] as $value) {
        if (!empty($value)) {
          $result[] = $value;
        }
      }
      $values['facets'] = $result;
    }
    if (is_string($values['count'])) {
      $values['count'] = intval($values['count']);
    }
    if (is_array($values['fields'])) {
      foreach ($values['fields'] as $field_name => $selections) {
        if (is_array($selections)) {
          $result = [];
          foreach ($selections as $key => $value) {
            if (!empty($value)) {
              $result[] = $value;
            }
          }
          $values['fields'][$field_name] = $result;
        }
        elseif (is_null($selections)) {
          $values['fields'][$field_name] = '';
        }
      }
    } else {
      $values['fields'] = [];
    }
    $values['pagination'] = boolval($values['pagination'] ?? FALSE);

    return $values;
  }
}
