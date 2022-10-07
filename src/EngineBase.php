<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_overview\Entity\Overview;
use Drupal\entity_overview\OverviewFields\OwnerField;
use Drupal\entity_overview\OverviewFields\SearchTextField;
use Drupal\entity_overview\OverviewFields\TaxonomyField;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class EngineBase extends PluginBase implements EngineInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

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
          $keys = $this->entityTypeManager->getDefinition($entity_type_id)
            ->getKeys();
          $query = $this->entityTypeManager->getStorage($entity_type_id)
            ->getQuery()
            ->condition($keys['bundle'], $bundles, 'IN')
            ->condition('status', 1)
            ->count();
          $total += $query->execute();
          $tags[] = $entity_type_id . '_list';
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
   * @inheritDoc
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
      $tags[] = $entity_type_id . '_list';
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
   * Returns info about an engine field.
   *
   * @param \Drupal\entity_overview\Entity\Overview $overview
   * @param string $field
   *
   * @return array
   */
  protected function getEngineFieldInfo(Overview $overview, string $field): ?OverviewFieldInfoInterface {
    return match ($field) {
      'text' => new SearchTextField(),
      'owner' => new OwnerField(),
      default => NULL
    };
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

  protected function findSupportedFields(array $entity_bundles) {
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
          // TODO: Support more entity types than taxonomy
          if ($definition->getType() == 'entity_reference' && in_array($definition->getSetting('target_type'), [
              'taxonomy_term',
              /*'user', 'media'*/
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
          $storage = $this->entityTypeManager->getStorage($settings['target_type']);
          // TODO: Support more entity types than taxonomy
          if ($settings['target_type'] == 'taxonomy_term') {
            if (count($settings['handler_settings']['target_bundles']) == 1) {
              $label = $definition->getLabel();
              $options = [];
              $vid = reset($settings['handler_settings']['target_bundles']);
              $query = $storage->getQuery();
              $query->condition('vid', $vid)
                ->sort($settings['handler_settings']['sort']['field'], $settings['handler_settings']['sort']['direction']);
              $tids = $query->execute();
              $terms = $storage->loadMultiple($tids);
              foreach ($terms as $term) {
                $options[$term->id()] = $term->label();
              }
              return new TaxonomyField($field, $label, $options);
            } else {
              \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
              return NULL;
            }
          } else {
            \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
            return NULL;
          }
          break;
        default:
          \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
          return NULL;
      }
    }
  }

  /**
   * @param $overview_id
   * @return array
   */
  protected function getFieldDefinitions(Overview $overview) {
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

}
