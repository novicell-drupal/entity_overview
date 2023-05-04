<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Type;

use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\transform_api\Annotation\TransformationType;
use Drupal\transform_api\Plugin\Transform\Field\EntityTransform;
use Drupal\transform_api\Transform\PagerTransform;
use Drupal\transform_api\Transform\TransformInterface;
use Drupal\transform_api\TransformationTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @TransformationType(
 *  id = "raw_overview_result",
 *  title = "Raw Entity overview result transform"
 * )
 */
class RawOverviewResult extends TransformationTypeBase {

  protected OverviewManager $overviewManager;

  function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->overviewManager = $overviewManager;
  }

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_overview.manager')
    );
  }

  public function transform(TransformInterface $transform) {
    $overview = $this->overviewManager->getOverview($transform->getValue('overview'));
    $filter = new OverviewFilter($transform->getValue('overview'), $transform->getValues());
    $result = $overview->getOverviewResult($filter);
    $transformation = [
      'type' => 'overview_result'
    ];
    $transformation['content'] = $result->getResult();
    $transformation['totals_text'] = $overview->getTotalsText($result);
    if ($filter->hasPagination()) {
      $transformation['pager'] = new PagerTransform();
    }
    $result->getCacheableMetadata()->applyTo($transformation);
    return $transformation;
  }

}
