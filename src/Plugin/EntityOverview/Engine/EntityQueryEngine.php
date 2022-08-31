<?php

namespace Drupal\entity_overview\Plugin\EntityOverview\Engine;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\entity_overview\EngineBase;
use Drupal\entity_overview\Entity\Overview;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Engine(
 *  id = "entity_query",
 *  title = "Entity Query",
 *  facets = {
 *    "owner",
 *    "count",
 *    "sort",
 *    "pagination"
 *  },
 *  multiple = false,
 *  recommendations = false
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
  public function getResult(OverviewFilter $filter) {
    $overview = $filter->getOverview();
    $storage = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview));
    $keys = $this->entityTypeManager->getDefinition($this->getEntityTypeID($overview))->getKeys();
    $query = $storage->getQuery()
      ->condition($keys['bundle'], $this->getBundles($overview), 'IN')
      ->condition('status', 1);
    foreach ($filter->getFieldValues() as $field_name => $value) {
      if (empty($value)) {
        continue;
      }

      if ($field_name == 'owner') {
        $query->condition($keys['owner'], $value);
      } elseif (is_array($value)) {
        $query->condition($field_name, $value, 'IN');
      } else {
        $query->condition($field_name, $value);
      }
    }
    if ($filter->hasPagination()) {
      // Do not use dependency injection for the request, or it will be serialized with the form state
      \Drupal::requestStack()->getCurrentRequest()->query->set('page', $filter->getPage());
      $query->pager($filter->getCount());
    } elseif ($filter->getCount() > 0) {
      $query->range($filter->getPage() * $filter->getCount(), $filter->getCount());
    }
    switch ($filter->getSort()) {
      case 'alphabetical':
        $query->sort($keys['label'], 'ASC');
        break;
      case 'oldest':
        $query->sort($overview->getSortField(), 'ASC');
        break;
      default:
        $query->sort($overview->getSortField(), 'DESC');
        break;
    }

    return $query->execute();
  }

  /**
   * @inheritDoc
   */
  public function getEntities(OverviewFilter $filter): array {
    $overview = $filter->getOverview();
    if (empty($this->getEntityTypeID($overview))) {
      return [];
    }
    $ids = $this->getResult($filter);
    if (empty($ids)) {
      return [];
    }
    try {
      $storage = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview));
    } catch (InvalidPluginDefinitionException $e) {
      return [];
    } catch (PluginNotFoundException $e) {
      return [];
    }
    return $storage->loadMultiple($ids);
  }

  /**
   * @inheritDoc
   */
  public function getEntitiesTotal(OverviewFilter $filter, int $shown = 0): string|\Drupal\Core\StringTranslation\TranslatableMarkup {
    $overview = $filter->getOverview();
    $keys = $this->entityTypeManager->getDefinition($this->getEntityTypeID($overview))->getKeys();
    $count = 0;
    $total = 0;
    switch ($filter->getShowTotal()) {
      case 'filtered':
        $query = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview))->getQuery()
          ->condition($keys['bundle'], $this->getBundles($overview), 'IN')
          ->condition('status', 1)
          ->count();
        $cid = 'entity_overview:' . $overview->id() . '_total';
        $cache = \Drupal::cache()->get($cid);
        if ($cache === FALSE) {
          $total = $query->execute();
          \Drupal::cache()->set($cid, $total, Cache::PERMANENT, [$this->getEntityTypeID($overview) . '_list']);
        } else {
          $total = $cache->data;
        }
        foreach ($filter->getFieldValues() as $field_name => $value) {
          if (empty($value)) {
            continue;
          }
          if ($field_name == 'owner') {
            $query->condition($keys['owner'], $value);
          } elseif (is_array($value)) {
            $query->condition($field_name, $value, 'IN');
          } else {
            $query->condition($field_name, $value);
          }
        }
        $count = $query->execute();
        break;
      case 'shown':
        $count = $shown;
        $query = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview))->getQuery()
          ->condition($keys['bundle'], $this->getBundles($overview), 'IN')
          ->condition('status', 1)
          ->count();
        foreach ($filter->getFieldValues() as $field_name => $value) {
          if (empty($value)) {
            continue;
          }
          if ($field_name == 'owner') {
            $query->condition($keys['owner'], $value);
          } elseif (is_array($value)) {
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
  public function getSortCriterias(): array {
    return [
      'newest' => t('Newest first'),
      'oldest' => t('Oldest first'),
      'alphabetical' => t('Alphabetical'),
    ];
  }

  /**
   * @inheritDoc
   */
  public function getShowTotalOptions(): array {
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

}
