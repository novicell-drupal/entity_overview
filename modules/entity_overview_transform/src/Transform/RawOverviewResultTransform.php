<?php

namespace Drupal\entity_overview_transform\Transform;

use Drupal\Core\Url;
use Drupal\entity_overview\OverviewFilter;
use Drupal\transform_api\Transform\PluginTransformBase;

class RawOverviewResultTransform extends PluginTransformBase {

  public function __construct(OverviewFilter $filter) {
    $this->values = $filter->toArray();
    $this->addCacheableDependency($filter->getOverview());
  }

  public function getTransformType() {
    return 'raw_overview_result';
  }

  public static function buildUrl(OverviewFilter $filter): Url {
    $query = ['facets' => $filter->getFacets(), 'pagination' => $filter->hasPagination()];
    if (!$filter->hasFacet('count')) {
      $query['count'] = $filter->getCount();
    }
    if (!$filter->hasFacet('sort')) {
      $query['sort'] = $filter->getSort();
    }
    return Url::fromRoute(
      'entity_overview_transform.raw_overview_result',
      ['overview' => $filter->getOverviewId()],
      ['query' => $query]
    );
  }
}
