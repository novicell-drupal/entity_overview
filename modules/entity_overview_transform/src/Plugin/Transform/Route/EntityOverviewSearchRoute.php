<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Route;


use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Url;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview_transform\Transform\OverviewResultTransform;
use Drupal\transform_api\RouteTransformBase;
use Drupal\transform_api\Transform\TransformInterface;

/**
 * @RouteTransform(
 *  id = "entity_overview_search.search",
 *  title = "Search"
 * )
 */
class EntityOverviewSearchRoute extends OverviewRouteBase {

  protected function getConfig(): ImmutableConfig {
    return \Drupal::config('entity_overview_search.settings');
  }

  protected function getFilter(): ?OverviewFilter {
    $config = $this->getConfig();
    $overview_id = $config->get('overview') ?? NULL;
    if (empty($overview_id)) {
      return NULL;
    }
    $filter = new OverviewFilter($overview_id, $config->get('filter') ?? []);
    $overview = $filter->getOverview();
    $filter->setPagination(TRUE);
    $filter->setShowTotal($overview->getShowTotal());
    $filter->fetchRequestValues(\Drupal::request());
    return $filter;
  }

  protected function getEndpoint(OverviewFilter $filter): Url {
    return OverviewResultTransform::buildUrl($filter);
  }

  protected function getContent(OverviewFilter $filter) {
    return new OverviewResultTransform($filter);
  }
}
