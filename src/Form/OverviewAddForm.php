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
 * Add form for overviews.
 */
class OverviewAddForm extends EntityForm {

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

    $engine_options = [];
    foreach ($this->engineManager->getDefinitions() as $key => $definition) {
      $engine_options[$definition['id']] = $this->t($definition['title'] ?? 'Engine');
    }
    if (!isset($engine_options[$this->entity->getEngineID() ?? ''])) {
      $engine_options = [$this->entity->getEngineID() ?? '' => ' - ' . $this->t('Select') . ' - '] + $engine_options;
      $engine = NULL;
    } else {
      $engine = $this->engineManager->createInstance($this->entity->getEngineID(), $this->entity->getEngineSettings() ?? []);
    }
    $ajax = [
      'callback' => '::engineCallback',
      'event' => 'change',
      'wrapper' => 'overview-engine-settings',
      'progress' => [
        'type' => 'throbber',
      ],
    ];
    $form['engine_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Search engine'),
      '#options' => $engine_options,
      '#default_value' => $this->entity->getEngineID() ?? '',
      '#ajax' => $ajax,
      '#required' => TRUE
    ];

    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'overview-engine-settings'
      ],
      '#parents' => []
    ];

    if (!empty($engine)) {
      $form['settings']['engine_settings'] = $engine->settingsForm([], $form_state);

      $form['settings']['entity_bundles'] = [
        '#type' => 'details',
        '#title' => $this->t('Entity types'),
        '#open' => TRUE,
        '#required' => TRUE
      ];

      $entity_bundles = $this->entity->getEntityBundles();
      $entity_types = $engine->getSupportedEntityTypes();
      foreach ($entity_types as $entity_type) {
        $options = [];
        foreach ($entity_type['bundles'] as $bundle_id => $bundle) {
          $options[$bundle_id] = $bundle['label'];
        }

        $form['settings']['entity_bundles'][$entity_type['id']] = [
          '#type' => 'checkboxes',
          '#title' => $entity_type['label'],
          '#description' => $this->t(''),
          '#options' => $options,
          '#default_value' => $entity_bundles[$entity_type['id']] ?? [],
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback for refreshing settings.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function engineCallback($form, FormStateInterface $form_state) {
    return $form['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->isRebuilding()) {
      return;
    }
    if (isset($form['settings']['entity_bundles'])) {
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
        $form_state->setError($form['settings']['entity_bundles'], t('You must select at least one entity bundle.'));
      }
      else {
        $engine = $this->engineManager->createInstance($form_state->getValue('engine_id'), $form_state->getValue('engine_settings') ?? []);
        if (!$engine->supportsMultipleEntities() && count($bundles) > 1) {
          $form_state->setError($form['settings']['entity_bundles'], t('The %engine engine only supports a single entity type.', ['%engine' => $engine->label()]));
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

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('edit-form'));
  }

}
