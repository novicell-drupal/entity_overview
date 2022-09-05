<?php
namespace Drupal\entity_overview\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\html5history\Ajax\HistoryReplaceStateCommand;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class OverviewFilterForm extends FormBase {

  protected OverviewFilter $filter;

  /**
   * @var OverviewManager
   */
  protected $overviewManager;

  protected ?OverviewResultInterface $result = NULL;

  /**
   * @var Request
   */
  protected $request;

  function __construct(OverviewManager $overviewManager, RequestStack $requestStack) {
    $this->overviewManager = $overviewManager;
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_overview.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_overview_filter_form';
  }

  /**
   * Builds the overview filter form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\entity_overview\OverviewFilter|null $filter
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, OverviewFilter $filter = NULL) {
    $this->filter = $filter;
    $overview = $filter->getOverview();

    $form['#theme'] = 'overview_form';
    $form['#overview'] = $filter->getOverviewId();
    $form['#attributes']['class'][] = 'overview-form';
    if ($this->overviewManager->deepLinksEnabled()) {
      $form['#attached']['library'] = array_merge($form['#attached']['library'] ?? [], ['html5history/html5history.ajax']);
      $filter->fetchRequestValues($this->request);
    }

    $filter->updateFormState($form_state);

    $ajax = [
      'callback' => '::contentCallback',
      'event' => 'change',
      'wrapper' => 'overview-form-contents',
      'progress' => [
        'type' => 'throbber',
      ],
    ];
    $form['facets'] = [];
    foreach ($this->overviewManager->getAllFieldInfos($overview) as $field => $info) {
      if ($filter->hasFacet($field)) {
        $form['facets'][$field] = $this->overviewManager->getFieldFormElement($filter, $field);
        $form['facets'][$field]['#ajax'] = $ajax;
        if ($info['base']) {
          $form['facets'][$field]['#default_value'] = $form_state->get($field);
        } else {
          if ($form_state->has(['fields', $field])) {
            $form['facets'][$field]['#default_value'] = $form_state->get([
              'fields',
              $field
            ]);
          }
        }
      }
    }

    if (!empty($form['facets'])) {
      $form['facets']['#type'] = 'container';
      $form['facets']['#attributes'] = [
        'id' => "overview-form-facets",
        'class' => ['overview-form-facets']
      ];
    }

    $form['content'] = $this->buildContents($form_state);

    $page = $form_state->get('page') ?? 0;
    if ($form_state->get('pagination')) {
      $form['#attached']['library'] = array_merge($form['#attached']['library'] ?? [], ['entity_overview/pager']);
      $form['page'] = [
        '#type' => 'hidden',
        '#attributes' => ['class' => ['overview-page-value']],
        '#default_value' => $page
      ];
      $form['page_submit'] = [
        '#type' => 'submit',
        '#attributes' => ['class' => ['visually-hidden', 'overview-page-submit']],
        '#submit' => ['::pageSubmit'],
        '#value' => $page,
        '#ajax' => [
          'callback' => '::contentCallback',
          'event' => 'click',
          'wrapper' => 'overview-form-contents',
          'progress' => [
            'type' => 'throbber',
          ],
        ]
      ];
    }

    $this->result->getCacheableMetadata()->applyTo($form);

    return $form;
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildContents(FormStateInterface $form_state) {
    $filter = OverviewFilter::createFromFormState($this->filter, $form_state);

    if (!$this->request->isXmlHttpRequest() || $form_state->isRebuilding()) {
      $entities = $this->getEntitiesForBuilding($filter);
    } else {
      $entities = [];
    }
    $content = [
      '#type' => 'container',
      '#attributes' => [
        'id' => "overview-form-contents",
        'class' => ['overview-form-contents']
      ],
    ];
    $this->buildEntitiesInContent($content, $entities, $filter);

    if (!empty($filter->getShowTotal())) {
      $content['total'] = $this->getEntitiesTotal($filter, count($entities));
    }

    if ($filter->hasPagination()) {
      if (!$this->request->isXmlHttpRequest() || $form_state->isRebuilding()) {
        $content['pager'] = [
          '#type' => 'pager'
        ];
      }
    }
    return $content;
  }

  /**
   * Function for retrieving the entities to be displayed. Overwrite for when a custom query is necessary.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  protected function getEntitiesForBuilding(OverviewFilter $filter) {
    return $this->getOverviewResult($filter)->getEntities();
  }

  /**
   * Function for getting total number of entities. Overwrite for when a custom query is necessary.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return array
   */
  protected function getEntitiesTotal(OverviewFilter $filter) {
    return [
      '#markup' => $filter->getOverview()->getTotalsText($this->getOverviewResult($filter))
    ];
  }

  /**
   * Function for building the display of the entities. Overwrite for building overviews with custom layouts and views.
   *
   * @param array $content
   * @param EntityInterface[] $entities
   * @param \Drupal\entity_overview\OverviewFilter $filter
   */
  protected function buildEntitiesInContent(array &$content, array $entities, OverviewFilter $filter) {
    $content['content'] = $this->overviewManager->buildEntitiesWithViewmode($entities, $filter->getViewMode());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Submit function for changing page via AJAX.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function pageSubmit(array &$form, FormStateInterface $form_state) {
    $page = intval($form_state->getValue('page'));
    $form_state->set('page', $page);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for refreshing content.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function contentCallback($form, FormStateInterface $form_state) {
    $filter = OverviewFilter::createFromFormState($this->filter, $form_state);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.overview-form-contents', $form['content']));
    if ($this->overviewManager->deepLinksEnabled()) {
      $url = Url::fromRoute('<current>');
      $data = $filter->getFieldValues();
      $array = $filter->toArray();
      foreach ($this->overviewManager->getBaseFields() as $facet) {
        if ($filter->hasFacet($facet)) {
          $data[$facet] = $array[$facet];
        }
      }
      if ($filter->hasPagination()) {
        $data['page'] = $filter->getPage();
      }
      $response->addCommand(new HistoryReplaceStateCommand(NULL, NULL, $url->toString() . '?' . http_build_query($data)));
    }
    return $response;
  }

  protected function getOverviewResult(OverviewFilter $filter) {
    if (empty($this->result)) {
      $this->result = $filter->getOverview()->getOverviewResult($filter);
    }
    return $this->result;
  }
}
