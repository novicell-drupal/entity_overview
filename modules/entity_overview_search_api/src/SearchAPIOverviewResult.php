<?php

namespace Drupal\entity_overview_search_api;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\entity_overview_search_api\Plugin\EntityOverview\Engine\SearchAPIEngine;
use Drupal\search_api\Query\ResultSetInterface;

class SearchAPIOverviewResult implements OverviewResultInterface {

  protected SearchAPIEngine $engine;

  protected OverviewFilter $filter;

  protected ResultSetInterface $result;

  protected array $entities = [];

  protected array $counts = [];

  public function __construct(SearchAPIEngine $engine, OverviewFilter $filter) {
    $this->engine = $engine;
    $this->filter = $filter;
  }

  /**
   * @inheritDoc
   */
  public function getResult() {
    if (empty($this->result)) {
      $this->result = $this->engine->getResult($this->filter);
      $this->counts['shown'] = count($this->result->getResultItems());
      $this->counts['results'] = $this->result->getResultCount();
    }
    return $this->result;
  }

  /**
   * @inheritDoc
   */
  public function getEntities(): array {
    if (empty($this->entities)) {
      $result = $this->getResult();
      if (!empty($result)) {
        $this->entities = $this->engine->loadEntities($result);
      }
    }
    return $this->entities;
  }

  /**
   * @inheritDoc
   */
  public function getShownCount() {
    if (!isset($this->counts['shown'])) {
      $this->getResult();
    }
    return $this->counts['shown'];
  }

  /**
   * @inheritDoc
   */
  public function getResultsCount() {
    if (!isset($this->counts['results'])) {
      $this->counts['results'] = $this->engine->getResultsCount($this->filter);
    }
    return $this->counts['results'];
  }

  /**
   * @inheritDoc
   */
  public function getTotalCount() {
    if (!isset($this->counts['total'])) {
      $this->counts['total'] = $this->engine->getTotalCount($this->filter);
    }
    return $this->counts['total'];
  }

  /**
   * @inheritDoc
   */
  public function getRecommendations(): array {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return $this->engine->getCacheableMetadata($this->filter, (count($this->filter->getFacets()) > 0));
  }

}
