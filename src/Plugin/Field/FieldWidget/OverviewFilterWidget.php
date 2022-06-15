<?php

namespace Drupal\entity_overview\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
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

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var FieldItemInterface $item */
    $item = $items[$delta] ?? [];
    $entity_bundle = $this->getFieldSetting('entity_bundle');
    $fields = $this->overviewManager->getFieldFormElements($entity_bundle);

    if (!empty($item) && $item->getEntity()->getEntityTypeId() == 'taxonomy_term') {
      $vid = $item->getEntity()->bundle();
      foreach ($fields as $field_name => $form_element) {
        if ($form_element['vid'] == $vid) {
          $element['fields'][$field_name] = [
            '#type' => 'item',
            '#title' => $form_element['label'],
            '#description' => $item->getEntity()->label() ?? $this->t('Show entities of this type.'),
          ];
          $element['element_id_field'] = [
            '#type' => 'hidden',
            '#default_value' => $field_name
          ];
          unset($fields[$field_name]);
        }
      }
    }

    foreach ($fields as $field_name => $form_element) {

      $element['fields'][$field_name] = [
        '#type' => $form_element['form_element'],
        '#title' => $form_element['label'],
        '#description' => $this->t('Default values'),
        '#options' => $form_element['options'],
        '#default_value' => $item->fields[$field_name] ?? []
      ];
    }

    $base_facets = $this->overviewManager->getBaseFacets($entity_bundle);
    foreach ($base_facets as $id => $label) {
      switch($id) {
        case 'count':
        case 'sort':
          $form[$id] = $this->overviewManager->getBaseFacetForm($entity_bundle, $id, $form_state);
          $form[$id]['#title'] = $label;
          break;
        default:
          $form['fields'][$id] = $this->overviewManager->getBaseFacetForm($entity_bundle, $id, $form_state);
          $form['fields'][$id]['#title'] = $label;
          break;
      }
    }

    if ($this->getFieldSetting('allow_facets')) {
      $filter_options = $base_facets;
      foreach ($fields as $field_name => $form_element) {
        $filter_options[$field_name] = $form_element['label'];
      }
      if (!empty($filter_options)) {
        $element['facets'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Facets'),
          '#description' => $this->t('Select the facets that you want to expose to the user.'),
          '#options' => $filter_options,
          '#default_value' => $item->facets ?? [],
        ];
      }

      $element['pagination'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Pagination'),
        '#description' => $this->t('Display pager at the bottom.'),
        '#default_value' => $item->pagination ?? FALSE,
      ];
    } else {
      $element['facets'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];
      $element['pagination'] = [
        '#type' => 'hidden',
        '#default_value' => FALSE,
      ];
    }

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
    }
    $values['pagination'] = boolval($values['pagination']);

    return $values;
  }
}
