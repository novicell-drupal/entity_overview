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

}
