<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\Entity\Overview;

interface EngineInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  public function label(): string;

  /**
   * Does the engine support multiple entity types at once?
   *
   * @return bool
   */
  public function supportsMultipleEntities(): bool;

  /**
   * Does the engine support delivering search term recommendations?
   *
   * @return bool
   */
  public function supportsSearchTermRecommendations(): bool;

  public function supportsBaseField(string $field): bool;

  public function getSupportedFields(array $entity_bundles = []): array;

  public function getSupportedSortFields(array $entity_bundles = []): array;

  public function getSortCriterias(): array;

  public function getFieldInfo(Overview $overview, string $field): array;

  public function getFieldFormElement(OverviewFilter $filter, string $field): array;

  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface;
}
