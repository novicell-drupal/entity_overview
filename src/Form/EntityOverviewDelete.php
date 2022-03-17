<?php

namespace Drupal\entity_overview\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityOverviewDelete extends ConfirmFormBase {

  protected $entity_bundle;

  protected $entityTypeBundleInfo;

  function __construct(EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  public function getQuestion() {
    $entity_info = explode('.', $this->entity_bundle);
    $entity_type_id = $entity_info[0];
    $bundle = $entity_info[1];
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);

    return $this->t('Are you sure you want to delete the overview config %entity_bundle?', [
      '%entity_bundle' => $bundle_info[$bundle]['label']
    ]);
  }

  public function getCancelUrl() {
    return Url::fromRoute('entity_overview.list');
  }

  public function getFormId() {
    return 'entity_overview.delete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_bundle = NULL) {
    $this->entity_bundle = $entity_bundle;
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_info = explode('.', $this->entity_bundle);
    $entity_type_id = $entity_info[0];
    $bundle = $entity_info[1];
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);

    $this->configFactory()->getEditable('entity_overview.' . $this->entity_bundle)->delete();
    $this->messenger()
      ->addStatus($this->t('Overview config %entity_bundle has been deleted.', [
        '%entity_bundle' => $bundle_info[$bundle]['label']
      ]));
    $form_state->setRedirect('entity_overview.list');
  }

}
