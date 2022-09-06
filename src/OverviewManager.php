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
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_overview\Entity\Overview;

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

  /**
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  private ModuleHandler $moduleHandler;

  protected const baseFields = [
    'count',
    'sort',
    'pagination'
  ];

  function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EngineManager $engineManager, ModuleHandler $moduleHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->taxonomyStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->configFactory = $configFactory;
    $this->engineManager = $engineManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * @return array
   */
  public function getOverviewConfigs() {
    $overviews = Overview::loadMultiple();
    $result = [];
    foreach ($overviews as $overview) {
      $result[$overview->id()] = $overview->label();
    }
    return $result;
  }

  /**
   * @param string $overview_id
   *
   * @return \Drupal\entity_overview\Entity\Overview
   */
  public function getOverview($overview_id) {
    $overviews = &drupal_static(__FUNCTION__, []);
    if (!empty($overviews[$overview_id])) {
      return $overviews[$overview_id];
    }

    $overviews[$overview_id] = Overview::load($overview_id);
    return $overviews[$overview_id];
  }

  /**
   * Returns list of all existing base fields.
   *
   * @return string[]
   */
  public function getBaseFields(): array {
    return self::baseFields;
  }

  /**
   * Returns info about a base field.
   *
   * @param string $field
   *
   * @return array
   */
  public function getBaseFieldInfo(Overview $overview, string $field): array {
    return match ($field) {
      'sort' => [
          'label' => $this->t('Sort select'),
          'widgets' => ['select'],
          'base' => TRUE,
          'requires facets' => FALSE,
          'can be exposed' => TRUE
        ],
      'count' => [
          'label' => $this->t('Page size select'),
          'widgets' => ['select'],
          'base' => TRUE,
          'requires facets' => FALSE,
          'can be exposed' => TRUE
        ],
      'pagination' => [
          'label' => $this->t('Pagination'),
          'widgets' => ['checkbox'],
          'base' => TRUE,
          'requires facets' => TRUE,
          'can be exposed' => FALSE
        ],
      default => []
    };
  }

  public function getBaseFieldElement(OverviewFilter $filter, string $field) {
    return match ($field) {
      'sort' => [
        '#type' => 'select',
        '#title' => $this->t('Sort select'),
        '#options' => $filter->getOverview()->getEngine()->getSortCriterias(),
        '#default_value' => $filter->getSort()
      ],
      'count' => [
        '#type' => 'select',
        '#title' => $this->t('Page size select'),
        '#options' => $this->getCountOptions($filter->getOverviewId()),
        '#default_value' => $filter->getCount()
      ],
      'pagination' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Pagination'),
        '#description' => $this->t('Display pager at the bottom.'),
        '#default_value' => $filter->hasPagination()
      ],
      default => [],
    };
  }

  /**
   * @param \Drupal\entity_overview\OverviewFilter $filter
   * @param string $field
   *
   * @return array
   */
  public function getFieldFormElement(OverviewFilter $filter, string $field): array {
    if (in_array($field, $this->getBaseFields())) {
      return $this->getBaseFieldElement($filter, $field);
    } else {
      return $filter->getOverview()->getEngine()->getFieldFormElement($filter, $field);
    }
  }

  /**
   * @param \Drupal\entity_overview\Entity\Overview $overview
   *
   * @return array
   */
  public function getAllFieldInfos(Overview $overview) {
    $info = [];
    $defaults = [
      'base' => FALSE,
      'requires facets' => FALSE,
      'can be exposed' => TRUE
    ];
    foreach ($this->getBaseFields() as $field) {
      $info[$field] = $this->getBaseFieldInfo($overview, $field) + $defaults;
    }
    foreach ($overview->getFields() as $field => $widget) {
      $info[$field] = $overview->getEngine()->getFieldInfo($overview, $field) + $defaults;
    }
    return $info;
  }

  public function buildOverviewFilterForm(OverviewFilter $filter, $allow_facets = TRUE) {
    $overview = $filter->getOverview();
    $form = ['fields' => []];
    $field_info = $this->getAllFieldInfos($overview);

    foreach ($field_info as $field => $info) {
      if (!$info['requires facets']) {
        if ($info['base']) {
          $form[$field] = $this->getBaseFieldElement($filter, $field);
        } else {
          $form['fields'][$field] = $overview->getEngine()
            ->getFieldFormElement($filter, $field);
          if (empty($form['fields'][$field]['#description'])) {
            $form['fields'][$field]['#description'] = $this->t('Default values');
          }
        }
      }
    }
    if (empty($form['fields'])) {
      $form['fields'][0] = [
        '#type' => 'hidden',
        '#default_value' => NULL
      ];
    }

    if ($allow_facets) {
      $facets_options = [];
      foreach ($field_info as $field => $info) {
        if ($info['can be exposed']) {
          $facets_options[$field] = $info['label'];
        }
      }
      if (!empty($facets_options)) {
        $form['facets'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Facets'),
          '#description' => $this->t('Select the facets that you want to expose to the user.'),
          '#options' => $facets_options,
          '#default_value' => $filter->getFacets(),
        ];
      }

      foreach ($field_info as $field => $info) {
        if ($info['requires facets']) {
          if ($info['base']) {
            $form[$field] = $this->getBaseFieldElement($filter, $field);
          } else {
            $form['fields'][$field] = $overview->getEngine()
              ->getFieldFormElement($filter, $field);
          }
        }
      }
    } else {
      $form['facets'] = [
        '#type' => 'hidden',
        '#default_value' => '',
      ];
      foreach ($field_info as $field => $info) {
        if ($info['requires facets']) {
          if ($info['base']) {
            $form[$field] = [
              '#type' => 'hidden',
              '#default_value' => FALSE,
            ];
          } else {
            $form['fields'][$field] = [
              '#type' => 'hidden',
              '#default_value' => FALSE,
            ];
          }
        }
      }
    }
    return $form;
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

  public function getCountOptions($overview_id) {
    $options = [
      5 => '5',
      10 => '10',
      15 => '15',
      20 => '20',
      25 => '25'
    ];

    $this->moduleHandler->alter('entity_overview_count_options', $options, $overview_id);
    return $options;
  }

  public function getShowTotalOptions(): array {
    return [
      '' => $this->t('None'),
      'results' => $this->t('Filtered search results'),
      'filtered' => $this->t('Filtered out of total number of items'),
      'shown' => $this->t('Shown items out of filtered number of items'),
    ];
  }

  /**
   * @return array
   */
  public function getViewModes(Overview $overview = NULL) {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $repository */
    $repository = \Drupal::service('entity_display.repository');
    if (empty($overview)) {
      return $repository->getViewModeOptionsByBundle('node', 'page');
    } else {
      return $repository->getViewModeOptionsByBundle('node', 'article');
    }
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
      $engines[$overview_id] = $this->getOverview($overview_id)->getEngine();
    }
    return $engines[$overview_id];
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

  /**
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   * @param string $view_mode
   *
   * @return array
   */
  public function buildEntitiesWithViewmode(array $entities, string $view_mode) {
    $types = [];
    foreach ($entities as $key => $entity) {
      if (!isset($types[$entity->getEntityTypeId()])) {
        $types[$entity->getEntityTypeId()] = [];
      }
      $types[$entity->getEntityTypeId()][$entity->id()] = $entity;
    }

    if (count($types) == 1) {
      $build = $this->entityTypeManager->getViewBuilder(array_key_first($types))->viewMultiple($entities, $view_mode);
    } else {
      $views = [];
      foreach ($types as $entity_type_id => $entity_type_entities) {
        foreach ($entity_type_entities as $key => $entity) {
          $views[$entity_type_id][$key] = $this->entityTypeManager->getViewBuilder($entity_type_id)
            ->view($entity, $view_mode);
        }
      }
      $build = [];
      foreach ($entities as $key => $entity) {
        $build[$key] = $views[$entity->getEntityTypeId()][$entity->id()];
      }
    }
    return $build;
  }

}
