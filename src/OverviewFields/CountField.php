<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;

class CountField implements OverviewFieldInfoInterface {

  protected OverviewManager $overviewManager;

  public function __construct(OverviewManager $overviewManager) {
    $this->overviewManager = $overviewManager;
  }

  public function id(): string {
    return 'count';
  }

  public function label(): string|TranslatableMarkup {
    return t('Page size');
  }

  public function getWidgets(): array {
    return ['select' => t('Select list'), 'number' => t('Number field')];
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
      '#options' => $this->overviewManager->getCountOptions($filter->getOverviewId()),
      '#default_value' => $filter->getCount()
    ];
  }

  public function updateFieldFormElementDefaultValue($value): mixed {
    return $value;
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => 'select',
      'title' => $this->label(),
      'options' => $this->overviewManager->getCountOptions($filter->getOverviewId()),
      'default_value' => $filter->getCount()
    ];
  }

}
