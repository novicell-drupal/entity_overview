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
    $keys = $this->entityTypeManager->getDefinition($this->getEntityTypeID($overview))->getKeys();
    $cid = 'entity_overview:' . $overview->id() . '_total';
    $cache = \Drupal::cache()->get($cid);
    if ($cache === FALSE) {
      try {
        $query = $this->entityTypeManager->getStorage($this->getEntityTypeID($overview))
          ->getQuery()
          ->condition($keys['bundle'], $this->getBundles($overview), 'IN')
          ->condition('status', 1)
          ->count();
        $total = $query->execute();
      } catch (InvalidPluginDefinitionException $e) {
        $total = 0;
      } catch (PluginNotFoundException $e) {
        $total = 0;
      }
      \Drupal::cache()->set($cid, $total, Cache::PERMANENT, [$this->getEntityTypeID($overview) . '_list']);
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
  protected function getEngineFieldInfo(Overview $overview, string $field): array {
    return match ($field) {
      'text' => [
        'label' => $this->t('Search keywords'),
        'widgets' => ['textfield']
      ],
      'owner' => [
        'label' => $this->t('Author'),
        'widgets' => ['entity_autocomplete']
      ],
      default => []
    };
  }

  /**
   * @param \Drupal\entity_overview\OverviewFilter $filter
   * @param string $field
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEngineFieldFormElement(OverviewFilter $filter, string $field): array {
    switch ($field) {
      case 'text':
        $form = [
          '#type' => 'search',
          '#title' => $this->t('Search terms'),
          '#default_value' => $filter->getFieldValue($field) ?? ''
        ];
        break;
      case 'owner':
        $user = NULL;
        if (!empty($filter->getFieldValue($field))) {
          $user = \Drupal::entityTypeManager()->getStorage('user')->load($filter->getFieldValue($field));
        }
        $form = [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Author'),
          '#target_type' => 'user',
          '#default_value' => $user
        ];
        break;
      default:
        $form = [];
    }
    return $form;
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

  public function getFieldInfo(Overview $overview, string $field): array {
    if (in_array($field, $this->getPluginDefinition()['facets'])) {
      return $this->getEngineFieldInfo($overview, $field);
    } else {
      if (empty($this->supportedFields)) {
        $this->findSupportedFields($overview->getEntityBundles());
      }
      return [
        'label' => $this->supportedFields[$field],
        'widgets' => ['checkboxes']
      ];
    }
  }

  public function getFieldFormElement(OverviewFilter $filter, string $field): array {
    if (in_array($field, $this->getPluginDefinition()['facets'])) {
      return $this->getEngineFieldFormElement($filter, $field);
    } else {
      $overview = $filter->getOverview();
      $widget = $overview->getFieldWidget($field);
      $element = [
        '#type' => $widget,
      ];
      $definition = $this->getFieldDefinitions($overview)[$field];

      switch ($definition->getType()) {
        case 'entity_reference':
          $settings = $definition->getSettings() ?? [];
          $storage = $this->entityTypeManager->getStorage($settings['target_type']);
          // TODO: Support more entity types than taxonomy
          if ($settings['target_type'] == 'taxonomy_term') {
            if (count($settings['handler_settings']['target_bundles']) == 1) {
              $element['#title'] = $definition->getLabel();
              $element['#default_value'] = $filter->getFieldValue($field) ?? [];
              $element['#options'] = [];
              $vid = reset($settings['handler_settings']['target_bundles']);
              $query = $storage->getQuery();
              $query->condition('vid', $vid)
                ->sort($settings['handler_settings']['sort']['field'], $settings['handler_settings']['sort']['direction']);
              $tids = $query->execute();
              $terms = $storage->loadMultiple($tids);
              foreach ($terms as $term) {
                $element['#options'][$term->id()] = $term->label();
              }
            } else {
              \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
              return [];
            }
          } else {
            \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
            return [];
          }
          break;
        default:
          \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field]);
          return [];
      }
      return $element;
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
          foreach ($overview->getFields() as $field_name => $widget) {
            if (!isset($definitions[$overview->id()][$field_name]) && !empty($bundle_definitions[$field_name])) {
              $definitions[$overview->id()][$field_name] = $bundle_definitions[$field_name];
            }
          }
        }
      }
    }

    return $definitions[$overview->id()];
  }

}
