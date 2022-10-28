<?php
namespace Drupal\entity_overview\OverviewFields;

use Drupal\entity_overview\OverviewFilter;

class TaxonomyField extends OverviewFieldBase {

  protected array $options = [];

  public function __construct($field_name, $label, array $options) {
    parent::__construct($field_name, $label);
    $this->options = $options;
  }

  public function getWidgets(): array {
    return ['checkboxes'];
  }

  public function getFieldFormElement(OverviewFilter $filter): array {
    return parent::getFieldFormElement($filter) + ['#options' => $this->options ?? []];
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return parent::getFieldFormTransform($filter) + ['options' => $this->options ?? []];
  }

}
