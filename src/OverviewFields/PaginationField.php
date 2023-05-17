<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;

class PaginationField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  /**
   * @inheritDoc
   */
  public function id(): string {
    return 'pagination';
  }

  /**
   * @inheritDoc
   */
  public function label(): string|TranslatableMarkup {
    return t('Pagination');
  }

  /**
   * @inheritDoc
   */
  public function getWidgets(): array {
    return ['checkbox' => t('Single on/off checkbox')];
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
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function requiresFacets(): bool {
    return TRUE;
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormElement(OverviewFilter $filter): array {
    return [
      '#type' => 'checkbox',
      '#title' => $this->label(),
      '#description' => t('Display pager at the bottom.'),
      '#default_value' => $filter->hasPagination()
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
      'type' => 'checkbox',
      'title' => $this->label(),
      'description' => t('Display pager at the bottom.'),
      'default_value' => $filter->hasPagination()
    ];
  }

}
