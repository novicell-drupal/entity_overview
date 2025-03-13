<?php

namespace Drupal\entity_overview_search_api\Plugin\search_api_autocomplete\search;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\search_api_autocomplete\Search\SearchPluginDeriverBase;

/**
 * Derives a search plugin definition for every overview.
 *
 * @see \Drupal\entity_overview_search_api\Plugin\search_api_autocomplete\search\Overview
 */
class OverviewDeriver extends SearchPluginDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!isset($this->derivatives)) {
      $this->derivatives = [];

      try {
        $overview_storage = $this->getEntityTypeManager()
          ->getStorage('entity_overview');
        $index_storage = $this->getEntityTypeManager()
          ->getStorage('search_api_index');
      }
      catch (PluginException $e) {
        return $this->derivatives;
      }

      /** @var \Drupal\entity_overview\OverviewInterface $overview */
      foreach ($overview_storage->loadMultiple() as $overview) {
        if ($overview->getEngineID() != 'search_api') {
          continue;
        }
        $engine = $overview->getEngine();
        $index = $index_storage->load($engine->getSetting('index'));
        $this->derivatives[$overview->id()] = [
          'label' => $overview->label(),
          'index' => $index->id(),
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
