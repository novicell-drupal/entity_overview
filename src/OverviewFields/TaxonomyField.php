<?php
namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\entity_overview\OverviewFilter;

class TaxonomyField extends OverviewFieldBase {

  protected array $options = [];

  public function __construct($field_name, $label, array $options) {
    parent::__construct($field_name, $label);
    $this->options = $options;
  }

  public function getWidgets(): array {
    return ['checkboxes' => t('Check boxes'), 'radios' => t('Radios'), 'select' => t('Select list')];
  }

  public function getFieldFormElement(OverviewFilter $filter): array {
    if ($filter->getOverview()->getFieldWidget($this->id()) == 'checkboxes') {
      $options = $this->options ?? [];
    } else {
      $options = ['' => t('All')] + $this->options ?? [];
    }
    return parent::getFieldFormElement($filter) + ['#options' => $options];
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return parent::getFieldFormTransform($filter) + ['options' => $this->options ?? []];
  }

  public static function createFromFieldDefinition(FieldDefinitionInterface $definition): TaxonomyField {
    $settings = $definition->getSettings() ?? [];
    $storage = \Drupal::entityTypeManager()
      ->getStorage($settings['target_type']);
    $label = $definition->getLabel();
    $options = [];
    $vid = reset($settings['handler_settings']['target_bundles']);
    $query = $storage->getQuery();
    $query->condition('vid', $vid);
    if (isset($settings['handler_settings']['sort']['field']) && isset($settings['handler_settings']['sort']['direction'])) {
      $query->sort($settings['handler_settings']['sort']['field'], $settings['handler_settings']['sort']['direction']);
    }
    $tids = $query->execute();
    $terms = $storage->loadMultiple($tids);
    foreach ($terms as $term) {
      $options[$term->id()] = $term->label();
    }
    return new self($definition->getName(), $label, $options);
  }
}
