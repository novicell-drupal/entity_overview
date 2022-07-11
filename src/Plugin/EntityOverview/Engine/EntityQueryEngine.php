<?php

namespace Drupal\entity_overview\Plugin\EntityOverview\Engine;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\entity_overview\EngineBase;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Engine(
 *  id = "entity_query",
 *  title = "Entity Query",
 *  facets = {
 *    "owner",
 *    "count",
 *    "sort"
 *  },
 *  multiple = false
 * )
 */
class EntityQueryEngine extends EngineBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, KillSwitch $killSwitch, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $overviewManager, $killSwitch);
    $this->entityTypeManager = $entityTypeManager;

  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_overview.manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * @inheritDoc
   */
  public function getResult($overview_id, array $filter = [], $page = 0) {
    $storage = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview_id));
    $keys = $this->entityTypeManager->getDefinition($this->getEntityTypeID($overview_id))->getKeys();
    $query = $storage->getQuery()
      ->condition($keys['bundle'], $this->getBundle($overview_id))
      ->condition('status', 1);
    foreach ($filter['fields'] as $field_name => $value) {
      if (empty($value)) {
        continue;
      }
      if (is_array($value)) {
        $query->condition($field_name, $value, 'IN');
      } else {
        $query->condition($field_name, $value);
      }
    }
    if (!empty($filter['owner'])) {
      $query->condition($keys['owner'], $filter['owner']);
    }
    if ($filter['pagination']) {
      // Do not use dependency injection for the request, or it will be serialized with the form state
      \Drupal::requestStack()->getCurrentRequest()->query->set('page', $page);
      $query->pager($filter['count']);
    } elseif (isset($filter['count']) && $filter['count'] > 0) {
      $query->range($page * $filter['count'], $filter['count']);
    }
    switch ($filter['sort']) {
      case 'alphabetical':
        $query->sort($keys['label'], 'ASC');
        break;
      case 'oldest':
        $query->sort($this->overviewManager->getSortField($overview_id), 'ASC');
        break;
      default:
        $query->sort($this->overviewManager->getSortField($overview_id), 'DESC');
        break;
    }

    return $query->execute();
  }

  /**
   * @inheritDoc
   */
  public function getEntities($overview_id, array $filter = [], $page = 0) {
    if (empty($this->getEntityTypeID($overview_id))) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview_id));
    $ids = $this->getResult($overview_id, $filter, $page);
    return $storage->loadMultiple($ids);
  }

  /**
   * @inheritDoc
   */
  public function getEntitiesTotal($overview_id, array $filter = [], $shown = 0) {
    $keys = $this->entityTypeManager->getDefinition($this->getEntityTypeID($overview_id))->getKeys();
    $count = 0;
    $total = 0;
    switch ($filter['show_total']) {
      case 'filtered':
        $query = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview_id))->getQuery()
          ->condition($keys['bundle'], $this->getBundle($overview_id))
          ->condition('status', 1)
          ->count();
        $cid = 'entity_overview:' . $overview_id . '_total';
        $cache = \Drupal::cache()->get($cid);
        if ($cache === FALSE) {
          $total = $query->execute();
          \Drupal::cache()->set($cid, $total, Cache::PERMANENT, [$this->getEntityTypeID($overview_id) . '_list']);
        } else {
          $total = $cache->data;
        }
        foreach ($filter['fields'] as $field_name => $value) {
          if (empty($value)) {
            continue;
          }
          if (is_array($value)) {
            $query->condition($field_name, $value, 'IN');
          } else {
            $query->condition($field_name, $value);
          }
        }
        $count = $query->execute();
        break;
      case 'shown':
        $count = $shown;
        $query = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview_id))->getQuery()
          ->condition($keys['bundle'], $this->getBundle($overview_id))
          ->condition('status', 1)
          ->count();
        foreach ($filter['fields'] as $field_name => $value) {
          if (empty($value)) {
            continue;
          }
          if (is_array($value)) {
            $query->condition($field_name, $value, 'IN');
          } else {
            $query->condition($field_name, $value);
          }
        }
        $total = $query->execute();
        break;
    }
    return $this->t('Showing @count out of @total', ['@count' => $count, '@total' => $total]);
  }

  /**
   * @inheritDoc
   */
  public function getSortCriterias() {
    return [
      'newest' => t('Newest first'),
      'oldest' => t('Oldest first'),
      'alphabetical' => t('Alphabetical'),
    ];
  }

  /**
   * @inheritDoc
   */
  public function getShowTotalOptions() {
    return [
      '' => t('None'),
      'filtered' => t('Filtered out of total number of items'),
      'shown' => t('Shown items out of filtered number of items'),
    ];
  }

  /**
   * Get the Entity Type ID used for the overview.
   *
   * @param string $overview_id
   *
   * @return string|null
   */
  protected function getEntityTypeID($overview_id) {
    return array_key_first($this->overviewManager->getEntityTypesAndBundles($overview_id));
  }

  /**
   * Get the bundle ID used for the overview.
   *
   * @param string $overview_id
   *
   * @return string|null
   */
  protected function getBundle($overview_id) {
    $entity_types = $this->overviewManager->getEntityTypesAndBundles($overview_id);
    return reset($entity_types[$this->getEntityTypeID($overview_id)]);
  }

}
