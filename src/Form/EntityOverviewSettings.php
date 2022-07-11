<?php

namespace Drupal\entity_overview\Form;

use Drupal\Core\Form\FormStateInterface;

class EntityOverviewSettings extends \Drupal\Core\Form\FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'entity_overview.settings';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['deeplinks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable deeplinks'),
      '#description' => $this->t('Overviews will change url when filters are changed allowing users to bookmark specific filter settings. Will cost performance.'),
      '#default_value' => TRUE,
      '#attributes' => ['disabled' => TRUE]
    ];

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

}
