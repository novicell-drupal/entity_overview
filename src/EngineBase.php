<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_overview\Entity\Overview;
use Drupal\entity_overview\OverviewFields\DateField;
use Drupal\entity_overview\OverviewFields\EntityReferenceField;
use Drupal\entity_overview\OverviewFields\OwnerField;
use Drupal\entity_overview\OverviewFields\SearchTextField;
use Drupal\entity_overview\OverviewFields\TaxonomyField;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class EngineBase extends PluginBase implements EngineInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The plugin settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * Whether default settings have been merged into the current $settings.
   *
   * @var bool
   */
  protected $defaultSettingsMerged = FALSE;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\entity_overview\OverviewManager
   */
  protected $overviewManager;

  /**
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  protected $supportedFields = [];
  protected $supportedSortFields = [];

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, KillSwitch $killSwitch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $configuration;
    $this->overviewManager = $overviewManager;
    $this->killSwitch = $killSwitch;
  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_overview.manager'), $container->get('page_cache_kill_switch'));
  }

  /**
   * @inheritDoc
   */
  public function label(): string {
    return $this->t($this->pluginDefinition['title'] ?? 'Engine');
  }

  /**
   * @inheritDoc
   */
  public function supportsMultipleEntities(): bool {
    return $this->pluginDefinition['multiple'] ?? FALSE;
  }

  /**
   * @inheritDoc
   */
  public function supportsSearchTermRecommendations(): bool {
    return $this->pluginDefinition['recommendations'] ?? FALSE;
  }

  /**
   * @inheritDoc
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface {
    return new OverviewResult($filter);
  }

  /**
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return int
   */
  public function getTotalCount(OverviewFilter $filter) {
    $overview = $filter->getOverview();
    $cid = 'entity_overview:' . $overview->id() . '_total';
    $cache = \Drupal::cache()->get($cid);
    if ($cache === FALSE) {
      $total = 0;
      $tags = [];
      try {
        foreach ($overview->getEntityBundles() as $entity_type_id => $bundles) {
          $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
          $keys = $entity_type->getKeys();
          $query = $this->entityTypeManager->getStorage($entity_type_id)
            ->getQuery()
            ->condition($keys['bundle'], $bundles, 'IN')
            ->condition('status', 1)
            ->count();
          $total += $query
            ->accessCheck(TRUE)
            ->execute();
          $tags += $entity_type->getListCacheTags();
        }
      } catch (InvalidPluginDefinitionException $e) {
      } catch (PluginNotFoundException $e) {
      }
      \Drupal::cache()->set($cid, $total, Cache::PERMANENT, $tags);
    } else {
      $total = $cache->data;
    }
    return $total;
  }

  /**
   * Get the cache metadata for the search result.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter Filter settings.
   * @param bool $has_facets Whether the overview has facets.
   *
   * @return CacheableMetadata Caching metadata.
   */
  public function getCacheableMetadata(OverviewFilter $filter, bool $has_facets): CacheableMetadata {
    $cache = new CacheableMetadata();

    if ($has_facets) {
      if ($this->overviewManager->deepLinksEnabled()) {
        $cache->addCacheContexts(['url.query_args']);
        $this->killSwitch->trigger();
      }
    }

    $tags = [];

    foreach ($filter->getOverview()->getEntityBundles() as $entity_type_id => $bundles) {
      $tags = Cache::mergeTags($tags, $this->entityTypeManager->getDefinition($entity_type_id)->getListCacheTags());
    }

    $overview = $filter->getOverview();

    $definitions = $this->getFieldDefinitions($overview);
    $active_fields = array_keys($overview->getFields());

    foreach ($definitions as $definition) {
      if ($definition->getType() == 'entity_reference' && in_array($definition->getName(), $active_fields)) {
        $type = $definition->getSetting('target_type');

        if ($type !== NULL) {
          // Add the list cache tags for each entity type referenced by overview fields.
          $tags = Cache::mergeTags($tags, $this->entityTypeManager->getDefinition($type)->getListCacheTags());
        }
      }
    }

    $cache->addCacheableDependency($filter->getOverview());
    $cache->addCacheTags($tags);

    return $cache;
  }

  /**
   * @inheritDoc
   */
  public function supportsBaseField(string $field): bool {
    return in_array($field, $this->getPluginDefinition()['facets']);
  }

  /**
   * Returns list of supported engine fields.
   *
   * @param array $entity_bundles Selected entity types and bundles.
   *
   * @return array
   */
  protected function getEngineSupportedFields(array $entity_bundles = []): array {
    $fields = [];
    foreach ($this->getPluginDefinition()['facets'] as $field) {
      if (!in_array($field, $this->overviewManager->getBaseFields())) {
        $fields[$field] = $field;
      }
    }
    return $fields;
  }

  /**
   * @inheritDoc
   */
  public function getSupportedFields(array $entity_bundles = []): array {
    if (empty($this->supportedFields)) {
      $this->findSupportedFields($entity_bundles);
    }
    return array_keys($this->supportedFields);
  }

  /**
   * @inheritDoc
   */
  public function getSupportedSortFields(array $entity_bundles = []): array {
    if (empty($this->supportedSortFields)) {
      $this->findSupportedFields($entity_bundles);
    }
    return $this->supportedSortFields;
  }

  /**
   * Finds the supported fields and sorting fields of selected entity types and bundles and caches it.
   *
   * @param array $entity_bundles
   *
   * @return void
   */
  protected function findSupportedFields(array $entity_bundles): void {
    $entity_types = $this->overviewManager->getSupportedEntityTypes();
    $this->supportedFields = $this->getEngineSupportedFields($entity_bundles);
    $this->supportedSortFields = [];
    foreach ($entity_types as $entity_type) {
      $entity_type_id = $entity_type['id'];
      if (empty($entity_bundles[$entity_type_id])) {
        continue;
      }
      foreach ($entity_bundles[$entity_type_id] as $bundle) {
        $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($definitions as $field_name => $definition) {
          if ($definition->getType() == 'entity_reference') {
            $this->supportedFields[$field_name] = $definition->getLabel();
          }
          if (in_array($definition->getType(), [
            'datetime'
          ])) {
            $this->supportedFields[$field_name] = $definition->getLabel();
          }
          if (in_array($definition->getType(), [
            'created',
            'changed',
            'datetime'
          ])) {
            $this->supportedSortFields[$field_name] = $definition->getLabel();
          }
        }
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function getFieldInfo(Overview $overview, string $field): ?OverviewFieldInfoInterface {
    if (in_array($field, $this->getPluginDefinition()['facets'])) {
      return $this->getEngineFieldInfo($overview, $field);
    } else {
      $definition = $this->getFieldDefinitions($overview)[$field];
      if (is_null($definition)) {
        \Drupal::logger('entity_overview')->error('Field %field was not found.', ['%field' => $field]);
        return NULL;
      }
      switch ($definition->getType()) {
        case 'entity_reference':
          $settings = $definition->getSettings() ?? [];
          if ($settings['target_type'] == 'taxonomy_term') {
            if (count($settings['handler_settings']['target_bundles']) == 1) {
              return TaxonomyField::createFromFieldDefinition($definition);
            } else {
              \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
              return NULL;
            }
          } else {
            return EntityReferenceField::createFromFieldDefinition($definition);
          }
        case 'datetime':
          return new DateField($field, $definition->getLabel(), $definition->getSetting('datetime_type'));
        default:
          \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
          return NULL;
      }
    }
  }

  /**
   * Returns info about an engine field.
   *
   * @param \Drupal\entity_overview\Entity\Overview $overview Overview configuration.
   * @param string $field Field ID.
   *
   * @return \Drupal\entity_overview\OverviewFieldInfoInterface|null Returns field info or NULL if field not found.
   */
  protected function getEngineFieldInfo(Overview $overview, string $field): ?OverviewFieldInfoInterface {
    return match ($field) {
      'text' => new SearchTextField(),
      'owner' => new OwnerField(),
      default => NULL
    };
  }

  /**
   * Gets all relevant field definitions for a given Overview configuration.
   *
   * @param \Drupal\entity_overview\Entity\Overview $overview The Overview configuration.
   *
   * @return FieldDefinitionInterface[]
   */
  protected function getFieldDefinitions(Overview $overview): array {
    $definitions = &drupal_static(__FUNCTION__, []);
    if (!isset($definitions[$overview->id()])) {
      $definitions[$overview->id()] = [];
      foreach ($overview->getEntityBundles() as $entity_type_id => $bundle_ids) {
        foreach ($bundle_ids as $bundle_id) {
          /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
          $entityFieldManager = \Drupal::service('entity_field.manager');
          $bundle_definitions = $entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
          foreach ($bundle_definitions as $field_name => $definition) {
            if (!isset($definitions[$overview->id()][$field_name]) && !empty($definition)) {
              $definitions[$overview->id()][$field_name] = $definition;
            }
          }
        }
      }
    }

    return $definitions[$overview->id()];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    // Merge defaults before returning the array.
    if (!$this->defaultSettingsMerged) {
      $this->mergeDefaults();
    }
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key) {
    // Merge defaults if we have no value for the key.
    if (!$this->defaultSettingsMerged && !array_key_exists($key, $this->settings)) {
      $this->mergeDefaults();
    }
    return $this->settings[$key] ?? NULL;
  }

  /**
   * Merges default settings values into $settings.
   */
  protected function mergeDefaults() {
    $this->settings += static::defaultSettings();
    $this->defaultSettingsMerged = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    $this->settings = $settings;
    $this->defaultSettingsMerged = FALSE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($key, $value) {
    $this->settings[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * Returns list of all entity types that is supported for overviews.
   *
   * @return array
   */
  public function getSupportedEntityTypes() {
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo */
    $entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
    $types = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if (!$entity_type->hasViewBuilderClass() || !$entity_type->isCommonReferenceTarget() || !$entity_type->hasRouteProviders() || $entity_type->getBundleEntityType() == NULL) {
        continue;
      }

      $bundles = [];
      foreach ($entityTypeBundleInfo->getBundleInfo($entity_type->id()) as $bundle_id => $bundle) {
        $bundles[$bundle_id] = [
          'id' => $bundle_id,
          'label' => $bundle['label']
        ];
      }

      $types[$entity_type->id()] = [
        'id' => $entity_type->id(),
        'label' => $entity_type->getLabel(),
        'bundles' => $bundles,
      ];
    }

    return $types;
  }

}
