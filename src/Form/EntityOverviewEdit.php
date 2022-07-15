<?php

namespace Drupal\entity_overview\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\entity_overview\EngineManager;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityOverviewEdit extends FormBase {

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
    return 'entity_overview.edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    /*$entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);*/

    $config = $this->configFactory()->get('entity_overview.' . $id);
    $engine = $this->engineManager->createInstance($config->get('engine') ?? 'entity_query');

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $id
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $config->get('label'),
      '#required' => TRUE
    ];

    $form['engine'] = [
      '#type' => 'item',
      '#title' => $this->t('Engine'),
      '#description' => $engine->label()
    ];

    $options = [];
    $sort_options = [];
    $bundles = $config->get('entity_bundles') ?? [];
    $entity_types = $this->overviewManager->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type) {
      $entity_type_id = $entity_type['id'];
      if (empty($bundles[$entity_type_id])) {
        continue;
      }
      foreach ($bundles[$entity_type_id] as $bundle) {
        $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($definitions as $field_name => $definition) {
          // TODO: Support more entity types than taxonomy
          if ($definition->getType() == 'entity_reference' && in_array($definition->getSetting('target_type'), [
              'taxonomy_term',
              /*'user', 'media'*/
            ])) {
            $options[$field_name] = $definition->getLabel();
          }
          if (in_array($definition->getType(), ['created', 'changed', 'datetime'])) {
            $sort_options[$field_name] = $definition->getLabel();
          }
        }
      }
    }
    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Fields'),
      '#options' => $options,
      '#description' => 'What fields can be used as facets?',
      '#default_value' => array_keys($config->get('fields') ?? [])
    ];

    $form['sort_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => $sort_options,
      '#description' => $this->t('What field should be used for sorting?'),
      '#default_value' => $config->get('sort_field') ?? 'changed'
    ];

    $form['show_total'] = [
      '#type' => 'select',
      '#title' => t('Display of total number of items'),
      '#options' => $engine->getShowTotalOptions(),
      '#default_value' => $config->get('show_total') ?? ''
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $form_state->getValue('label');
    $overview_id = $form_state->getValue('id');

    $config = $this->configFactory()->getEditable('entity_overview.' . $overview_id);
    $config->set('id', $overview_id);
    $config->set('label', $label);

    $fields = [];
    foreach ($form_state->getValue('fields') as $key => $value) {
      if ($value) {
        $fields[$key] = 'checkboxes';
      }
    }
    $config->set('fields', $fields);

    $config->set('sort_field', $form_state->getValue('sort_field'));
    $config->set('show_total', $form_state->getValue('show_total'));
    $config->save();
    $this->messenger()->addStatus($this->t('Configuration saved.'));
    $form_state->setRedirect('entity_overview.list');
  }

  /**
   * @param $entity_bundle
   * @param $langcode
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function getTitle($id, $langcode = NULL) {
    $config = $this->configFactory()->get('entity_overview.' . $id);
    return $this->t('Edit %label', [
        '%label' => $config->get('label') ?? $id,
      ],
      ['langcode' => $langcode]
    );
  }

}
