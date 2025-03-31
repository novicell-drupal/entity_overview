<?php

namespace Drupal\entity_overview_search_api\Plugin\EntityOverview\Engine;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\EngineBase;
use Drupal\entity_overview\Entity\Overview;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFields\DateField;
use Drupal\entity_overview\OverviewFields\EntityReferenceField;
use Drupal\entity_overview\OverviewFields\TaxonomyField;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\entity_overview_search_api\SearchAPIOverviewResult;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\ResultSetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @Engine(
 *  id = "search_api",
 *  title = "Search API",
 *  facets = {
 *    "text",
 *    "owner",
 *    "count",
 *    "sort",
 *    "pagination"
 *  },
 *  entity_types = false,
 *  multiple = true,
 *  recommendations = false
 * )
 */
class SearchAPIEngine extends EngineBase {

  use DependencySerializationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected PagerManagerInterface $pagerManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, KillSwitch $killSwitch, EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack, PagerManagerInterface $pagerManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $overviewManager, $killSwitch);
    $this->entityTypeManager = $entityTypeManager;
    $this->request = $requestStack->getCurrentRequest();
    $this->pagerManager = $pagerManager;
  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_overview.manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('pager.manager')
    );
  }

  /**
   * @var array
   */
  protected array $count = [];

  /**
   * @inheritDoc
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface {
    return new SearchAPIOverviewResult($this, $filter);
  }

  /**
   * @inheritDoc
   */
  public function getResult(OverviewFilter $filter) {
    $this->killSwitch->trigger();
    $this->setCount($filter, 0);
    $index = Index::load($this->getSetting('index'));
    $query = $index->query();

    // Change the parse mode for the search.
    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
      ->createInstance('direct');
    $parse_mode->setConjunction('OR');
    $query->setParseMode($parse_mode);

    // Set one or more tags for the query.
    // @see hook_search_api_query_TAG_alter()
    // @see hook_search_api_results_TAG_alter()
    $query->addTag('entity_overview');

    $overview = $filter->getOverview();
    if (!empty($filter->getFieldValue('text'))) {
      $query->keys($filter->getFieldValue('text'));
      $query->setFulltextFields($this->getSetting('fulltext_fields'));
    }
    if (!empty($this->getSetting('language_field'))) {
      $query->addCondition($this->getSetting('language_field') ?? 'langcode', \Drupal::languageManager()
        ->getCurrentLanguage()
        ->getId());
    }
    foreach ($filter->getFieldValues() as $field_name => $value) {
      if (empty($value)) {
        continue;
      }

      if ($field_name == 'text') {
      } elseif ($field_name == 'owner') {
        $query->addCondition($this->getSetting('owner_field') ?? 'uid', $value);
      } elseif (is_array($value)) {
        $query->addCondition($field_name, $value, 'IN');
      } else {
        $query->addCondition($field_name, $value);
      }
    }
    if ($filter->hasPagination()) {
      $query->range($filter->getPage() * $filter->getCount(), $filter->getCount());
    } elseif ($filter->getCount() > 0) {
      $query->range($filter->getPage() * $filter->getCount(), $filter->getCount());
    }
    switch ($filter->getSort()) {
      case 'alphabetical':
        $query->sort($this->getSetting('title_field') ?? 'title', 'ASC');
        break;
      case 'relevance':
        $query->sort('search_api_relevance', 'ASC');
        break;
      case 'oldest':
        $query->sort($overview->getSortField(), 'ASC');
        break;
      default:
        $query->sort($overview->getSortField(), 'DESC');
        break;
    }

    // Execute the search.
    $result = $query->execute();
    if (!empty($result) && !empty($result->getResultCount()) && $result->getResultCount() > 0) {
      $this->setCount($filter, $result->getResultCount());
    }

    if ($filter->hasPagination()) {
      $this->request->query->set('page', $filter->getPage());
      $this->pagerManager->createPager($this->getCount($filter), $filter->getCount(), 0);
    }
    return $result;

  }

  public function getEntities(OverviewFilter $filter): array {
    $result = $this->getResult($filter);
    if (empty($result)) {
      return [];
    }
    return $this->loadEntities($result);
  }

  public function loadEntities(ResultSetInterface $result): array {
    $entities = [];
    foreach ($result->getResultItems() as $resultItem) {
      $object = $resultItem->getOriginalObject(true);
      if ($object instanceof EntityAdapter) {
        $entities[] = $object->getEntity();
      }
    }
    foreach ($entities as $id => $entity) {
      if ($entity instanceof \Drupal\Core\TypedData\TranslatableInterface) {
        if ($entity->hasTranslation(\Drupal::languageManager()->getCurrentLanguage()->getId())) {
          $entities[$id] = $entity->getTranslation(\Drupal::languageManager()->getCurrentLanguage()->getId());
        }
      }
    }
    return $entities;
  }

  public function getTotalCount(OverviewFilter $filter): int {
    $index = Index::load($this->getSetting('index'));
    return $index->getTrackerInstance()->getTotalItemsCount();
  }

  /**
   * @inheritDoc
   */
  public function getSortCriterias(): array {
    $options = [
      'newest' => $this->t('Newest first'),
      'oldest' => $this->t('Oldest first')
    ];
    if ($this->getSetting('title_field')) {
      $options['alphabetical'] = $this->t('Alphabetical');
    }
    $options['relevance'] = $this->t('Relevance');
    return $options;
  }

  /**
   * Get the Entity Type ID used for the overview.
   *
   * @param string $overview_id
   *
   * @return string|null
   */
  protected function getEntityTypeID(Overview $overview) {
    return array_key_first($overview->getEntityBundles());
  }

  /**
   * Get the bundle ID used for the overview.
   *
   * @param string $overview_id
   *
   * @return string|null
   */
  protected function getBundles(Overview $overview) {
    return $overview->getEntityBundles()[$this->getEntityTypeID($overview)];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['notice'] = [
      '#type' => 'item',
      '#title' => $this->t('Notice'),
      '#description' => $this->t('Entity Overview works best with entity data sources for Search API.'),
    ];

    $indexes = Index::loadMultiple();
    $options = [];
    foreach ($indexes as $index) {
      $options[$index->id()] = $index->label();
    }
    $form['index'] = [
      '#type' => 'select',
      '#title' => $this->t('Index'),
      '#description' => $this->t('What Search API index to use.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('index') ?? ''
    ];
    $index = $indexes[$this->getSetting('index') ?? ''] ?? NULL;

    if (!empty($index)) {
      $options = [];
      foreach ($index->getFields(true) as $id => $field) {
        if (in_array($field->getType(), ['string', 'text'])) {
          $options[$id] = $field->getLabel();
        }
      }
      $form['title_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Title field'),
        '#description' => $this->t('What field to use for sorting alphabetically.'),
        '#options' => [
          '' =>  ' - ' . $this->t('None') . ' - '
        ] + $options,
        '#default_value' => $this->getSetting('title_field') ?? ''
      ];

      $options = [];
      foreach ($index->getFields(true) as $id => $field) {
        if (in_array($field->getType(), ['integer'])) {
          $options[$id] = $field->getLabel();
        }
      }
      $form['owner_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Owner field'),
        '#description' => $this->t('What field to use for the owner filter.'),
        '#options' => [
            '' =>  ' - ' . $this->t('None') . ' - '
          ] + $options,
        '#default_value' => $this->getSetting('owner_field') ?? ''
      ];

      $options = [];
      foreach ($index->getFields(true) as $id => $field) {
        if (in_array($field->getType(), ['string'])) {
          $options[$id] = $field->getLabel();
        }
      }
      $form['language_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Language field'),
        '#description' => $this->t('What field to use for the filtering language.'),
        '#options' => [
            '' =>  ' - ' . $this->t('None') . ' - '
          ] + $options,
        '#default_value' => $this->getSetting('language_field') ?? ''
      ];

      $options = [];
      foreach ($index->getFields(true) as $id => $field) {
        if (in_array($id, $index->getFulltextFields())) {
          $options[$id] = $field->getLabel();
        }
      }
      $form['fulltext_fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Full text fields'),
        '#description' => $this->t('What fields to use for full text search.'),
        '#options' => $options,
        '#default_value' => $this->getSetting('fulltext_fields') ?? ''
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedEntityTypes() {
    $index_id = $this->getSetting('index') ?? '';
    if (empty($index_id)) {
      return parent::getSupportedEntityTypes();
    }
    $index = Index::load($index_id);
    $types = [];
    foreach ($index->getDatasources() as $id => $datasource) {
      $bundles = [];
      foreach ($datasource->getBundles() as $bundle_id => $bundle) {
        $bundles[$bundle_id] = [
          'id' => $bundle_id,
          'label' => $bundle
        ];
      }

      $types[$datasource->getEntityTypeId()] = [
        'label' => $datasource->label(),
        'id' => $datasource->getEntityTypeId(),
        'bundles' => $bundles
      ];
    }

    return $types;
  }

  /**
   * @inheritDoc
   */
  public function getEngineSummary(Overview $overview): string|TranslatableMarkup {
    if (empty($this->getSetting('index'))) {
      return '';
    }
    $index = Index::load($this->getSetting('index'));
    return $this->t('Uses %index index', ['%index' => $index->label()]);
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
      if ($field == 'text' && empty($this->getSetting('title_field'))) {
        continue;
      }
      if ($field == 'owner' && empty($this->getSetting('owner_field'))) {
        continue;
      }
      if (!in_array($field, $this->overviewManager->getBaseFields())) {
        $fields[$field] = $field;
      }
    }
    return $fields;
  }

  /**
   * Finds the supported fields and sorting fields of selected entity types and bundles and caches it.
   *
   * @param array $entity_bundles
   *
   * @return void
   */
  protected function findSupportedFields(array $entity_bundles): void {
    $this->supportedFields = $this->getEngineSupportedFields($entity_bundles);
    $this->supportedSortFields = [];
    $index_id = $this->getSetting('index') ?? '';
    if (empty($index_id)) {
      return;
    }
    $index = Index::load($index_id);

    $fields = $index->getFields(TRUE);
    foreach ($fields as $id => $field) {
      switch ($field->getDataDefinition()->getDataType()) {
        case 'field_item:changed':
        case 'field_item:created':
        case 'field_item:datetime':
          $this->supportedSortFields[$id] = $field->getLabel();
          break;

        case 'field_item:entity_reference':
          $this->supportedFields[$id] = $field->getLabel();
          break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldInfo(Overview $overview, string $field): ?OverviewFieldInfoInterface {
    if (in_array($field, $this->getPluginDefinition()['facets'])) {
      return $this->getEngineFieldInfo($overview, $field);
    } else {
      $index_id = $this->getSetting('index') ?? '';
      if (empty($index_id)) {
        return NULL;
      }
      $index = Index::load($index_id);
      $field_name = $field;
      $field = $index->getField($field);
      if (is_null($field)) {
        \Drupal::logger('entity_overview')->error('Field %field was not found.', ['%field' => $field_name]);
        return NULL;
      }
      $definition = $field->getDataDefinition();
      if (is_null($definition)) {
        \Drupal::logger('entity_overview')->error('Field %field was not found.', ['%field' => $field_name]);
        return NULL;
      }
      switch ($field->getOriginalType()) {
        case 'field_item:entity_reference':
          $settings = $definition->getSettings() ?? [];
          if ($settings['target_type'] == 'taxonomy_term') {
            if (count($settings['handler_settings']['target_bundles']) == 1) {
              return TaxonomyField::createFromDataDefinition($field_name, $field->getLabel(), $definition);
            } else {
              \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
              return NULL;
            }
          } else {
            return EntityReferenceField::createFromDataDefinition($field_name, $field->getLabel(), $definition);
          }
        case 'field_item:datetime':
          return new DateField($field, $definition->getLabel(), $definition->getSetting('datetime_type'));
        default:
          \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
          return NULL;
      }
    }
  }

  protected function setCount(OverviewFilter $filter, $count) {
    $payload = $filter->toArray();
    $hash = md5(serialize($payload));
    $this->count[$hash] = $count;
  }

  protected function getCount(OverviewFilter $filter) {
    $payload = $filter->toArray();
    $hash = md5(serialize($payload));
    return $this->count[$hash] ?? NULL;
  }

}
