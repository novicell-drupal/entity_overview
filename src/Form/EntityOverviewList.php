<?php

namespace Drupal\entity_overview\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityOverviewList extends FormBase {

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
    return 'entity_overview.list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $list = $this->configFactory()->listAll('entity_overview.');

    $form['#attached']['library'][] = 'core/drupal.tableresponsive';

    $form['list'] = [
      '#type' => 'table',
      '#header' => [$this->t('Name'), $this->t('Entity types'), $this->t('Operations')],
      '#empty' => $this->t('There are no overview configurations yet.'),
      '#tableselect' => FALSE,
      '#attributes' => ['class' => ["responsive-enabled"]],
    ];
    foreach ($list as $config_id) {
      $config = $this->configFactory()->get($config_id);
      $id = $config->get('id');

      $form['list'][$id] = [];
      $form['list'][$id]['name'] = [
        '#type' => 'link',
        '#title' => $config->get('label') ?? $id,
        '#url' => Url::fromRoute('entity_overview.edit', ['id' => $id]),
      ];

      $labels = [];
      foreach ($config->get('entity_bundles') ?? [] as $entity_type_id => $bundles) {
        $bundle_labels = [];
        foreach ($bundles as $bundle) {
          $bundle_labels[] = $this->getBundleLabel($entity_type_id, $bundle);
        }
        $labels[] = '<strong>' . $this->getEntityTypeLabel($entity_type_id) . ':</strong> ' . implode(', ', $bundle_labels);
      }
      $form['list'][$id]['entity_type'] = [
        '#markup' => implode('<br/>', $labels),
      ];

      $links = [];
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('entity_overview.edit', ['id' => $id]),
      ];
      $links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('entity_overview.delete', ['id' => $id]),
      ];
      $form['list'][$id]['operations'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  protected function getEntityTypeLabel($entity_type_id) {
    $labels = &drupal_static(__FUNCTION__, []);

    if (!isset($labels[$entity_type_id])) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $labels[$entity_type_id] = $entity_type->getLabel();
    }
    return $labels[$entity_type_id];
  }

  protected function getBundleLabel($entity_type_id, $bundle) {
    $labels = &drupal_static(__FUNCTION__, []);

    if (!isset($labels[$entity_type_id][$bundle])) {
      $bundle_infos = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundle_infos as $bundle_id => $bundle_info) {
        $labels[$entity_type_id][$bundle_id] = $bundle_info['label'];
      }
    }
    return $labels[$entity_type_id][$bundle];
  }

}
