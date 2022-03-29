<?php
namespace Drupal\entity_overview;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\Pager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;

class OverviewManager {

  use StringTranslationTrait;

  /**
   * @var EntityStorageInterface
   */
  protected $taxonomyStorage;

  /**
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\entity_overview\EngineManager
   */
  protected $engineManager;

  function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EngineManager $engineManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->taxonomyStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->configFactory = $configFactory;
    $this->engineManager = $engineManager;
  }

  /**
   * @return array
   */
  public function getEntityBundles() {
    $list = $this->configFactory->listAll('entity_overview.');
    $result = [];
    foreach ($list as $config_id) {
      $config = $this->configFactory->get($config_id);
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($config->get('entity_type_id'));
      $result[$config->get('id')] = $bundle_info[$config->get('bundle')]['label'];
    }
    return $result;
  }

  /**
   * @param string $entity_bundle
   *
   * @return array
   */
  public function getEntityBundleConfig($entity_bundle) {
    $configs = &drupal_static(__FUNCTION__, []);
    if (!empty($configs[$entity_bundle])) {
      return $configs[$entity_bundle];
    }

    $configs[$entity_bundle] = $this->configFactory->get('entity_overview.' . $entity_bundle)->getRawData();
    return $configs[$entity_bundle];
  }

  /**
   * @param $entity_bundle
   * @return array
   */
  public function getFieldFormElements($entity_bundle) {
    // TODO: Get more information from field definitions and cache it
    $fields = $this->getEntityBundleConfig($entity_bundle)['fields'];
    $elements = [];
    foreach ($fields as $field => $form_element) {
      $elements[$field] = $this->getFieldFormElement($entity_bundle, $field, $form_element);
    }
    return $elements;
  }

  public function getFieldFormElement($entity_bundle, $field_name, $element_type) {
    $element = [
      'form_element' => $element_type,
    ];

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $definitions = $this->entityFieldManager->getFieldDefinitions($this->getEntityTypeID($entity_bundle), $this->getBundle($entity_bundle));
    $definition = $definitions[$field_name];
    switch ($definition->getType()) {
      case 'entity_reference':
        $settings = $definition->getSettings() ?? [];
        $storage = $this->entityTypeManager->getStorage($settings['target_type']);
        // TODO: Support more entity types than taxonomy
        if ($settings['target_type'] == 'taxonomy_term') {
          if (count($settings['handler_settings']['target_bundles']) == 1) {
            $element['label'] = $definition->getLabel();
            $element['source'] = 'taxonomy_term';
            $element['options'] = [];
            $vid = reset($settings['handler_settings']['target_bundles']);
            $element['vid'] = $vid;
            $query = $storage->getQuery();
            $query->condition('vid', $vid)
              ->sort($settings['handler_settings']['sort']['field'], $settings['handler_settings']['sort']['direction']);
            $tids = $query->execute();
            $terms = $storage->loadMultiple($tids);
            foreach ($terms as $term) {
              $element['options'][$term->id()] = $term->label();
            }
          } else {
            \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field_name]);
            return [];
          }
        } else {
          \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field_name]);
          return [];
        }
        break;
      default:
        \Drupal::logger('entity_overview')->error('Field %field is not supported by Entity Overview', ['%field' => $field_name]);
        return [];
    }
    return $element;
  }

  public function getCountOptions() {
    return [
      5 => '5',
      10 => '10',
      15 => '15',
      20 => '20',
      25 => '25'
    ];
  }

  /**
   * Get list of supported sorting criteria
   *
   * @return array
   */
  public function getSortCriterias($entity_bundle) {
    return $this->getEngine($entity_bundle)->getSortCriterias();
  }

  /**
   * @return array
   */
  public function getViewModes() {
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $repository */
    $repository = \Drupal::service('entity_display.repository');
    return $repository->getViewModeOptionsByBundle('node', 'page');
  }

  /**
   * @param string $entity_bundle
   *
   * @return string
   */
  public function getLabel($entity_bundle) {
    $label = $this->getEntityBundleConfig($entity_bundle)['label'] ?? NULL;
    if (empty($label)) {
      if (empty($this->getEntityTypeID($entity_bundle))) {
        return $entity_bundle;
      } else {
        $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($this->getEntityTypeID($entity_bundle));
        return $bundle_info[$this->getBundle($entity_bundle)]['label'];
      }
    } else {
      return $label;
    }
  }

  /**
   * @param string $entity_bundle
   *
   * @return string|null
   */
  public function getEntityTypeID($entity_bundle) {
    return $this->getEntityBundleConfig($entity_bundle)['entity_type_id'] ?? NULL;
  }

  /**
   * @param string $entity_bundle
   *
   * @return string|null
   */
  public function getBundle($entity_bundle) {
    return $this->getEntityBundleConfig($entity_bundle)['bundle'] ?? NULL;
  }

  /**
   * @param string $entity_bundle
   *
   * @return string
   */
  public function getSortField($entity_bundle) {
    return $this->getEntityBundleConfig($entity_bundle)['sort_field'] ?? 'field_list_date';
  }

  /**
   * @param string $entity_bundle
   *
   * @return string
   */
  public function getShowTotal($entity_bundle) {
    return $this->getEntityBundleConfig($entity_bundle)['show_total'] ?? '';
  }

  /**
   * @param $entity_bundle
   *
   * @return \Drupal\entity_overview\EngineInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getEngine($entity_bundle) {
    $engines = &drupal_static(__FUNCTION__, []);
    if (empty($engines[$entity_bundle])) {
      $engine = $this->getEntityBundleConfig($entity_bundle)['engine'] ?? 'entity_query';
      $engines[$entity_bundle] = $this->engineManager->createInstance($engine);
    }
    return $engines[$entity_bundle];
  }

  /**
   * @param string $entity_bundle
   * @param array $filter
   * @param int $page
   *
   * @return mixed
   */
  public function getResult($entity_bundle, array $filter = [], $page = 0) {
    return $this->getEngine($entity_bundle)->getResult($entity_bundle, $filter, $page);
  }

  /**
   * @param string $entity_bundle
   * @param array $filter
   * @param int $page
   *
   * @return EntityInterface[]
   */
  public function getEntities($entity_bundle, array $filter = [], $page = 0) {
    return $this->getEngine($entity_bundle)->getEntities($entity_bundle, $filter, $page);
  }

  /**
   * @param string $entity_bundle
   * @param array $filter
   * @param int $shown
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getEntitiesTotal($entity_bundle, array $filter = [], $shown = 0) {
    return $this->getEngine($entity_bundle)->getEntitiesTotal($entity_bundle, $filter, $shown);
  }

}
