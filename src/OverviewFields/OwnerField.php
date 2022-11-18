<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\transform_api\Transform\EntityAutocompleteEndpointTransform;

class OwnerField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  public function id(): string {
    return 'owner';
  }

  public function label(): string|TranslatableMarkup {
    return t('Author');
  }

  public function getWidgets(): array {
    return ['entity_autocomplete' => t('Autocomplete')];
  }

  public function isBase(): bool {
    return FALSE;
  }

  public function canBeExposed(): bool {
    return TRUE;
  }

  public function requiresFacets(): bool {
    return FALSE;
  }

  public function getFieldFormElement(OverviewFilter $filter): array {
    $user = NULL;
    if (!empty($filter->getFieldValue($this->id()))) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($filter->getFieldValue($this->id()));
    }
    return [
      '#type' => 'entity_autocomplete',
      '#title' => $this->label(),
      '#target_type' => 'user',
      '#default_value' => $user
    ];
  }

  public function updateFieldFormElementDefaultValue($value): mixed {
    $user = NULL;
    if (!empty($value)) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($value);
    }
    return $user;
  }

  public function getFieldFormTransform(OverviewFilter $filter): array {
    return [
      'type' => 'entity_autocomplete',
      'title' => $this->label(),
      'endpoint' => new EntityAutocompleteEndpointTransform('user'),
      'default_value' => new \Drupal\transform_api\Transform\EntityTransform('user', $filter->getFieldValue($this->id()))
    ];
  }

}
