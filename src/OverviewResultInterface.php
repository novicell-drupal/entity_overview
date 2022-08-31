<?php

namespace Drupal\entity_overview;

use Drupal\Core\Cache\CacheableMetadata;

interface OverviewResultInterface {

  /**
   * Returns the raw result data from the engine.
   *
   * @return mixed
   */
  public function getResult();

  /**
   * Returns search result as entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[] Array of entities.
   */
  public function getEntities(): array;

  /**
   * Get "total" text for the search result.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getTotalsText();

  /**
   * Returns an array of search term recommendations.
   *
   * @return array
   */
  public function getRecommendations(): array;

  /**
   * Get the cache metadata for the search result.
   *
   * @return CacheableMetadata
   */
  public function getCacheableMetadata(): CacheableMetadata;

}
