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

  public function getCacheableMetadata($entity_bundle, bool $has_facets): CacheableMetadata {
    $cache = new CacheableMetadata();
    if ($has_facets) {
      if ($this->overviewManager->deepLinksEnabled()) {
        $cache->addCacheContexts(['url.query_args']);
        $this->killSwitch->trigger();
      }
    }
    if (!empty($this->overviewManager->getEntityTypeID($entity_bundle))) {
      $cache->addCacheTags([$this->overviewManager->getEntityTypeID($entity_bundle) . '_list']);
    }
    return $cache;
  }

  public function getBaseFacets($entity_bundle): array {
    $facets = [];
    if (in_array('text', $this->getPluginDefinition()['facets'])) {
      $facets['text'] = $this->t('Text');
    }
    if (in_array('sort', $this->getPluginDefinition()['facets'])) {
      $facets['sort'] = $this->t('Sort select');
    }
    if (in_array('count', $this->getPluginDefinition()['facets'])) {
      $facets['count'] = $this->t('Page size select');
    }
    if (in_array('owner', $this->getPluginDefinition()['facets'])) {
      $facets['owner'] = $this->t('Author');
    }
    return $facets;
  }

  public function getBaseFacetForm($entity_bundle, $facet, FormStateInterface $form_state): array {
    $form = [];
    switch ($facet) {
      case 'text':
        $form = [
          '#type' => 'textfield',
          '#default_value' => empty($form_state->get('text')) ? '' : $form_state->get('text'),
        ];
        break;
      case 'sort':
        $form = [
          '#type' => 'select',
          '#options' => $this->getSortCriterias(),
          '#default_value' => empty($form_state->get('sort')) ? 'newest' : $form_state->get('sort'),
        ];
        break;
      case 'count':
        $form = [
          '#type' => 'select',
          '#options' => $this->overviewManager->getCountOptions(),
          '#default_value' => empty($form_state->get('count')) ? 5 : $form_state->get('count')
        ];
        break;
      case 'owner':
        $form = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
          '#default_value' => empty($form_state->get('owner')) ? NULL : $form_state->get('owner')
        ];
        break;
    }
    return $form;
  }

}
