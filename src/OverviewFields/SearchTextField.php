<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;

class SearchTextField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  /**
   * @inheritDoc
   */
  public function id(): string {
    return 'text';
  }

  /**
   * @inheritDoc
   */
  public function label(): string|TranslatableMarkup {
    return t('Search terms');
  }

  /**
   * @inheritDoc
   */
  public function getWidgets(): array {
    return ['search' => t('Search field')];
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
  public function getFieldFormElement(OverviewFilter $filter): array {
    return [
      '#type' => 'search',
      '#title' => $this->label(),
      '#default_value' => $filter->getFieldValue($this->id()) ?? ''
    ];
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => 'search',
      'title' => $this->label(),
      'default_value' => $filter->getFieldValue($this->id()) ?? ''
    ];
  }

}
