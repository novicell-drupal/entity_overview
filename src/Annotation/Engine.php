<?php

namespace Drupal\entity_overview\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an engine annotation object.
 *
 * Plugin Namespace: Plugin\EntityOverview\Engine
 *
 *
 * @see \Drupal\Core\ArchiverImportrManager
 * @see \Drupal\Core\Archiver\ImportInterface
 * @see plugin_api
 * @see hook_import_alter()
 *
 * @Annotation
 */
class Engine extends Plugin {

  /**
   * The engine plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The default facets supported by the plugin.
   *
   * @var array
   */
  public $facets;

  /**
   * Whether the plugin supports global overviews.
   *
   * @var boolean
   */
  public $multiple;

  /**
   * Whether the plugin supports search term recommendations.
   *
   * @var boolean
   */
  public $recommendations;
}
