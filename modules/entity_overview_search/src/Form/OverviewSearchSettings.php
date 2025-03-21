<?php

namespace Drupal\entity_overview_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\EngineManager;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OverviewSearchSettings extends FormBase {
  use ConfigFormBaseTrait;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $config_factory;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * @var \Drupal\entity_overview\OverviewManager
   */
  private OverviewManager $overviewManager;

  /**
   * @var \Drupal\entity_overview\EngineManager
   */
  private EngineManager $engineManager;

  function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, OverviewManager $overviewManager, EngineManager $engineManager) {
    $this->config_factory = $config_factory;
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
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';
    $form['#tree'] = TRUE;

    $config = $this->config('entity_overview_search.settings');
    $overview_id = $form_state->getValue('overview') ?? $config->get('overview') ?? NULL;

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
      $filter = new OverviewFilter($overview_id, $config->get('filter') ?? []);
      $form['settings'] = $this->overviewManager->buildOverviewFilterForm($filter, TRUE);
      unset($form['settings']['pagination']);

      $form['settings']['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode used to display search results.'),
        '#options' => $this->overviewManager->getViewModes($filter->getOverview()),
        '#default_value' => $filter->getViewMode() ?? 'teaser'
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
      $filter = OverviewFilter::createFromFormValues($overview_id, $form_state->getValue('settings') ?? []);
      $filter->setShowTotal($filter->getOverview()->getShowTotal());
      $config->set('filter', $filter->toArray());
    }

    $config->save();
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
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
