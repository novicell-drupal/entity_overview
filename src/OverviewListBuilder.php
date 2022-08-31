<?php

namespace Drupal\entity_overview;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OverviewListBuilder extends DraggableListBuilder {

  /**
   * The entity overview manager.
   *
   * @var \Drupal\entity_overview\OverviewManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_overview.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, OverviewManager $manager) {
    parent::__construct($entity_type, $storage);
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_overview_overview_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['engine'] = $this->t('Search engine');
    $header['entity_bundles'] = $this->t('Entity types');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\entity_overview\OverviewInterface $entity */
    $row['label'] = $entity->label();
    $row['engine'] = $entity->getEngine()->label();

    $entity_bundles = [];
    $entity_types = $this->manager->getSupportedEntityTypes();
    foreach ($entity->getEntityBundles() as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $entity_bundles[] = $entity_types[$entity_type]['label'] . ' (' . $entity_types[$entity_type]['bundles'][$bundle]['label'] . ')';
      }
    }
    $row['entity_bundles'] = implode('<br>', $entity_bundles);
    return $row + parent::buildRow($entity);
  }
}
