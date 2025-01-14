<?php
namespace Drupal\entity_overview\OverviewFields;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\entity_overview\OverviewFields\OverviewFieldBase;
use Drupal\entity_overview\OverviewFilter;

class EntityReferenceField extends OverviewFieldBase {

  protected array $options = [];

  public function __construct($field_name, $label, array $options) {
    parent::__construct($field_name, $label);
    $this->options = $options;
  }

  /**
   * @inheritDoc
   */
  public function getWidgets(): array {
    return ['checkboxes' => t('Check boxes'), 'radios' => t('Radios'), 'select' => t('Select list')];
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormElement(OverviewFilter $filter): array {
    return parent::getFieldFormElement($filter) + ['#options' => $this->getOptions($filter)];
  }

  /**
   * @inheritDoc
   */
  public function getFilterValueFromFormStateValue($value): mixed {
    $result = $value;
    if (is_array($value)) {
      $result = [];
      foreach ($value as $value2) {
        if ($value2) {
          $result[] = $value2;
        }
      }
    }
    return $result;
  }

  /**
   * Get a list of options for the field widget.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *   The current filter values.
   *
   * @return array
   *   List of options for the field widget.
   */
  public function getOptions(OverviewFilter $filter): array {
    if ($filter->getOverview()->getFieldWidget($this->id()) == 'checkboxes') {
      $options = $this->options ?? [];
    } else {
      $options = ['' => t('All')] + $this->options ?? [];
    }
    return $options;
  }

  /**
   * @inheritDoc
   */
  public function getFieldFormTransform(OverviewFilter $filter): array {
    $transformation = parent::getFieldFormTransform($filter) + ['options' => []];
    foreach ($this->getOptions($filter) as $key => $value) {
      $transformation['options'][] = [
        'key' => $key,
        'value' => $value
      ];
    }
    return $transformation;
  }

  public static function createFromFieldDefinition(FieldDefinitionInterface $definition): EntityReferenceField {
    $settings = $definition->getSettings() ?? [];
    $entityTypeManager = \Drupal::entityTypeManager();
    $storage = $entityTypeManager
      ->getStorage($settings['target_type']);
    $entity_type = $entityTypeManager->getDefinition($settings['target_type']);
    $keys = $entity_type->getKeys();
    $label = $definition->getLabel();
    $options = [];
    $bundles = array_values($settings['handler_settings']['target_bundles'] ?? []);
    $query = $storage->getQuery();
    if (!empty($bundles) && !empty($keys['bundle'])) {
      $query->condition($keys['bundle'], $bundles);
    }
    if (isset($settings['handler_settings']['sort']['field']) && $settings['handler_settings']['sort']['field'] !== '_none' && isset($settings['handler_settings']['sort']['direction'])) {
      $query->sort($settings['handler_settings']['sort']['field'], $settings['handler_settings']['sort']['direction']);
    }
    $ids = $query->accessCheck(TRUE)->execute();
    $entities = $storage->loadMultiple($ids);

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    foreach ($entities as $entity) {
      if ($entity instanceof TranslatableInterface && $entity->hasTranslation($langcode)) {
        $entity = $entity->getTranslation($langcode);
      }

      $options[$entity->id()] = $entity->label();
    }
    return new self($definition->getName(), $label, $options);
  }
}
