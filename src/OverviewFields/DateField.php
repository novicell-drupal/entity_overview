<?php

namespace Drupal\entity_overview\OverviewFields;

class DateField extends OverviewFieldBase {
  protected $datetime_type = 'date';

  public function __construct($field_name, $label, $datetime_type) {
    parent::__construct($field_name, $label);
    $this->datetime_type = $datetime_type;
  }

  public function getWidgets(): array {
    if ($this->datetime_type == 'date') {
      return [$this->datetime_type => t('Date')];
    } else {
      return [$this->datetime_type => t('Datetime')];
    }
  }

}
