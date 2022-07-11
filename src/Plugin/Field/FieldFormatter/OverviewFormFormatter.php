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
      $options['overview'] = $items->getSetting('entity_bundle');
      $options['view_mode'] = $this->getSetting('view_mode');

      $elements[$delta] = \Drupal::formBuilder()->getForm('Drupal\entity_overview\Form\OverviewFilterForm', $options);
    }

    return $elements;
  }

}
