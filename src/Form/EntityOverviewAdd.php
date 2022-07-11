<?php

namespace Drupal\entity_overview\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\EngineManager;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityOverviewAdd extends \Drupal\Core\Form\FormBase {

  protected $entityTypeManager;
  protected $entityFieldManager;
  protected $entityTypeBundleInfo;
  protected $overviewManager;
  protected $engineManager;

  function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, OverviewManager $overviewManager, EngineManager $engineManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->overviewManager = $overviewManager;
    $this->engineManager = $engineManager;
  }

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_overview.add';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $config = $this->configFactory()->get('entity_overview.' . $id);
    $form = [
      '#tree' => TRUE
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $config->get('label'),
      '#required' => TRUE
    ];

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $config->get('id'),
      '#disabled' => !$config
        ->isNew(),
      '#maxlength' => 64,
      '#description' => $this
        ->t('A unique name for this item. It must only contain lowercase letters, numbers, and underscores.'),
      '#machine_name' => array(
        'exists' => array(
          $this,
          'exists',
        ),
      ),
    );

    $engine_options = [];
    foreach ($this->engineManager->getDefinitions() as $key => $definition) {
      $engine_options[$definition['id']] = $this->t($definition['title'] ?? 'Engine');
    }
    $form['engine'] = [
      '#type' => 'select',
      '#title' => $this->t('Search engine'),
      '#options' => $engine_options
    ];

    $form['entity_bundles'] = array(
      '#type' => 'details',
      '#title' => $this->t('Entity types'),
      '#open' => TRUE,
      '#required' => TRUE
    );

    $bundles = $config->get('entity_bundles') ?? [];
    $entity_types = $this->overviewManager->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type) {
      $options = [];
      foreach ($entity_type['bundles'] as $bundle_id => $bundle) {
        $options[$bundle_id] = $bundle['label'];
      }

      $form['entity_bundles'][$entity_type['id']] = array(
        '#type' => 'checkboxes',
        '#title' => $entity_type['label'],
        '#description' => $this->t(''),
        '#options' => $options,
        '#default_value' => $bundles[$entity_type['id']] ?? []
      );
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
    ];
    return $form;

  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $bundles = [];
    $entity_types = $this->overviewManager->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type) {
      foreach ($form_state->getValue(['entity_bundles', $entity_type['id']], []) as $bundle) {
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
    } else {
      $engine = $this->engineManager->createInstance($form_state->getValue('engine'));
      if (!$engine->supportsMultipleEntities() && count($bundles) > 1) {
        $form_state->setError($form['entity_bundles'], t('The %engine engine only supports a single entity type.', ['%engine' => $engine->label()]));
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id = $form_state->getValue('id');
    $label = $form_state->getValue('label');

    $config = $this->configFactory()->getEditable('entity_overview.' . $id);
    $config->set('id', $id);
    $config->set('label', $label);
    $config->set('engine', $form_state->getValue('engine'));
    $engine = $this->engineManager->createInstance($form_state->getValue('engine'));

    $bundles = [];
    $entity_types = $this->overviewManager->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type) {
      foreach ($form_state->getValue(['entity_bundles', $entity_type['id']], []) as $bundle) {
        if (!empty($bundle)) {
          if (!isset($bundles[$entity_type['id']])) {
            $bundles[$entity_type['id']] = [];
          }
          $bundles[$entity_type['id']][] = $bundle;
        }
      }
    }
    $config->set('entity_bundles', $bundles);

    $config->set('fields', []);
    $config->set('sort_field', 'created');
    $config->set('show_total', '');

    $config->save();
    $this->messenger()->addStatus($this->t('Configuration added.'));
    $form_state->setRedirect('entity_overview.edit', ['id' => $id]);
  }

  /**
   * Determines if the config already exists.
   *
   * @param string $id
   *   The action ID.
   *
   * @return bool
   *   TRUE if the action exists, FALSE otherwise.
   */
  public function exists($id) {
    $config = $this->configFactory()->get('entity_overview.' . $id);
    return !$config->isNew();
  }

}
