<?php

namespace Drupal\entity_overview\Form;

use Drupal\content_notify\ContentNotifyManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\EngineManager;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit form for overviews.
 */
class OverviewEditForm extends EntityForm {

  protected $entityTypeManager;
  protected $entityFieldManager;
  protected $entityTypeBundleInfo;
  protected $engineManager;

  /**
   * The entity overview manager.
   *
   * @var \Drupal\entity_overview\OverviewManager
   */
  protected $overviewManager;

  /**
   * @var \Drupal\entity_overview\OverviewInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_overview.manager'),
      $container->get('plugin.entity_overview.engine')
    );
  }

  /**
   * OverviewEditForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   * @param \Drupal\entity_overview\OverviewManager $overviewManager
   * @param \Drupal\entity_overview\EngineManager $engineManager
   */
  function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, OverviewManager $overviewManager, EngineManager $engineManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->overviewManager = $overviewManager;
    $this->engineManager = $engineManager;
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
      '#description' => $this->t('A short name to help you identify this overview in the overview list.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->id(),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
      '#machine_name' => [
        'exists' => 'Drupal\entity_overview\Entity\Overview::load',
      ],
    ];

    /** @var \Drupal\entity_overview\EngineInterface $engine */
    $engine = $this->entity->getEngine();
    $form['engine_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Search engine'),
      '#description' => $engine->label()
    ];

    $bundles = $this->entity->getEntityBundles();
    $form['entity_bundles_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Entity types'),
      '#description' => $engine->getEngineSummary($this->entity),
      '#default_value' => $bundles
    ];

    $form['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Fields'),
      '#description' => $this->t('What fields can be used as facets?'),
      '#open' => TRUE,
    ];
    $fields = $this->entity->getFields();
    foreach ($this->entity->getSupportedFieldsInfo($bundles) as $field => $field_info) {
      if (is_null($field_info)) {
        $form['fields'][$field] = [
          '#type' => 'item',
          '#title' => $field,
          '#description' => $this->t('No longer supported')
        ];
        continue;
      }
      $widgets = [
        '' => ' - ' . $this->t('Disabled') . ' - ',
      ] + $field_info->getWidgets() ?? [];
      $form['fields'][$field] = [
        '#type' => 'select',
        '#title' => $field_info->label(),
        '#options' => $widgets,
        '#default_value' => $fields[$field] ?? ''
      ];
    }

    $sort_options = $engine->getSupportedSortFields($bundles);
    $form['sort_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => $sort_options,
      '#description' => $this->t('What field should be used for sorting?'),
      '#default_value' => $this->entity->getSortField() ?? 'changed'
    ];

    $form['show_total'] = [
      '#type' => 'select',
      '#title' => t('Display of total number of items'),
      '#options' => $this->overviewManager->getShowTotalOptions(),
      '#default_value' => $this->entity->getShowTotal() ?? ''
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_overview\OverviewInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    $fields = [];
    foreach ($form_state->getValue('fields', []) as $key => $field) {
      if (!empty($field)) {
        $fields[$key] = $field;
      }
    }
    $entity->setFields($fields);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    $this->messenger()->addMessage($this->t('Overview %label saved.', [
      '%label' => $this->entity->label(),
    ]));
  }

}
