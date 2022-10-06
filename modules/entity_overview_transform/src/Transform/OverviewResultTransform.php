<?php

namespace Drupal\entity_overview_transform\Transform;

use Drupal\entity_overview\OverviewFilter;
use Drupal\transform_api\Transform\PluginTransformBase;

class OverviewResultTransform extends PluginTransformBase {

  public function __construct(OverviewFilter $filter) {
    $this->values = $filter->toArray();
    $this->addCacheableDependency($filter->getOverview());
  }

  public function getTransformType() {
    return 'overview_result';
  }
}
