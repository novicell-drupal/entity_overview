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

  /**
   * @inheritDoc
   */
  public function id(): string {
    return $this->field_name;
  }

  /**
   * @inheritDoc
   */
  public function label(): string|TranslatableMarkup {
    return $this->label;
  }

  /**
   * @inheritDoc
   */
  public function isBase(): bool {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function canBeExposed(): bool {
    return TRUE;
  }

  /**
   * @inheritDoc
   */
  public function requiresFacets(): bool {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormElement(OverviewFilter $filter): array {
    $element = [
      '#type' => $filter->getOverview()->getFieldWidget($this->id()),
      '#title' => $this->label(),
    ];
    if (!empty($filter->getFieldValue($this->id()))) {
      $element['#default_value'] = $filter->getFieldValue($this->id());
    }
    return $element;
  }

  /**
   * @inheritDoc
   */
  public function setFieldFormElementAttribute(array &$form, $attribute, $value): void {
    $form['#' . $attribute] = $value;
  }

  /**
   * @inheritDoc
   */
  public function updateFieldFormElementDefaultValue($value): mixed {
    return $value;
  }

  /**
   * @inheritDoc
   */
  public function getFilterValueFromFormStateValue($value): mixed {
    return $value;
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => $filter->getOverview()->getFieldWidget($this->id()),
      'title' => $this->label(),
      'default_value' => $filter->getFieldValue($this->id())
    ];
  }

}
