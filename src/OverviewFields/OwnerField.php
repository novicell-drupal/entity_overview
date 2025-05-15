<?php

namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\OverviewFieldInfoInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\transform_api\Transform\EntityAutocompleteEndpointTransform;
use Drupal\transform_api\Transform\EntityTransform;
use Drupal\user\Entity\User;

class OwnerField implements OverviewFieldInfoInterface {

  public function __construct() {
  }

  /**
   * @inheritDoc
   */
  public function id(): string {
    return 'owner';
  }

  /**
   * @inheritDoc
   */
  public function label(): string|TranslatableMarkup {
    return t('Author');
  }

  /**
   * @inheritDoc
   */
  public function getWidgets(): array {
    return ['entity_autocomplete' => t('Autocomplete')];
  }

  /**
   * @inheritDoc
   */
  public function isBase(): bool {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function canBeExposed(): bool {
    return TRUE;
  }

  /**
   * @inheritDoc
   */
  public function requiresFacets(): bool {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
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

  /**
   * @inheritDoc
   */
  public function updateFieldFormElementDefaultValue($value): mixed {
    $user = NULL;
    if (!empty($value)) {
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($value);
    }
    return $user;
  }

  /**
   * @inheritDoc
   */
  public function setFieldFormElementAttribute(array &$form, $attribute, $value): void {
    $form['#' . $attribute] = $value;
  }

  /**
   * @inheritDoc
   */
  public function getFilterValueFromFormStateValue($value): mixed {
    return $value;
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormTransform(OverviewFilter $filter): array {
    if (method_exists(\Drupal\transform_api\Transform\EntityTransform::class, 'createFromEntity')) {
      return [
        'type' => 'entity_autocomplete',
        'title' => $this->label(),
        'endpoint' => new EntityAutocompleteEndpointTransform('user'),
        'default_value' => new EntityTransform('user', $filter->getFieldValue($this->id()))
      ];
    } else {
      return [
        'type' => 'entity_autocomplete',
        'title' => $this->label(),
        'endpoint' => new EntityAutocompleteEndpointTransform('user'),
        'default_value' => new EntityTransform(User::load($filter->getFieldValue($this->id())))
      ];
    }
  }

}
