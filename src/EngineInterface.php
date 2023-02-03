<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\Entity\Overview;

interface EngineInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Returns an administrative presentable label for the engine.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function label(): string|TranslatableMarkup;

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

  /**
   * Does the engine support a certain base field?
   *
   * @param string $field Field ID.
   *
   * @return bool
   */
  public function supportsBaseField(string $field): bool;

  /**
   * Returns an array of field names that the engine supports based on selected entity types and bundles.
   *
   * @param array $entity_bundles Selected entity types and bundles.
   *
   * @return array
   */
  public function getSupportedFields(array $entity_bundles = []): array;

  /**
   * Returns an array of supported sorting fields based on selected entity types and bundles.
   *
   * @param array $entity_bundles Selected entity types and bundles.
   *
   * @return array Array keyed by id and translated labels as values
   */
  public function getSupportedSortFields(array $entity_bundles = []): array;

  /**
   * Returns an array of supported sorting criteria by the engine.
   *
   * @return array Array keyed by id and translated labels as values
   */
  public function getSortCriterias(): array;

  /**
   * Returns info about a supported field.
   *
   * @param \Drupal\entity_overview\Entity\Overview $overview Overview configuration.
   * @param string $field Field ID.
   *
   * @return \Drupal\entity_overview\OverviewFieldInfoInterface|null Returns field info or NULL if field not found.
   */
  public function getFieldInfo(Overview $overview, string $field): ?OverviewFieldInfoInterface;

  /**
   * Returns an OverviewResult object with results of the search based on the filter settings.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter Filter settings for the search.
   *
   * @return \Drupal\entity_overview\OverviewResultInterface Result object.
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface;
}
