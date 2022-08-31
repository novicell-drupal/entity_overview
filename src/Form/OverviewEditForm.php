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

    if ($this->entity->isNew()) {
      $engine_options = [];
      foreach ($this->engineManager->getDefinitions() as $key => $definition) {
        $engine_options[$definition['id']] = $this->t($definition['title'] ?? 'Engine');
      }
      $form['engine_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Search engine'),
        '#options' => $engine_options
      ];

      $form['entity_bundles'] = [
        '#type' => 'details',
        '#title' => $this->t('Entity types'),
        '#open' => TRUE,
        '#required' => TRUE
      ];

      $entity_bundles = $this->entity->getEntityBundles();
      $entity_types = $this->overviewManager->getSupportedEntityTypes();
      foreach ($entity_types as $entity_type) {
        $options = [];
        foreach ($entity_type['bundles'] as $bundle_id => $bundle) {
          $options[$bundle_id] = $bundle['label'];
        }

        $form['entity_bundles'][$entity_type['id']] = [
          '#type' => 'checkboxes',
          '#title' => $entity_type['label'],
          '#description' => $this->t(''),
          '#options' => $options,
          '#default_value' => $entity_bundles[$entity_type['id']] ?? [],
        ];
      }
    } else {
      /** @var \Drupal\entity_overview\EngineInterface $engine */
      $engine = $this->entity->getEngine();
      $form['engine_item'] = [
        '#type' => 'item',
        '#title' => $this->t('Search engine'),
        '#description' => $engine->label()
      ];

      $bundles = $this->entity->getEntityBundles();
      $entity_types = $this->overviewManager->getSupportedEntityTypes();
      $form['entity_bundles_item'] = [
        '#type' => 'item',
        '#title' => $this->t('Entity types'),
        '#description' => '',
        '#default_value' => $bundles
      ];
      foreach ($entity_types as $entity_type) {
        $entity_type_id = $entity_type['id'];
        if (empty($bundles[$entity_type_id])) {
          continue;
        }
        $bundle_labels = [];
        foreach ($bundles[$entity_type_id] as $bundle) {
          $bundle_labels[] = $entity_types[$entity_type_id]['label'] . ' (' . $entity_types[$entity_type_id]['bundles'][$bundle]['label'] . ')';
        }
        $form['entity_bundles_item']['#description'] = implode(', ', $bundle_labels);
      }

      $form['fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Fields'),
        '#options' => $this->entity->getSupportedFieldsWithLabels($bundles),
        '#description' => 'What fields can be used as facets?',
        '#default_value' => array_keys($this->entity->getFields())
      ];

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
        '#options' => $engine->getShowTotalOptions(),
        '#default_value' => $this->entity->getShowTotal() ?? ''
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($this->entity->isNew()) {
      $bundles = [];
      $entity_types = $this->overviewManager->getSupportedEntityTypes();
      foreach ($entity_types as $entity_type) {
        foreach ($form_state->getValue([
          'entity_bundles',
          $entity_type['id']
        ], []) as $bundle) {
          if (!empty($bundle)) {
            if (!isset($bundles[$entity_type['id']])) {
              $bundles[$entity_type['id']] = [];
            }
            $bundles[$entity_type['id']][] = $bundle;
          }
        }
      }
      if (empty($bundles)) {
        $form_state->setError($form['entity_bundles'], t('You must select at least one entity bundle.'));
      }
      else {
        $engine = $this->engineManager->createInstance($form_state->getValue('engine_id'));
        if (!$engine->supportsMultipleEntities() && count($bundles) > 1) {
          $form_state->setError($form['entity_bundles'], t('The %engine engine only supports a single entity type.', ['%engine' => $engine->label()]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_overview\OverviewInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    if ($entity->isNew()) {
      $bundles = [];
      $entity_types = $this->overviewManager->getSupportedEntityTypes();
      foreach ($entity_types as $entity_type) {
        foreach ($form_state->getValue([
          'entity_bundles',
          $entity_type['id']
        ], []) as $bundle) {
          if (!empty($bundle)) {
            if (!isset($bundles[$entity_type['id']])) {
              $bundles[$entity_type['id']] = [];
            }
            $bundles[$entity_type['id']][] = $bundle;
          }
        }
      }
      $entity->setEntityBundles($bundles);
      $entity->setSortField('created');
    } else {
      $engine = $this->entity->getEngine();
      $fields = [];
      foreach ($form_state->getValue('fields', []) as $field) {
        if (!empty($field)) {
          $info = $engine->getFieldInfo($this->entity, $field);
          if (!empty($info['widgets'])) {
            $fields[$field] = reset($info['widgets']);
          } else {
            $fields[$field] = $field;
          }
        }
      }
      $entity->setFields($fields);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $new = $this->entity->isNew();
    parent::save($form, $form_state);
    if ($new) {
      $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
    } else {
      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
      $this->messenger()->addMessage($this->t('Overview %label saved.', [
        '%label' => $this->entity->label(),
      ]));
    }
  }

}
