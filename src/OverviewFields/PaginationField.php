<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;

class PaginationField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  public function id(): string {
    return 'pagination';
  }

  public function label(): string|TranslatableMarkup {
    return t('Pagination');
  }

  public function getWidgets(): array {
    return ['checkbox' => t('Single on/off checkbox')];
  }

  public function isBase(): bool {
    return TRUE;
  }

  public function canBeExposed(): bool {
    return FALSE;
  }

  public function requiresFacets(): bool {
    return TRUE;
  }

  public function getFieldFormElement(OverviewFilter $filter): array {
    return [
      '#type' => 'checkbox',
      '#title' => $this->label(),
      '#description' => t('Display pager at the bottom.'),
      '#default_value' => $filter->hasPagination()
    ];
  }

  public function updateFieldFormElementDefaultValue($value): mixed {
    return $value;
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => 'checkbox',
      'title' => $this->label(),
      'description' => t('Display pager at the bottom.'),
      'default_value' => $filter->hasPagination()
    ];
  }

}
