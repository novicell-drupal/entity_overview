<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview_transform\Transform\OverviewResultTransform;
use Drupal\transform_api\FieldTransformBase;

/**
 * @FieldTransform(
 *  id = "entity_overview_form",
 *  title = "Entity overview form",
 *  description = "Filtered entities or exposed form for filtering entities.",
 *  types = {
 *    "overview_filter"
 *  }
 * )
 */
class EntityOverviewForm extends FieldTransformBase {

  public function transformElements(FieldItemListInterface $items, $langcode) {
    $overview_id = $items->getSetting('overview');

    $values = [];
    foreach ($items as $delta => $item) {
      $filter = new OverviewFilter($overview_id, $item->getValue());
      $filter->setViewMode($this->getSetting('view_mode'));
      $endpoint = Url::fromRoute('entity_overview_transform.overview_result.view_mode', ['overview' => $overview_id, 'view_mode' => $filter->getViewMode()]);
      $values[$delta] = [
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
          $values[$delta]['facets'][$field] = $info->getFieldFormTransform($filter);
        }
      }
      $values[$delta]['content'] = new OverviewResultTransform($filter);
    }
    return $values;
  }

}
