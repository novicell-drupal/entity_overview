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

  /**
   * Does the engine support multiple entity types at once?
   *
   * @return bool
   */
  public function supportsMultipleEntities(): bool;

  /**
   * Does the engine support delivering search term recommendations?
   *
   * @return bool
   */
  public function supportsSearchTermRecommendations(): bool;

  public function supportsBaseField(string $field): bool;

  public function getSupportedFields(array $entity_bundles = []): array;

  public function getSupportedSortFields(array $entity_bundles = []): array;

  public function getSortCriterias(): array;

  public function getShowTotalOptions(): array;

  public function getFieldInfo(Overview $overview, string $field): array;

  public function getFieldFormElement(OverviewFilter $filter, string $field): array;

  public function getResultObject(OverviewFilter $filter): OverviewResultInterface;

  /**
   * Returns the raw result data from the engine.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter Filter values for the search.
   *
   * @return mixed
   *
   * @deprecated Use getResultObject instead.
   */
  public function getResult(OverviewFilter $filter);

  /**
   * Returns search result as entities.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter Filter values for the search.
   *
   * @return \Drupal\Core\Entity\EntityInterface[] Array of entities.
   *
   * @deprecated Use getResultObject instead.
   */
  public function getEntities(OverviewFilter $filter): array;

  /**
   * Get "total" text for the search result.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter Filter values for the search.
   * @param int $shown How many items were shown.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *
   * @deprecated Use getResultObject instead.
   */
  public function getEntitiesTotal(OverviewFilter $filter, int $shown = 0);

  /**
   * Get the cache metadata for the search result.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter Filter values for the search.
   * @param bool $has_facets Whether the search has exposed facets.
   *
   * @return CacheableMetadata
   *
   * @deprecated Use getResultObject instead.
   */
  public function getCacheableMetadata(OverviewFilter $filter, bool $has_facets): CacheableMetadata;
}
