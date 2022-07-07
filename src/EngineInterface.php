<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;

interface EngineInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  public function getCacheableMetadata($entity_bundle, bool $has_facets): CacheableMetadata;

  public function getBaseFacets($entity_bundle): array;

  public function getBaseFacetForm($entity_bundle, $facet, $default_value): array;

  public function getResult($entity_bundle, array $filter = [], $page = 0);

  public function getEntities($entity_bundle, array $filter = [], $page = 0);

  public function getEntitiesTotal($entity_bundle, array $filter = [], $shown = 0);

  public function getSortCriterias();

  public function getShowTotalOptions();

}
