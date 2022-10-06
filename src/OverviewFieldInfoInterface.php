<?php
namespace Drupal\entity_overview;

use Drupal\Core\StringTranslation\TranslatableMarkup;

interface OverviewFieldInfoInterface {

  public function id(): string;
  public function label(): string|TranslatableMarkup;
  public function getWidgets(): array;
  public function isBase(): bool;
  public function canBeExposed(): bool;
  public function requiresFacets(): bool;
  public function getFieldFormElement(OverviewFilter $filter): array;
  public function updateFieldFormElementDefaultValue($value): mixed;
  public function getFieldFormTransform(OverviewFilter $filter): array;

}
