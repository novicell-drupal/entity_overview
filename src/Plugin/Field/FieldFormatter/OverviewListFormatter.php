<?php

namespace Drupal\entity_overview\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'overview_list' formatter.
 *
 * @FieldFormatter(
 *   id = "overview_list",
 *   label = @Translation("Overview list"),
 *   field_types = {
 *     "overview_filter"
 *   }
 * )
 */
class OverviewListFormatter extends FormatterBase {

  /**
   * @var \Drupal\entity_overview\OverviewManager
   */
  protected $overviewManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, OverviewManager $overviewManager, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->overviewManager = $overviewManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings'], $container->get('entity_overview.manager'), $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $entity_bundle = $items->getSetting('entity_bundle');
      $entities = $this->overviewManager->getEntities($entity_bundle, $item->getValue());
      $elements[$delta] = $this->entityTypeManager->getViewBuilder($this->overviewManager->getEntityTypeID($entity_bundle))->viewMultiple($entities, $this->getSetting('view_mode'));
      $elements[$delta]['#cache']['tags'][] = $this->overviewManager->getEntityTypeID($entity_bundle) . '_list';
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'view_mode' => 'teaser',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
        'view_mode' => [
          '#type' => 'select',
          '#title' => $this->t('View mode'),
          '#options' => $this->overviewManager->getViewModes(),
          '#default_value' => $this->getSetting('view_mode'),
          '#required' => TRUE,
        ],

        // Implement settings form.
      ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('View mode: @view_mode', [
      '@view_mode' => $this->getSetting('view_mode')
    ]);

    return $summary;
  }


}
