<?php

namespace Drupal\entity_overview;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_overview\Entity\Overview;

class OverviewResult implements OverviewResultInterface {

  protected Overview $overview;

  protected OverviewFilter $filter;

  protected EngineInterface $engine;

  protected int $shown = 0;

  public function __construct(OverviewFilter $filter) {
    $this->filter = $filter;
    $this->overview = $filter->getOverview();
    $this->engine = $this->overview->getEngine();
  }

  /**
   * @inheritDoc
   */
  public function getResult() {
    return $this->engine->getResult($this->filter);
  }

  /**
   * @inheritDoc
   */
  public function getEntities(): array {
    $entities = $this->engine->getEntities($this->filter);
    $this->shown = count($entities);
    return $entities;
  }

  /**
   * @inheritDoc
   */
  public function getTotalsText() {
    return $this->engine->getEntitiesTotal($this->filter, $this->shown);
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
