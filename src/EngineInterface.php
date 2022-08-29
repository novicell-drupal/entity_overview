<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\Entity\Overview;

interface EngineInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  public function label(): string;

  public function supportsMultipleEntities(): bool;

  public function supportsBaseField(string $field): bool;

  public function getSupportedFields(array $entity_bundles = []): array;

  public function getSupportedSortFields(array $entity_bundles = []): array;

  public function getSortCriterias(): array;

  public function getShowTotalOptions(): array;

  public function getFieldInfo(Overview $overview, string $field): array;

  public function getFieldFormElement(OverviewFilter $filter, string $field): array;

  public function getResult(OverviewFilter $filter);

  /**
   * Returns search result as entities.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  public function getEntities(OverviewFilter $filter): array;

  /**
   * Get "total" text for the search result.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   * @param int $shown
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getEntitiesTotal(OverviewFilter $filter, int $shown = 0): string|\Drupal\Core\StringTranslation\TranslatableMarkup;

  public function getCacheableMetadata(OverviewFilter $filter, bool $has_facets): CacheableMetadata;
}
