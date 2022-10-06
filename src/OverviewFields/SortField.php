<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;

class SortField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  public function id(): string {
    return 'sort';
  }

  public function label(): string|TranslatableMarkup {
    return t('Sort by');
  }

  public function getWidgets(): array {
    return ['select'];
  }

  public function isBase(): bool {
    return TRUE;
  }

  public function canBeExposed(): bool {
    return TRUE;
  }

  public function requiresFacets(): bool {
    return FALSE;
  }

  public function getFieldFormElement(OverviewFilter $filter): array {
    return [
      '#type' => 'select',
      '#title' => $this->label(),
      '#options' => $filter->getOverview()->getEngine()->getSortCriterias(),
      '#default_value' => $filter->getSort()
    ];
  }

  public function updateFieldFormElementDefaultValue($value): mixed {
    return $value;
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => 'select',
      'title' => $this->label(),
      'options' => $filter->getOverview()->getEngine()->getSortCriterias(),
      'default_value' => $filter->getSort()
    ];
  }

}
