<?php

namespace Drupal\entity_overview_transform\Plugin\Transform\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\Entity\Overview;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\transform_api\FieldTransformBase;
use Drupal\transform_api\Repository\EntityTransformRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @FieldTransform(
 *  id = "entity_overview_list",
 *  label = @Translation("Entity overview list"),
 *  field_types = {
 *    "overview_filter"
 *  }
 * )
 */
class EntityOverviewListTransform extends FieldTransformBase {

  /**
   * @var \Drupal\entity_overview\OverviewManager
   */
  protected $overviewManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\transform_api\Repository\EntityTransformRepositoryInterface
   */
  protected $entityTransformRepository;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $transform_mode, array $third_party_settings, OverviewManager $overviewManager, EntityTypeManagerInterface $entityTypeManager, EntityTransformRepositoryInterface $entityTransformRepository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $transform_mode, $third_party_settings);
    $this->overviewManager = $overviewManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTransformRepository = $entityTransformRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['transform_mode'], $configuration['third_party_settings'], $container->get('entity_overview.manager'), $container->get('entity_type.manager'), $container->get('transform_api.entity_display.repository'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'transform_mode' => 'default',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $overview = $this->overviewManager->getOverview($this->fieldDefinition->getSetting('overview'));
    return [
        'transform_mode' => [
          '#type' => 'select',
          '#title' => $this->t('Transform mode'),
          '#options' => $this->getTransformModes($overview),
          '#default_value' => $this->getSetting('transform_mode'),
          '#required' => TRUE,
        ],

        // Implement settings form.
      ] + parent::settingsForm($form, $form_state);
  }

  /**
   * @param \Drupal\entity_overview\Entity\Overview $overview
   *
   * @return array
   */
  public function getTransformModes(Overview $overview): array {
    $transform_modes = NULL;
    foreach ($overview->getEntityBundles() as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        if (is_null($transform_modes)) {
          $transform_modes = $this->entityTransformRepository->getTransformModeOptionsByBundle($entity_type_id, $bundle);
        } else {
          $transform_modes = array_intersect_key($transform_modes, $this->entityTransformRepository->getTransformModeOptionsByBundle($entity_type_id, $bundle));
        }
      }
    }
    if (empty($transform_modes)) {
      $transform_modes = ['default' => $this->t('Default')];
    }
    return $transform_modes ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $overview = $this->overviewManager->getOverview($this->fieldDefinition->getSetting('overview'));
    $summary = [];
    $summary[] = $this->t('Transform mode: @transform_mode', [
      '@transform_mode' => $this->getTransformModes($overview)[$this->getSetting('transform_mode')]
    ]);

    return $summary;
  }

  public function transformElements(FieldItemListInterface $items, $langcode) {
    $overview_id = $items->getSetting('overview');

    $values = [];
    foreach ($items as $delta => $item) {
      $filter = new OverviewFilter($overview_id, $item->getValue());
      $filter->setViewMode($this->getSetting('transform_mode'));
      $result = $filter->getOverview()->getOverviewResult($filter);
      $values[$delta] = ['entities' => []];
      foreach ($result->getEntities() as $entity) {
        if (method_exists(\Drupal\transform_api\Transform\EntityTransform::class, 'createFromEntity')) {
          $values[$delta]['entities'][] = new \Drupal\transform_api\Transform\EntityTransform($entity->getEntityTypeId(), $entity->id(), $filter->getViewMode());
        } else {
          $values[$delta]['entities'][] = new \Drupal\transform_api\Transform\EntityTransform($entity, $filter->getViewMode());
        }
      }
    }
    return $values;
  }

}
