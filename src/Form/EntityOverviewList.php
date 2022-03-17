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
      '#header' => [$this->t('Name'), $this->t('Entity type'), $this->t('Operations')],
      '#empty' => $this->t('There are no overview configurations yet.'),
      '#tableselect' => FALSE,
      '#attributes' => ['class' => ["responsive-enabled"]],
    ];
    foreach ($list as $config_id) {
      $config = $this->configFactory()->get($config_id);
      $id = $config->get('id');

      $entity_type = $this->entityTypeManager->getDefinition($config->get('entity_type_id'));
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($config->get('entity_type_id'));

      $form['list'][$id] = [];
      $form['list'][$id]['name'] = [
        '#type' => 'link',
        '#title' => $bundle_info[$config->get('bundle')]['label'],
        '#url' => Url::fromRoute('entity_overview.edit', ['entity_bundle' => $id]),
      ];
      $form['list'][$id]['entity_type'] = [
        '#plain_text' => $entity_type->getLabel(),
      ];

      $links = [];
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('entity_overview.edit', ['entity_bundle' => $id]),
      ];
      $links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('entity_overview.delete', ['entity_bundle' => $id]),
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

}
