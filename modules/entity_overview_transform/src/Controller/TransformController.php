<?php

namespace Drupal\entity_overview_transform\Controller;

use Drupal\Core\Http\RequestStack;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\entity_overview_transform\Transform\OverviewResultTransform;
use Drupal\transform_api\Transformer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TransformController extends \Drupal\Core\Controller\ControllerBase {
  protected Transformer $transformer;
  protected OverviewManager $overviewManager;
  protected Request $request;

  function __construct(Transformer $transformer, OverviewManager $overviewManager, RequestStack $requestStack) {
    $this->transformer = $transformer;
    $this->overviewManager = $overviewManager;
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('transform_api.transformer'),
      $container->get('entity_overview.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * @param $overview
   * @param string $view_mode
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function overviewResult($overview, $view_mode = 'default'): JsonResponse {
    $overview = $this->overviewManager->getOverview($overview);
    $filter = new OverviewFilter($overview, $this->request->query->all());
    $filter->setViewMode($view_mode);
    $transform = new OverviewResultTransform($filter);
    return new JsonResponse($this->transformer->transformRoot($transform));
  }

}
