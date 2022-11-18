<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;

class SearchTextField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  public function id(): string {
    return 'text';
  }

  public function label(): string|TranslatableMarkup {
    return t('Search terms');
  }

  public function getWidgets(): array {
    return ['search' => t('Search field')];
  }

  public function isBase(): bool {
    return FALSE;
  }

  public function canBeExposed(): bool {
    return TRUE;
  }

  public function requiresFacets(): bool {
    return FALSE;
  }

  public function updateFieldFormElementDefaultValue($value): mixed {
    return $value;
  }

  public function getFieldFormElement(OverviewFilter $filter): array {
    return [
      '#type' => 'search',
      '#title' => $this->label(),
      '#default_value' => $filter->getFieldValue($this->id()) ?? ''
    ];
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => 'search',
      'title' => $this->label(),
      'default_value' => $filter->getFieldValue($this->id()) ?? ''
    ];
  }

}
