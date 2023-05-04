<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Route;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Url;
use Drupal\entity_overview\OverviewFilter;
use Drupal\transform_api\RouteTransformBase;
use Drupal\transform_api\Transform\TransformInterface;

abstract class OverviewRouteBase extends RouteTransformBase {

  public function transform(TransformInterface $transform): array {
    $filter = $this->getFilter();
    if (empty($filter)) {
      return [];
    }
    $endpoint = $this->getEndpoint($filter);

    $transformation = [
      'type' => 'overview_form',
      'overview' => $filter->getOverviewId(),
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
    $transformation['content'] = $this->getContent($filter);

    return $transformation;
  }

  abstract protected function getFilter(): ?OverviewFilter;
  abstract protected function getEndpoint(OverviewFilter $filter): Url;

  abstract protected function getContent(OverviewFilter $filter);

}
