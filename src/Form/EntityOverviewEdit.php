<?php

namespace Drupal\entity_overview\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityOverviewEdit extends FormBase {

  protected $entityTypeManager;
  protected $entityFieldManager;
  protected $entityTypeBundleInfo;

  function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
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
  public function buildForm(array $form, FormStateInterface $form_state, $entity_bundle = NULL) {
    $entity_info = explode('.', $entity_bundle);
    $entity_type_id = $entity_info[0];
    $bundle = $entity_info[1];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    $config = $this->configFactory()->get('entity_overview.' . $entity_bundle);

    $form['entity_type_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Entity type'),
      '#description' => $entity_type->getLabel()
    ];
    $form['entity_type_id'] = [
      '#type' => 'hidden',
      '#value' => $entity_type_id
    ];

    $form['entity_bundle_info'] = [
      '#type' => 'item',
      '#title' => $entity_type->getBundleLabel(),
      '#description' => $bundle_info[$bundle]['label']
    ];
    $form['bundle'] = [
      '#type' => 'hidden',
      '#value' => $bundle
    ];

    $options = [];
    foreach ($definitions as $field_name => $definition) {
      // TODO: Support more entity types than taxonomy
      if ($definition->getType() == 'entity_reference' && in_array($definition->getSetting('target_type'), ['taxonomy_term'/*, 'user', 'media'*/])) {
        $options[$field_name] = $definition->getLabel();
      }
    }
    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Fields'),
      '#options' => $options,
      '#description' => 'What fields can be used as facets?',
      '#default_value' => array_keys($config->get('fields') ?? [])
    ];

    $sort_options = [];
    foreach ($definitions as $field_name => $definition) {
      if (in_array($definition->getType(), ['created', 'changed', 'datetime'])) {
        $sort_options[$field_name] = $definition->getLabel();
      }
    }
    $form['sort_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#options' => $sort_options,
      '#description' => $this->t('What field should be used for sorting?'),
      '#default_value' => $config->get('sort_field') ?? 'changed'
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
    $entity_type_id = $form_state->getValue('entity_type_id');
    $bundle = $form_state->getValue('bundle');
    $entity_bundle = $entity_type_id . '.' . $bundle;

    $config = $this->configFactory()->getEditable('entity_overview.' . $entity_bundle);
    $config->set('id', $entity_bundle);
    $config->set('entity_type_id', $entity_type_id);
    $config->set('bundle', $bundle);

    $fields = [];
    foreach ($form_state->getValue('fields') as $key => $value) {
      if ($value) {
        $fields[$key] = 'checkboxes';
      }
    }
    $config->set('fields', $fields);

    $config->set('sort_field', $form_state->getValue('sort_field'));
    $config->save();
    $this->messenger()->addStatus($this->t('Configuration saved.'));
    $form_state->setRedirect('entity_overview.list');
  }

  public function getTitle($entity_bundle, $langcode = NULL) {
    $entity_info = explode('.', $entity_bundle);
    $entity_type_id = $entity_info[0];
    $bundle = $entity_info[1];
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    return $this->t('Edit %label', [
        '%label' => $bundle_info[$bundle]['label'],
      ],
      ['langcode' => $langcode]
    );
  }

}
