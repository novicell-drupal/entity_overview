<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

interface EngineInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  public function getResult($entity_bundle, array $filter = [], $page = 0);

  public function getEntities($entity_bundle, array $filter = [], $page = 0);

  public function getEntitiesTotal($entity_bundle, array $filter = [], $shown = 0);

  public function getSortCriterias();

  public function getShowTotalOptions();

}
