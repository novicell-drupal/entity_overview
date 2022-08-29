<?php
namespace Drupal\entity_overview;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\entity_overview\EngineInterface;

interface OverviewInterface extends ConfigEntityInterface {

  /**
   * @param array $fields
   *
   * @return $this
   */
  public function setFields($fields);

  /**
   * @return array
   */
  public function getFields(): array;

  /**
   * @param array $entity_bundles
   *
   * @return $this
   */
  public function setEntityBundles(array $entity_bundles);

  /**
   * @return array
   */
  public function getEntityBundles(): array;

  /**
   * @param string $type
   * @param array $recipients
   *
   * @return $this
   */
  public function setEngineID($engine_id);

  /**
   * @return string
   */
  public function getEngineID(): string;

    /**
   * @return \Drupal\entity_overview\EngineInterface|null
   */
  public function getEngine(): ?EngineInterface;

  /**
   * @return string
   */
  public function getShowTotal(): string;

  /**
   * @param string $show_total
   *
   * @return $this
   */
  public function setShowTotal($show_total): self;

  /**
   * @return string
   */
  public function getSortField(): string;

  /**
   * @param string $sort_field
   *
   * @return $this
   */
  public function setSortField($sort_field): self;

  /**
   * Generate an array with field names as key and labels as value.
   *
   * @param array $entity_bundles
   *
   * @return array
   */
  public function getSupportedFieldsWithLabels(array $entity_bundles): array;

}
