<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;

class SortField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  /**
   * @inheritDoc
   */
  public function id(): string {
    return 'sort';
  }

  /**
   * @inheritDoc
   */
  public function label(): string|TranslatableMarkup {
    return t('Sort by');
  }

  /**
   * @inheritDoc
   */
  public function getWidgets(): array {
    return ['select' => t('Select list')];
  }

  /**
   * @inheritDoc
   */
  public function isBase(): bool {
    return TRUE;
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
    return [
      '#type' => 'select',
      '#title' => $this->label(),
      '#options' => $filter->getOverview()->getEngine()->getSortCriterias(),
      '#default_value' => $filter->getSort()
    ];
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
  public function setFieldFormElementAttribute(array &$form, $attribute, $value): void {
    $form['#' . $attribute] = $value;
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
      'type' => 'select',
      'title' => $this->label(),
      'options' => $filter->getOverview()->getEngine()->getSortCriterias(),
      'default_value' => $filter->getSort()
    ];
  }

}
