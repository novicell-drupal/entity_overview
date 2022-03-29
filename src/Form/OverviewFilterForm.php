<?php
namespace Drupal\entity_overview\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\html5history\Ajax\HistoryReplaceStateCommand;
use Drupal\entity_overview\OverviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class OverviewFilterForm extends FormBase {

  protected array $options = [];

  /**
   * @var OverviewManager
   */
  protected $overviewManager;

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
   * @param array $options
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $options = []) {
    $this->options = $options;
    $values = $this->options;
    $entity_bundle = $options['entity_bundle'];

    $form['#attributes']['class'][] = 'overview-form';
    $form['#attached']['library'] = array_merge($form['#attached']['library'] ?? [], ['html5history/html5history.ajax']);
    if (!empty($this->overviewManager->getEntityTypeID($entity_bundle))) {
      $form['#cache']['tags'][] = $this->overviewManager->getEntityTypeID($entity_bundle) . '_list';
    }

    $values['page'] = 0;
    unset($values['facets']);
    unset($values['_attributes']);
    foreach ($values as $key => $value) {
      if (!$form_state->has($key)) {
        $form_state->set($key, $value);
      }
      if ($this->request->query->has($key)) {
        $form_state->set($key, $this->request->query->get($key));
      }
      if ($form_state->hasValue($key)) {
        if (is_array($form_state->getValue($key))) {
          $result = [];
          foreach ($form_state->getValue($key) as $value2) {
            if ($value2) {
              $result[] = $value2;
            }
          }
          $form_state->set($key, $result);
        } else {
          $form_state->set($key, $form_state->getValue($key));
        }
      }
    }
    foreach ($values['fields'] as $key => $value) {
      if ($this->request->query->has($key)) {
        $form_state->set(['fields', $key], $this->request->query->get($key));
      }
      if ($form_state->hasValue($key)) {
        if (is_array($form_state->getValue($key))) {
          $result = [];
          foreach ($form_state->getValue($key) as $value2) {
            if ($value2) {
              $result[] = $value2;
            }
          }
          if ($form_state->get(['fields', $key]) != $result) {
            $form_state->set('page', 0);
            $form_state->set(['fields', $key], $result);
          }
        } else {
          if ($form_state->get(['fields', $key]) != $form_state->getValue($key)) {
            $form_state->set('page', 0);
            $form_state->set(['fields', $key], $form_state->getValue($key));
          }
        }
      }
    }

    $ajax = [
      'callback' => '::contentCallback',
      'event' => 'change',
      'wrapper' => 'overview-form-contents',
      'progress' => [
        'type' => 'throbber',
      ],
    ];
    $form['facets'] = [];
    $fields = $this->overviewManager->getFieldFormElements($options['entity_bundle']);
    foreach ($fields as $field_name => $form_element) {
      if (in_array($field_name, $options['facets'])) {
        $form['facets'][$field_name] = [
          '#type' => $form_element['form_element'],
          '#title' => $form_element['label'],
          '#options' => $form_element['options'],
          '#ajax' => $ajax
        ];
        if ($form_state->has(['fields', $field_name])) {
          $form['facets'][$field_name]['#default_value'] = $form_state->get(['fields', $field_name]);
        }
      }
    }

    if (in_array('count', $options['facets'])) {
      $form['facets']['count'] = [
        '#type' => 'select',
        '#options' => $this->overviewManager->getCountOptions(),
        '#default_value' => empty($form_state->get('count')) ? 5 : $form_state->get('count'),
        '#ajax' => $ajax
      ];
    }

    if (in_array('sort', $options['facets'])) {
      $form['facets']['sort'] = [
        '#type' => 'select',
        '#options' => $this->overviewManager->getSortCriterias($entity_bundle),
        '#default_value' => empty($form_state->get('sort')) ? 'newest' : $form_state->get('sort'),
        '#ajax' => $ajax
      ];
    }

    if (!empty($form['facets'])) {
      $form['facets']['#type'] = 'container';
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

    return $form;
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildContents(FormStateInterface $form_state) {
    $options = $this->optionsFromFormState($form_state);
    $entity_bundle = $form_state->get('entity_bundle') ?? 'node.page';
    $page = $options['page'];

    if (!$this->request->isXmlHttpRequest() || $form_state->isRebuilding()) {
      $entities = $this->getEntitiesForBuilding($entity_bundle, $options, $page);
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
    $this->buildEntitiesInContent($content, $entities, $options);

    if (!empty($options['show_total'])) {
      $content['total'] = [
        '#markup' => $this->getEntitiesTotal($entity_bundle, $options, count($entities))
      ];
    }

    if ($options['pagination']) {
      if (!$this->request->isXmlHttpRequest() || $form_state->isRebuilding()) {
        $content['pager'] = [
          '#type' => 'pager'
        ];
      }
    }
    return $content;
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  protected function optionsFromFormState(FormStateInterface $form_state) {
    $entity_bundle = $form_state->get('entity_bundle');
    $options = [
      'entity_bundle' => $form_state->get('entity_bundle'),
      'fields' => $form_state->get('fields'),
      'count' => $form_state->getValue('count') ?? $form_state->get('count'),
      'view_mode' => $form_state->get('view_mode'),
      'pagination' => $form_state->get('pagination'),
      'sort' => $form_state->getValue('sort') ?? $form_state->get('sort'),
      'show_total' => $form_state->get('show_total') ?? $this->overviewManager->getShowTotal($entity_bundle),
      'page' => $form_state->getValue('page') ?? $form_state->get('page') ?? 0
    ];
    $this->request->query->set('page', $options['page']);
    foreach ($options['fields'] as $key => $value) {
      if ($form_state->hasValue($key)) {
        if (is_array($form_state->getValue($key))) {
          $result = [];
          foreach ($form_state->getValue($key) as $value2) {
            if ($value2) {
              $result[] = $value2;
            }
          }
          $options['fields'][$key] = $result;
        } else {
          $options['fields'][$key] = $form_state->getValue($key);
        }
      }
    }
    return $options;
  }

  /**
   * Function for retrieving the entities to be displayed. Overwrite for when a custom query is necessary.
   *
   * @param string $entity_bundle
   * @param array $options
   * @param int $page
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  protected function getEntitiesForBuilding($entity_bundle, array $options, $page = 0) {
    return $this->overviewManager->getEntities($entity_bundle, $options, $page);
  }

  /**
   * Function for getting total number of entities. Overwrite for when a custom query is necessary.
   *
   * @param string $entity_bundle
   * @param array $options
   * @param int $shown
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected function getEntitiesTotal($entity_bundle, array $options, $shown) {
    return $this->overviewManager->getEntitiesTotal($entity_bundle, $options, $shown);
  }

  /**
   * Function for building the display of the entities. Overwrite for building overviews with custom layouts and views.
   *
   * @param array $content
   * @param EntityInterface[] $entities
   * @param array $options
   */
  protected function buildEntitiesInContent(array &$content, array $entities, array $options) {
    $content['content'] = \Drupal::entityTypeManager()->getViewBuilder($this->overviewManager->getEntityTypeID($options['entity_bundle']))->viewMultiple($entities, $options['view_mode']);
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
    $options = $this->optionsFromFormState($form_state);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.overview-form-contents', $form['content']));
    $url = Url::fromRoute('<current>');
    $data = ($options['fields'] ?? []) + ['sort' => $options['sort'], 'page' => $options['page']];
    $response->addCommand(new HistoryReplaceStateCommand(NULL, NULL, $url->toString() . '?' . http_build_query($data)));
    return $response;
  }
}
