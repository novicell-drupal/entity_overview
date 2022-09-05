<?php

namespace Drupal\entity_overview;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_overview\Plugin\EntityOverview\Engine\EntityQueryEngine;

class EntityQueryOverviewResult implements OverviewResultInterface {

  protected EntityQueryEngine $engine;

  protected OverviewFilter $filter;

  protected array $ids = [];

  protected array $entities = [];

  protected array $counts = [];

  public function __construct(EntityQueryEngine $engine, OverviewFilter $filter) {
    $this->engine = $engine;
    $this->filter = $filter;
  }

  /**
   * @inheritDoc
   */
  public function getResult() {
    if (empty($this->ids)) {
      $this->ids = $this->engine->getResult($this->filter);
      $this->counts['shown'] = count($this->ids);
    }
    return $this->ids;
  }

  /**
   * @inheritDoc
   */
  public function getEntities(): array {
    if (empty($this->entities)) {
      $ids = $this->getResult();
      if (!empty($ids)) {
        $this->entities = $this->engine->loadEntities($this->filter, $ids);
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
