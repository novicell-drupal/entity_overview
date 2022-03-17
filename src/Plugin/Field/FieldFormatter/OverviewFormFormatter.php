<?php
namespace Drupal\entity_overview\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'overview_filter_form' formatter.
 *
 * @FieldFormatter(
 *   id = "overview_filter_form",
 *   label = @Translation("Overview filter form"),
 *   field_types = {
 *     "overview_filter"
 *   }
 * )
 */
class OverviewFormFormatter extends OverviewListFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $options = $item->getValue();
      $options['entity_bundle'] = $items->getSetting('entity_bundle');
      $options['view_mode'] = $this->getSetting('view_mode');
      $options['show_total'] = $this->getSetting('show_total');
      $elements[$delta] = \Drupal::formBuilder()->getForm('Drupal\entity_overview\Form\OverviewFilterForm', $options);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'show_total' => '',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
        'show_total' => [
          '#type' => 'select',
          '#title' => t('Display of total number of elements'),
          '#options' => $this->overviewManager->getShowTotalOptions(),
          '#default_value' => $this->getSetting('show_total'),
          '#required' => FALSE,
        ],

        // Implement settings form.
      ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if (!empty($this->getSetting('show_total'))) {
      $options = $this->overviewManager->getShowTotalOptions();
      $summary[] = $this->t('Total display: @show_total', [
        '@show_total' => $options[$this->getSetting('show_total')]
      ]);
    }

    return $summary;
  }

}
