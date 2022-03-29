<?php

namespace Drupal\entity_overview\Plugin\EntityOverview\Engine;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_overview\EngineBase;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Engine(
 *  id = "entity_query",
 *  title = "Entity Query",
 *  facets = {
 *    "count",
 *    "sort"
 *  },
 *  global = false
 * )
 */
class EntityQueryEngine extends EngineBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $overviewManager);
    $this->entityTypeManager = $entityTypeManager;

  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_overview.manager'),
      $container->get('entity_type.manager')
    );
  }

  public function getResult($entity_bundle, array $filter = [], $page = 0) {
    $storage = $this->entityTypeManager->getStorage($this->overviewManager->getEntityTypeID($entity_bundle));
    $keys = $this->entityTypeManager->getDefinition($this->overviewManager->getEntityTypeID($entity_bundle))->getKeys();
    $query = $storage->getQuery()
      ->condition($keys['bundle'], $this->overviewManager->getBundle($entity_bundle))
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
    if ($filter['pagination']) {
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
        $query->sort($this->overviewManager->getSortField($entity_bundle), 'ASC');
        break;
      default:
        $query->sort($this->overviewManager->getSortField($entity_bundle), 'DESC');
        break;
    }

    return $query->execute();
  }

  public function getEntities($entity_bundle, array $filter = [], $page = 0) {
    $storage = $this->entityTypeManager->getStorage($this->overviewManager->getEntityTypeID($entity_bundle));
    $ids = $this->getResult($entity_bundle, $filter, $page);
    return $storage->loadMultiple($ids);
  }

  public function getEntitiesTotal($entity_bundle, array $filter = [], $shown = 0) {
    $keys = $this->entityTypeManager->getDefinition($this->overviewManager->getEntityTypeID($entity_bundle))->getKeys();
    $count = 0;
    $total = 0;
    switch ($filter['show_total']) {
      case 'filtered':
        $query = $this->entityTypeManager->getStorage($this->overviewManager->getEntityTypeID($entity_bundle))->getQuery()
          ->condition($keys['bundle'], $this->overviewManager->getBundle($entity_bundle))
          ->condition('status', 1)
          ->count();
        $cid = 'entity_overview:' . $entity_bundle . '_total';
        $cache = \Drupal::cache()->get($cid);
        if ($cache === FALSE) {
          $total = $query->execute();
          \Drupal::cache()->set($cid, $total, Cache::PERMANENT, [$this->overviewManager->getEntityTypeID($entity_bundle) . '_list']);
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
        $query = $this->entityTypeManager->getStorage($this->overviewManager->getEntityTypeID($entity_bundle))->getQuery()
          ->condition($keys['bundle'], $this->overviewManager->getBundle($entity_bundle))
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

  public function getSortCriterias() {
    return [
      'newest' => t('Newest first'),
      'oldest' => t('Oldest first'),
      'alphabetical' => t('Alphabetical'),
    ];
  }

  public function getShowTotalOptions() {
    return [
      '' => t('None'),
      'filtered' => t('Filtered out of total number of items'),
      'shown' => t('Shown items out of filtered number of items'),
    ];
  }

}
