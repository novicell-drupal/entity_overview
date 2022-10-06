<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;

abstract class OverviewFieldBase implements OverviewFieldInfoInterface {

  protected string $field_name = '';
  protected string $label = '';

  public function __construct($field_name, $label) {
    $this->field_name = $field_name;
    $this->label = $label;
  }

  public function id(): string {
    return $this->field_name;
  }

  public function label(): string|TranslatableMarkup {
    return $this->label;
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

  public function getFieldFormElement(OverviewFilter $filter): array {
    return [
      '#type' => $filter->getOverview()->getFieldWidget($this->id()),
      '#title' => $this->label(),
      '#default_value' => $filter->getFieldValue($this->id())
    ];
  }

  public function updateFieldFormElementDefaultValue($value): mixed {
    return $value;
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => $filter->getOverview()->getFieldWidget($this->id()),
      'title' => $this->label(),
      'default_value' => $filter->getFieldValue($this->id())
    ];
  }

}
