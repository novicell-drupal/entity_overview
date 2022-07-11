<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, KillSwitch $killSwitch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->overviewManager = $overviewManager;
    $this->killSwitch = $killSwitch;
  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_overview.manager'), $container->get('page_cache_kill_switch'));
  }

  public function label(): string {
    return $this->t($this->pluginDefinition['title'] ?? 'Engine');
  }

  public function supportsMultipleEntities(): bool {
    return $this->pluginDefinition['multiple'] ?? FALSE;
  }

  public function getCacheableMetadata($overview_id, bool $has_facets): CacheableMetadata {
    $cache = new CacheableMetadata();
    if ($has_facets) {
      if ($this->overviewManager->deepLinksEnabled()) {
        $cache->addCacheContexts(['url.query_args']);
        $this->killSwitch->trigger();
      }
    }
    $tags = [];
    foreach ($this->overviewManager->getEntityTypesAndBundles($overview_id) as $entity_type_id => $bundles) {
      $tags[] = $entity_type_id . '_list';
    }
    $cache->addCacheTags($tags);
    return $cache;
  }

  public function getBaseFacets($overview_id): array {
    $facets = [];
    if (in_array('text', $this->getPluginDefinition()['facets'])) {
      $facets['text'] = $this->t('Search keywords');
    }
    if (in_array('owner', $this->getPluginDefinition()['facets'])) {
      $facets['owner'] = $this->t('Author');
    }
    if (in_array('sort', $this->getPluginDefinition()['facets'])) {
      $facets['sort'] = $this->t('Sort select');
    }
    if (in_array('count', $this->getPluginDefinition()['facets'])) {
      $facets['count'] = $this->t('Page size select');
    }
    return $facets;
  }

  public function getBaseFacetForm($entity_bundle, $facet, $default_value): array {
    $form = [];
    switch ($facet) {
      case 'text':
        $form = [
          '#type' => 'textfield',
          '#default_value' => $default_value ?? ''
        ];
        break;
      case 'sort':
        $form = [
          '#type' => 'select',
          '#options' => $this->getSortCriterias(),
          '#default_value' => $default_value ?? 'newest'
        ];
        break;
      case 'count':
        $form = [
          '#type' => 'select',
          '#options' => $this->overviewManager->getCountOptions($entity_bundle),
          '#default_value' => $default_value ?? 5
        ];
        break;
      case 'owner':
        $user = NULL;
        if (!empty($default_value)) {
          $user = \Drupal::entityTypeManager()->getStorage('user')->load($default_value);
        }
        $form = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
          '#default_value' => $user
        ];
        break;
    }
    return $form;
  }

}
