<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\transform_api\FieldTransformBase;

/**
 * @FieldTransform(
 *  id = "entity_overview_list",
 *  title = "Entity overview list",
 *  description = "Filtered entities or exposed form for filtering entities.",
 *  types = {
 *    "overview_filter"
 *  }
 * )
 */
class EntityOverviewList extends FieldTransformBase {

  public function transformElements(FieldItemListInterface $items, $langcode) {
    $overview_id = $items->getSetting('overview');

    $values = [];
    foreach ($items as $delta => $item) {
      $filter = new OverviewFilter($overview_id, $item->getValue());
      $filter->setViewMode($this->getSetting('view_mode'));
      $result = $filter->getOverview()->getOverviewResult($filter);
      $values[$delta] = ['entities' => []];
      foreach ($result->getEntities() as $entity) {
        $values[$delta]['entities'][] = new \Drupal\transform_api\Transform\EntityTransform($entity->getEntityTypeId(), $entity->id(), $filter->getViewMode());
      }
    }
    return $values;
  }

}
