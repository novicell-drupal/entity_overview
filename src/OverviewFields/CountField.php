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

  /**
   * @inheritDoc
   */
  public function id(): string {
    return 'count';
  }

  /**
   * @inheritDoc
   */
  public function label(): string|TranslatableMarkup {
    return t('Page size');
  }

  /**
   * @inheritDoc
   */
  public function getWidgets(): array {
    return ['select' => t('Select list'), 'number' => t('Number field')];
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
      '#options' => $this->overviewManager->getCountOptions($filter->getOverviewId()),
      '#default_value' => $filter->getCount()
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
      'options' => $this->overviewManager->getCountOptions($filter->getOverviewId()),
      'default_value' => $filter->getCount()
    ];
  }

}
