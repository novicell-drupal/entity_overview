<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\entity_overview\OverviewFilter;

class DateField extends OverviewFieldBase {
  protected $datetime_type = 'date';
  protected $date_range = FALSE;

  public function __construct($field_name, $label, $datetime_type) {
    parent::__construct($field_name, $label);
    if ($datetime_type == 'date_range') {
      $datetime_type = 'date';
      $this->date_range = TRUE;
    } elseif ($datetime_type == 'datetime_range') {
      $datetime_type = 'datetime';
      $this->date_range = TRUE;
    }
    $this->datetime_type = $datetime_type;
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormElement(OverviewFilter $filter): array {
    if ($this->date_range) {
      $element = [
        '#type' => 'fieldset',
        '#title' => $this->label(),
      ];
      $element['start'] = [
        '#type' => $this->datetime_type,
        '#parents' => [$this->field_name, 'start'],
        '#title' => t('Start date'),
      ];
      $element['end'] = [
        '#type' => $this->datetime_type,
        '#parents' => [$this->field_name, 'end'],
        '#title' => t('End date'),
      ];
      if (is_array($filter->getFieldValue($this->id()))) {
        foreach ($filter->getFieldValue($this->id()) as $index => $value) {
          $element[$index]['#default_value'] = $value;
        }
      }
      return $element;
    } else {
      return parent::getFieldFormElement($filter);
    }
  }


  /**
   * @inheritDoc
   */
  public function getWidgets(): array {
    if ($this->datetime_type == 'date') {
      return [$this->datetime_type => t('Date'), $this->datetime_type . '_range' => t('Date range')];
    } else {
      return [$this->datetime_type => t('Datetime'), $this->datetime_type . '_range' => t('Datetime Range')];
    }
  }

  /**
   * @inheritDoc
   */
  public function setFieldFormElementAttribute(array &$form, $attribute, $value): void {
    if ($this->date_range) {
      if ($attribute == 'description') {
        $form['#' . $attribute] = $value;
      } elseif ($attribute == 'default_value') {
        $form['start']['#default_value'] = $value['start'] ?? '';
        $form['end']['#default_value'] = $value['end'] ?? '';
      } else {
        $form['start']['#' . $attribute] = $value;
        $form['end']['#' . $attribute] = $value;
      }
    } else {
      parent::setFieldFormElementAttribute($form, $attribute, $value);
    }
  }

}
