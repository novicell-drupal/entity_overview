<?php
namespace Drupal\entity_overview;

use Drupal\Core\StringTranslation\TranslatableMarkup;

interface OverviewFieldInfoInterface {

  /**
   * Returns the id of the field.
   *
   * @return string
   */
  public function id(): string;

  /**
   * Returns a label for the field.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function label(): string|TranslatableMarkup;

  /**
   * Returns an associative array of supported widget types with labels.
   *
   * @return array
   */
  public function getWidgets(): array;

  /**
   * Returns whether the field is a base field.
   *
   * @return bool
   */
  public function isBase(): bool;

  /**
   * Tells whether the field is allowed to be exposed to the end users.
   *
   * @return bool
   */
  public function canBeExposed(): bool;

  /**
   * Whether field requires facets to be enabled in order to function.
   *
   * @return bool
   */
  public function requiresFacets(): bool;

  /**
   * Returns a form element for display in backend or frontend facets.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *   The current filter values.
   *
   * @return array
   */
  public function getFieldFormElement(OverviewFilter $filter): array;

  /**
   * Modifies the form element with certain attributes.
   *
   * @param array $form The form that needs to be changed.
   * @param $attribute string The attribute that needs to be changed. Can be ajax, description or default_value.
   * @param $value mixed The value that the attribute needs to be changed to.
   */
  public function setFieldFormElementAttribute(array &$form, $attribute, $value): void;

  /**
   * Converts OverviewFilter value into form element value.
   *
   * @param mixed $value
   *
   * @return mixed
   */
  public function updateFieldFormElementDefaultValue($value): mixed;

  /**
   * Converts form state value into OverviewFilter value.
   *
   * @param mixed $value
   *
   * @return mixed
   */
  public function getFilterValueFromFormStateValue($value): mixed;

  /**
   * Return the field information as a transform.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return array
   */
  public function getFieldFormTransform(OverviewFilter $filter): array;

}
