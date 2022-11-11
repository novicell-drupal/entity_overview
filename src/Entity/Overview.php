<?php
namespace Drupal\entity_overview\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_overview\EngineInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewInterface;
use Drupal\entity_overview\OverviewResultInterface;

/**
 * Defines the overview entity.
 *
 * @ConfigEntityType(
 *   id = "entity_overview",
 *   label = @Translation("Overview"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_overview\OverviewListBuilder",
 *     "form" = {
 *       "default" = "Drupal\entity_overview\Form\OverviewEditForm",
 *       "add" = "Drupal\entity_overview\Form\OverviewEditForm",
 *       "edit" = "Drupal\entity_overview\Form\OverviewEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "overview",
 *   admin_permission = "configure entity overview",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "entity_bundles",
 *     "fields",
 *     "engine_id",
 *     "sort_field",
 *     "show_total"
 *   },
 *   lookup_keys = {
 *     "engine"
 *   },
 *   links = {
 *     "add-page" = "/admin/config/content/entity_overview/overview/add",
 *     "collection" = "/admin/config/content/entity_overview/overview",
 *     "edit-form" = "/admin/config/content/entity_overview/overview/{entity_overview}",
 *     "delete-form" = "/admin/config/content/entity_overview/overview/{entity_overview}/delete"
 *   }
 * )
 */
class Overview extends ConfigEntityBase implements OverviewInterface {

  use StringTranslationTrait;

  /**
   * The Content notify rule ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Content notify rule label.
   *
   * @var string
   */
  protected $label;

  /**
   * Target entity types and target bundles.
   *
   * @var array
   */
  protected $entity_bundles = [];

  /**
   * @var array
   */
  protected $fields = [];

  /**
   * The engine used to drive this overview.
   *
   * @var string
   */
  protected $engine_id = '';

  /**
   * @var EngineInterface
   */
  protected $engine = NULL;

  /**
   * @var string
   */
  protected $sort_field = '';

  /**
   * @var string
   */
  protected $show_total = '';

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    foreach ($this->getEntityBundles() as $entity_type_id => $bundles) {
      $definition = $this->entityTypeManager()
        ->getDefinition($entity_type_id);
      $this->addDependency('module', $definition->getProvider());
      foreach ($bundles as $bundle) {
        $dependency = $definition->getBundleConfigDependency($bundle);
        $this->addDependency($dependency['type'], $dependency['name']);
      }
    }
    // TODO: add target dependency
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getShowTotal(): string {
    return $this->show_total;
  }

  /**
   * {@inheritdoc}
   */
  public function setShowTotal($show_total): self {
    $this->show_total = $show_total;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortField(): string {
    return $this->sort_field;
  }

  /**
   * {@inheritdoc}
   */
  public function setSortField($sort_field): self {
    $this->sort_field = $sort_field;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields(): array {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidget(string $field): string {
    return $this->fields[$field];
  }

  /**
   * {@inheritdoc}
   */
  public function setFields($fields): self {
    $this->fields = $fields;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundles(): array {
    if (is_array($this->entity_bundles)) {
      return $this->entity_bundles;
    } else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityBundles(array $entity_bundles): self {
    $this->entity_bundles = $entity_bundles;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEngineID(): string {
    return $this->engine_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setEngineID($engine_id) {
    $this->engine_id = $engine_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEngine(): ?EngineInterface {
    if (is_null($this->engine) && !empty($this->engine_id)) {
      /** @var \Drupal\entity_overview\EngineManager $engineManager */
      $engineManager = \Drupal::service('plugin.entity_overview.engine');
      $this->engine = $engineManager->createInstance($this->engine_id);
    }
    return $this->engine;
  }

  /**
   * Generate an array with field names as key and labels as value.
   *
   * @param array $entity_bundles
   *
   * @return array
   */
  public function getSupportedFieldsWithLabels(array $entity_bundles): array {
    $fields = $this->getEngine()->getSupportedFields($entity_bundles);
    $options = [];
    foreach ($fields as $field) {
      $info = $this->getEngine()->getFieldInfo($this, $field);
      if (!is_null($info)) {
        $options[$field] = $this->getEngine()
          ->getFieldInfo($this, $field)
          ->label();
      } else {
        $options[$field] = $this->t('Missing');
      }
    }
    return $options;
  }

  /**
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return \Drupal\entity_overview\OverviewResultInterface
   */
  public function getOverviewResult(OverviewFilter $filter) {
    return $this->getEngine()->getOverviewResult($filter);
  }

  public function getTotalsText(OverviewResultInterface $result) {
    return match ($this->getShowTotal()) {
      'results' => $this->formatPlural($result->getResultsCount(), '1 result found', '@count results found'),
      'shown' => $this->formatPlural($result->getShownCount(), 'Showing 1 out of @total', 'Showing @count out of @total', ['@total' => $result->getResultsCount()]),
      'filtered' => $this->formatPlural($result->getResultsCount(), 'Showing 1 out of @total', 'Showing @count out of @total', ['@total' => $result->getTotalCount()]),
      default => '',
    };
  }
}
