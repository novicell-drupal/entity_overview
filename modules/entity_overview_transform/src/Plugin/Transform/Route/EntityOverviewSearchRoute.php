<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Route;


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
class EntityOverviewSearchRoute extends RouteTransformBase {

  public function transform(TransformInterface $transform): array {
    $config = \Drupal::config('entity_overview_search.settings');
    $overview_id = $config->get('overview') ?? NULL;
    if (empty($overview_id)) {
      return [];
    }
    $filter = new OverviewFilter($overview_id, $config->get('filter') ?? []);
    $overview = $filter->getOverview();
    $filter->setPagination(TRUE);
    $filter->setShowTotal($overview->getShowTotal());
    $filter->fetchRequestValues(\Drupal::request());

    $query = ['facets' => $filter->getFacets(), 'pagination' => TRUE];
    if (!$filter->hasFacet('count')) {
      $query['count'] = $filter->getCount();
    }
    if (!$filter->hasFacet('sort')) {
      $query['sort'] = $filter->getSort();
    }
    $endpoint = Url::fromRoute(
      'entity_overview_transform.overview_result.transform_mode',
      ['overview' => $overview_id, 'transform_mode' => $filter->getViewMode()],
      ['query' => $query]
    );

    $transformation = [
      'type' => 'overview_form',
      'overview' => $overview_id,
      'endpoint' => $endpoint->toString(),
      'initial' => $filter->toArray(),
      'facets' => []
    ];
    /** @var \Drupal\entity_overview\OverviewManager $overviewManager */
    $overviewManager = \Drupal::service('entity_overview.manager');
    foreach ($overviewManager->getAllFieldInfos($filter->getOverview()) as $field => $info) {
      if ($filter->hasFacet($field)) {
        $transformation['facets'][$field] = $info->getFieldFormTransform($filter);
      }
    }
    $transformation['content'] = new OverviewResultTransform($filter);

    return $transformation;
  }

}
