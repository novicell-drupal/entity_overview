<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;

interface EngineInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  public function label(): string;

  public function supportsMultipleEntities(): bool;

  public function getBaseFacetForm($entity_bundle, $facet, $default_value): array;

  public function getBaseFacets($overview_id): array;

  public function getResult($overview_id, array $filter = [], $page = 0);

  public function getEntities($overview_id, array $filter = [], $page = 0);

  public function getEntitiesTotal($overview_id, array $filter = [], $shown = 0);

  public function getSortCriterias();

  public function getShowTotalOptions();

}
