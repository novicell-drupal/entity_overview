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
   * Get amount of hits shown for the search result.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getShownCount();

  /**
   * Get the count of hits for the search result.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getResultsCount();

  /**
   * Get the total count of possible hits for the search result.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getTotalCount();

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
