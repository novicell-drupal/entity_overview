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

  protected $id;

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
    $config = $this->configFactory()->get('entity_overview.' . $this->id);

    return $this->t('Are you sure you want to delete the overview config %label?', [
      '%label' => $config->get('label')
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
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->id = $id;
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $label = $this->configFactory()->get('entity_overview.' . $this->id)->get('label');

    $this->configFactory()->getEditable('entity_overview.' . $this->id)->delete();
    $this->messenger()
      ->addStatus($this->t('Overview config %label has been deleted.', [
        '%label' => $label
      ]));
    $form_state->setRedirect('entity_overview.list');
  }

}
