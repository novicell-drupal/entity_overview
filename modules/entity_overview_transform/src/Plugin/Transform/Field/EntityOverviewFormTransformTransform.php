<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview_transform\Transform\OverviewResultTransform;

/**
 * @FieldTransform(
 *  id = "entity_overview_form",
 *  label = @Translation("Entity overview form"),
 *  field_types = {
 *    "overview_filter"
 *  }
 * )
 */
class EntityOverviewFormTransformTransform extends EntityOverviewListTransform {

  public function transformElements(FieldItemListInterface $items, $langcode) {
    $overview_id = $items->getSetting('overview');

    $values = [];
    foreach ($items as $delta => $item) {
      $filter = new OverviewFilter($overview_id, $item->getValue());
      $filter->setViewMode($this->getSetting('transform_mode'));
      $endpoint = Url::fromRoute('entity_overview_transform.overview_result.transform_mode', ['overview' => $overview_id, 'transform_mode' => $filter->getViewMode()]);
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
