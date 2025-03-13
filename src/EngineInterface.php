<?php

namespace Drupal\entity_overview;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_overview\Entity\Overview;

interface EngineInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Returns an administrative presentable label for the engine.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function label(): string|TranslatableMarkup;

  /**
   * Does the engine use entity types for indexing?
   *
   * @return bool
   */
  public function usesEntityTypes(): bool;

  /**
   * Does the engine support multiple entity types at once?
   *
   * @return bool
   */
  public function supportsMultipleEntities(): bool;

  /**
   * Does the engine support delivering search term recommendations?
   *
   * @return bool
   */
  public function supportsSearchTermRecommendations(): bool;

  /**
   * Does the engine support a certain base field?
   *
   * @param string $field Field ID.
   *
   * @return bool
   */
  public function supportsBaseField(string $field): bool;

  /**
   * Returns an array of field names that the engine supports based on selected entity types and bundles.
   *
   * @param array $entity_bundles Selected entity types and bundles.
   *
   * @return array
   */
  public function getSupportedFields(array $entity_bundles = []): array;

  /**
   * Returns an array of supported sorting fields based on selected entity types and bundles.
   *
   * @param array $entity_bundles Selected entity types and bundles.
   *
   * @return array Array keyed by id and translated labels as values
   */
  public function getSupportedSortFields(array $entity_bundles = []): array;

  /**
   * Returns an array of supported sorting criteria by the engine.
   *
   * @return array Array keyed by id and translated labels as values
   */
  public function getSortCriterias(): array;

  /**
   * Returns info about a supported field.
   *
   * @param \Drupal\entity_overview\Entity\Overview $overview Overview configuration.
   * @param string $field Field ID.
   *
   * @return \Drupal\entity_overview\OverviewFieldInfoInterface|null Returns field info or NULL if field not found.
   */
  public function getFieldInfo(Overview $overview, string $field): ?OverviewFieldInfoInterface;

  /**
   * Returns an OverviewResult object with results of the search based on the filter settings.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter Filter settings for the search.
   *
   * @return \Drupal\entity_overview\OverviewResultInterface Result object.
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface;

  /**
   * Defines the default settings for this plugin.
   *
   * @return array
   *   A list of default settings, keyed by the setting name.
   */
  public static function defaultSettings();

  /**
   * Returns the array of settings, including defaults for missing settings.
   *
   * @return array
   *   The array of settings.
   */
  public function getSettings();

  /**
   * Returns the value of a setting, or its default value if absent.
   *
   * @param string $key
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   */
  public function getSetting($key);

  /**
   * Sets the settings for the plugin.
   *
   * @param array $settings
   *   The array of settings, keyed by setting names. Missing settings will be
   *   assigned their default values.
   *
   * @return $this
   */
  public function setSettings(array $settings);

  /**
   * Sets the value of a setting for the plugin.
   *
   * @param string $key
   *   The setting name.
   * @param mixed $value
   *   The setting value.
   *
   * @return $this
   */
  public function setSetting($key, $value);

  /**
   * Returns a form to configure settings for the provider.
   *
   * Invoked from \Drupal\field_ui\Form\EntityDisplayFormBase to allow
   * administrators to configure the provider. The relewise module takes care
   * of handling submitted form values.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the provider settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state);

  /**
   * Returns list of all entity types that is supported for overviews.
   *
   * @return array
   */
  public function getSupportedEntityTypes();

  /**
   * Returns a summary of what the overview covers from the engine.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup;
   */
  public function getEngineSummary(Overview $overview): string|TranslatableMarkup;
}
