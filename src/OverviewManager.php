<?php
namespace Drupal\entity_overview;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class OverviewManager {

  use StringTranslationTrait;

  /**
   * @var EntityStorageInterface
   */
  protected $taxonomyStorage;

  /**
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\entity_overview\EngineManager
   */
  protected $engineManager;

  function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EngineManager $engineManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->taxonomyStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->configFactory = $configFactory;
    $this->engineManager = $engineManager;
  }

  /**
   * @return array
   */
  public function getOverviewConfigs() {
    $list = $this->configFactory->listAll('entity_overview.');
    $result = [];
    foreach ($list as $config_id) {
      $config = $this->configFactory->get($config_id);
      $result[$config->get('id')] = $config->get('label') ?? $config_id;
    }
    return $result;
  }

  /**
   * @param string $overview_id
   *
   * @return array
   * @deprecated Use getOverviewConfig() instead.
   */
  public function getEntityBundleConfig($entity_bundle) {
    return $this->getOverviewConfig($entity_bundle);
  }

  /**
   * @param string $overview_id
   *
   * @return array
   */
  public function getOverviewConfig($overview_id) {
    $configs = &drupal_static(__FUNCTION__, []);
    if (!empty($configs[$overview_id])) {
      return $configs[$overview_id];
    }

    $configs[$overview_id] = $this->configFactory->get('entity_overview.' . $overview_id)->getRawData();
    return $configs[$overview_id];
  }

  /**
   * @param $overview_id
   * @return array
   */
  public function getFieldFormElements($overview_id) {
    // TODO: Get more information from field definitions and cache it
    $config = $this->getOverviewConfig($overview_id);

    $elements = [];
    foreach ($config['entity_bundles'] as $entity_type_id => $bundle_ids) {
      foreach ($bundle_ids as $bundle_id) {
        /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
        $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
        foreach ($config['fields'] as $field_name => $element_type) {
          if (!isset($elements[$field_name]) && !empty($definitions[$field_name])) {
            $elements[$field_name] = $this->getFieldFormElement($definitions[$field_name], $element_type);
          }
        }
      }
    }

    return $elements;
  }

  /**
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   * @param string $element_type
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFieldFormElement(FieldDefinitionInterface $definition, $element_type) {
    $element = [
      'form_element' => $element_type,
    ];

    switch ($definition->getType()) {
      case 'entity_reference':
        $settings = $definition->getSettings() ?? [];
        $storage = $this->entityTypeManager->getStorage($settings['target_type']);
        // TODO: Support more entity types than taxonomy
        if ($settings['target_type'] == 'taxonomy_term') {
          if (count($settings['handler_settings']['target_bundles']) == 1) {
            $element['label'] = $definition->getLabel();
            $element['source'] = 'taxonomy_term';
            $element['options'] = [];
            $vid = reset($settings['handler_settings']['target_bundles']);
            $element['vid'] = $vid;
            $query = $storage->getQuery();
            $query->condition('vid', $vid)
              ->sort($settings['handler_settings']['sort']['field'], $settings['handler_settings']['sort']['direction']);
            $tids = $query->execute();
            $terms = $storage->loadMultiple($tids);
            foreach ($terms as $term) {
              $element['options'][$term->id()] = $term->label();
            }
          } else {
            \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field_name]);
            return [];
          }
        } else {
          \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field_name]);
          return [];
        }
        break;
      default:
        \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field_name]);
        return [];
    }
    return $element;
  }

  /**
   * Returns list of all entity types that is supported for overviews.
   *
   * @return array
   */
  public function getSupportedEntityTypes() {
    $types = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if (!$entity_type->hasViewBuilderClass() || !$entity_type->isCommonReferenceTarget() || !$entity_type->hasRouteProviders() || $entity_type->getBundleEntityType() == NULL) {
        continue;
      }

      $bundles = [];
      foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type->id()) as $bundle_id => $bundle) {
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

  public function getCountOptions() {
    return [
      5 => '5',
      10 => '10',
      15 => '15',
      20 => '20',
      25 => '25'
    ];
  }

  /**
   * Get list of supported sorting criteria
   *
   * @return array
   */
  public function getSortCriterias($overview_id) {
    return $this->getEngine($overview_id)->getSortCriterias();
  }

  /**
   * @return array
   */
  public function getViewModes() {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $repository */
    $repository = \Drupal::service('entity_display.repository');
    return $repository->getViewModeOptionsByBundle('node', 'page');
  }

  /**
   * @param string $overview_id
   *
   * @return string
   */
  public function getLabel($overview_id) {
    return $this->getOverviewConfig($overview_id)['label'] ?? $overview_id;
  }

  /**
   * @param string $overview_id
   *
   * @return string|null
   * @deprecated Use getEntityTypesAndBundles() instead
   */
  public function getEntityTypeID($overview_id) {
    $entity_types = $this->getEntityTypesAndBundles($overview_id);
    if (count($entity_types) !== 1) {
      return NULL;
    }
    $entity_type_id = array_key_first($entity_types);
    if (count($entity_types[$entity_type_id]) !== 1) {
      return NULL;
    }
    return $entity_type_id;
  }

  /**
   * @param string $overview_id
   *
   * @return string|null
   * @deprecated Use getEntityTypesAndBundles() instead
   */
  public function getBundle($overview_id) {
    $entity_type_id = $this->getEntityTypeID($overview_id);
    if (empty($entity_type_id)) {
      return NULL;
    }
    $entity_types = $this->getEntityTypesAndBundles($overview_id);
    return reset($entity_types[$entity_type_id]) ?? NULL;
  }

  /**
   * @param string $overview_id
   *
   * @return array
   */
  public function getEntityTypesAndBundles($overview_id) {
    return $this->getOverviewConfig($overview_id)['entity_bundles'] ?? [];
  }

  /**
   * @param string $overview_id
   *
   * @return string
   */
  public function getSortField($overview_id) {
    return $this->getOverviewConfig($overview_id)['sort_field'] ?? 'created';
  }

  /**
   * @param string $overview_id
   *
   * @return string
   */
  public function getShowTotal($overview_id) {
    return $this->getOverviewConfig($overview_id)['show_total'] ?? '';
  }

  /**
   * @param $overview_id
   *
   * @return \Drupal\entity_overview\EngineInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getEngine($overview_id) {
    $engines = &drupal_static(__FUNCTION__, []);
    if (empty($engines[$overview_id])) {
      $engine = $this->getOverviewConfig($overview_id)['engine'] ?? 'entity_query';
      $engines[$overview_id] = $this->engineManager->createInstance($engine);
    }
    return $engines[$overview_id];
  }

  /**
   * @param string $overview_id
   * @param array $filter
   * @param int $page
   *
   * @return mixed
   */
  public function getResult($overview_id, array $filter = [], $page = 0) {
    return $this->getEngine($overview_id)->getResult($overview_id, $filter, $page);
  }

  /**
   * @param string $overview_id
   * @param array $filter
   * @param int $page
   *
   * @return EntityInterface[]
   */
  public function getEntities($overview_id, array $filter = [], $page = 0) {
    return $this->getEngine($overview_id)->getEntities($overview_id, $filter, $page);
  }

  /**
   * @param string $overview_id
   * @param array $filter
   * @param int $shown
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getEntitiesTotal($overview_id, array $filter = [], $shown = 0) {
    return $this->getEngine($overview_id)->getEntitiesTotal($overview_id, $filter, $shown);
  }

  /**
   * Whether deeplinks are enabled on the site.
   *
   * @return bool
   */
  public function deepLinksEnabled() {
    // TODO: Make deeplink functionality optional
    return TRUE;
  }

  public function getBaseFacets($overview_id): array {
    return $this->getEngine($overview_id)->getBaseFacets($overview_id);
  }

  public function getBaseFacetForm($entity_bundle, $facet, ?string $default_value): array {
    return $this->getEngine($entity_bundle)->getBaseFacetForm($entity_bundle, $facet, $default_value);
  }

  public function getCacheableMetadata($overview_id, bool $has_facets): CacheableMetadata {
    return $this->getEngine($overview_id)->getCacheableMetadata($overview_id, $has_facets);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   * @param string $view_mode
   *
   * @return array
   */
  public function buildEntitiesWithViewmode(array $entities, $view_mode) {
    $types = [];
    foreach ($entities as $key => $entity) {
      if (!isset($types[$entity->getEntityTypeId()])) {
        $types[$entity->getEntityTypeId()] = [];
      }
      $types[$entity->getEntityTypeId()][$entity->id()] = $entity;
    }
    $views = [];
    foreach ($types as $entity_type_id => $entities) {
      $views[$entity_type_id] = $this->entityTypeManager->getViewBuilder($entity_type_id)->viewMultiple($entities, $view_mode);
    }
    $build = [];
    foreach ($entities as $key => $entity) {
      $build[$key] = $views[$entity->getEntityTypeId()][$entity->id()];
    }
    return $build;
  }

}
