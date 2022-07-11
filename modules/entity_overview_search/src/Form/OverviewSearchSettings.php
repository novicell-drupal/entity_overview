<?php

namespace Drupal\entity_overview_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\EngineManager;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OverviewSearchSettings extends ConfigFormBase {

  protected $entityTypeManager;
  protected $entityFieldManager;
  protected $entityTypeBundleInfo;
  protected $overviewManager;
  protected $engineManager;

  function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, OverviewManager $overviewManager, EngineManager $engineManager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->overviewManager = $overviewManager;
    $this->engineManager = $engineManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_overview.manager'),
      $container->get('plugin.entity_overview.engine')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'entity_overview_search.settings';
  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['entity_overview_search.settings'];
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#tree'] = TRUE;

    $config = $this->config('entity_overview_search.settings');
    $overview_id = $form_state->getValue('overview') ?? $config->get('overview') ?? NULL;
    if (!empty($overview_id)) {
      $fields = $this->overviewManager->getFieldFormElements($overview_id);
      $base_facets = $this->overviewManager->getBaseFacets($overview_id);
    } else {
      $fields = [];
      $base_facets = [];
    }

    $form['overview'] = [
      '#type' => 'select',
      '#title' => $this->t('Overview'),
      '#description' => $this->t(''),
      '#options' => ['' => $this->t('None')] + $this->overviewManager->getOverviewConfigs(),
      '#default_value' => $overview_id,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::overviewCallback',
        'wrapper' => 'overview-search-settings',
        'progress' => [
          'type' => 'throbber',
        ]
      ]
    ];

    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => "overview-search-settings",
      ],
    ];

    if (!empty($overview_id)) {
      foreach ($base_facets as $id => $label) {
        switch ($id) {
          case 'count':
          case 'sort':
            $form['settings'][$id] = $this->overviewManager->getBaseFacetForm($overview_id, $id, $config->get($id) ?? NULL);
            $form['settings'][$id]['#title'] = $label;
            break;
          default:
            $form['settings']['fields'][$id] = $this->overviewManager->getBaseFacetForm($overview_id, $id, $config->get('fields.' . $id) ?? NULL);
            $form['settings']['fields'][$id]['#title'] = $label;
            break;
        }
      }

      $facets = $base_facets;
      foreach ($fields as $field_name => $form_element) {
        $facets[$field_name] = $form_element['label'];
      }
      $form['settings']['facets'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Facets'),
        '#description' => $this->t('Select the facets that you want to expose to the user.'),
        '#options' => $facets,
        '#default_value' => $config->get('facets') ?? [],
      ];

      $form['settings']['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode used to display search results.'),
        '#options' => $this->overviewManager->getViewModes(),
        '#default_value' => $config->get('view_mode') ?? 'teaser'
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('entity_overview_search.settings');
    $overview_id = $form_state->getValue('overview') ?? '';
    $config->setData(['overview' => $overview_id]);

    if (!empty($overview_id)) {
      $base_facets = $this->overviewManager->getBaseFacets($overview_id);

      if ($form_state->hasValue(['settings', 'view_mode'])) {
        $config->set('view_mode', $form_state->getValue([
          'settings',
          'view_mode'
        ]));
      }

      $fields = [];
      foreach ($base_facets as $id => $label) {
        switch ($id) {
          case 'count':
          case 'sort':
            if ($form_state->hasValue(['settings', $id])) {
              $config->set($id, $form_state->getValue([
                'settings',
                $id
              ]));
            }
            break;
          default:
            if ($form_state->hasValue(['settings', 'fields', $id])) {
              $value = $form_state->getValue([
                'settings',
                'fields',
                $id
              ]);
              if (!empty($value)) {
                $fields[$id] = $value;
              }
            }
            break;
        }
      }
      $config->set('fields', $fields);

      if ($form_state->hasValue(['settings', 'facets'])) {
        $facets = [];
        foreach ($form_state->getValue([
          'settings',
          'facets'
        ]) as $key => $value) {
          if (!empty($value)) {
            $facets[] = $key;
          }
        }
        $config->set('facets', $facets);
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback for refreshing settings.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function overviewCallback($form, FormStateInterface $form_state) {
    return $form['settings'];
  }
}
