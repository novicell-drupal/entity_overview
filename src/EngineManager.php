<?php

namespace Drupal\entity_overview;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class EngineManager extends DefaultPluginManager {

  /**
   * Constructs a ImportManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/EntityOverview/Engine',
      $namespaces,
      $module_handler,
      'Drupal\entity_overview\EngineInterface',
      'Drupal\entity_overview\Annotation\Engine'
    );
    $this->alterInfo('entity_overview_engine_info');
    $this->setCacheBackend($cache_backend, 'entity_overview_engine_plugins');
  }

  /**
   * @param $plugin_id
   * @param array $configuration
   *
   * @return EngineInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /** @var EngineInterface $engine */
    $engine = parent::createInstance($plugin_id, $configuration);
    return $engine;
  }

}
