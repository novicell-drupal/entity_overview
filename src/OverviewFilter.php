<?php

namespace Drupal\entity_overview;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\Entity\Overview;
use Symfony\Component\HttpFoundation\Request;

class OverviewFilter {
  protected string $overview_id = '';
  protected array $fields = [];
  protected array $facets = [];
  protected int $count = 5;
  protected string $sort = 'newest';
  protected bool $pagination = FALSE;
  protected int $page = 0;
  protected string $show_total = '';
  protected string $view_mode = 'teaser';

  public function __construct($overview_id, array $values) {
    $this->overview_id = $overview_id;
    if (isset($values['fields'])) {
      $this->setFieldValues($values['fields']);
    }
    if (isset($values['facets'])) {
      $this->setFacets($values['facets']);
    }
    if (isset($values['count'])) {
      $this->setCount($values['count']);
    }
    if (isset($values['sort'])) {
      $this->setSort($values['sort'] ?? 'newest');
    }
    if (isset($values['pagination'])) {
      $this->setPagination($values['pagination']);
    }
    if (isset($values['page'])) {
      $this->setPage($values['page'] ?? 0);
    }
    if (isset($values['view_mode'])) {
      $this->setViewMode($values['view_mode'] ?? 'teaser');
    }
  }

  /**
   * @return string
   */
  public function getOverviewId(): string {
    return $this->overview_id;
  }

  /**
   * @return \Drupal\entity_overview\Entity\Overview
   */
  public function getOverview(): Overview {
    return Overview::load($this->overview_id);
  }

  /**
   * @param $field string Field name
   *
   * @return mixed
   */
  public function getFieldValue(string $field): mixed {
    return $this->fields[$field] ?? NULL;
  }

  /**
   * @return array
   */
  public function getFieldValues(): array {
    return $this->fields;
  }

  /**
   * @param string $field
   * @param mixed $value
   *
   * @return OverviewFilter
   */
  public function setFieldValue(string $field, $value): OverviewFilter {
    $this->fields[$field] = $value;
    return $this;
  }

  /**
   * @param array $fields
   *
   * @return OverviewFilter
   */
  public function setFieldValues(array $fields): OverviewFilter {
    $this->fields = $fields;
    return $this;
  }

  /**
   * @param string $facet
   *
   * @return bool
   */
  public function hasFacet(string $facet): bool {
    return in_array($facet, $this->facets);
  }

  /**
   * @return array
   */
  public function getFacets(): array {
    return $this->facets;
  }

  /**
   * @param array $facets
   *
   * @return OverviewFilter
   */
  public function setFacets(array $facets): OverviewFilter {
    $this->facets = $facets;
    return $this;
  }

  /**
   * @return int
   */
  public function getCount(): int {
    return $this->count;
  }

  /**
   * @param int $count
   *
   * @return OverviewFilter
   */
  public function setCount(int $count): OverviewFilter {
    $this->count = $count;
    return $this;
  }

  /**
   * @return string
   */
  public function getSort(): string {
    return $this->sort;
  }

  /**
   * @param string $sort
   *
   * @return OverviewFilter
   */
  public function setSort(string $sort): OverviewFilter {
    $this->sort = $sort;
    return $this;
  }

  /**
   * @return bool
   */
  public function hasPagination(): bool {
    return $this->pagination;
  }

  /**
   * @param bool $pagination
   *
   * @return OverviewFilter
   */
  public function setPagination(bool $pagination): OverviewFilter {
    $this->pagination = $pagination;
    return $this;
  }

  /**
   * @return int
   */
  public function getPage(): int {
    return $this->page;
  }

  /**
   * @param int $page
   *
   * @return OverviewFilter
   */
  public function setPage(int $page): OverviewFilter {
    $this->page = $page;
    return $this;
  }

  /**
   * @param string $show_total
   *
   * @return OverviewFilter
   */
  public function setShowTotal(string $show_total): OverviewFilter {
    $this->show_total = $show_total;
    return $this;
  }

  /**
   * @return string
   */
  public function getShowTotal(): string {
    return $this->show_total;
  }

  /**
   * @param string $view_mode
   *
   * @return OverviewFilter
   */
  public function setViewMode(string $view_mode): OverviewFilter {
    $this->view_mode = $view_mode;
    return $this;
  }

  /**
   * @return string
   */
  public function getViewMode(): string {
    return $this->view_mode;
  }

  public function toArray(): array {
    return [
      'overview' => $this->getOverviewId(),
      'fields' => $this->getFieldValues(),
      'facets' => $this->getFacets(),
      'sort' => $this->getSort(),
      'count' => $this->getCount(),
      'pagination' => $this->hasPagination(),
      'page' => $this->getPage(),
      'show_total' => $this->getShowTotal(),
      'view_mode' => $this->getViewMode()
    ];
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   *
   * @return void
   */
  public function fetchRequestValues(Request $request = NULL) {
    if (is_null($request)) {
      $request = \Drupal::request();
    }

    if ($request->query->has('sort')) {
      $this->setSort($request->query->get('sort'));
    }
    if ($request->query->has('count')) {
      $this->setCount($request->query->get('count'));
    }
    if ($request->query->has('page')) {
      $this->setPage($request->query->get('page'));
    }
    foreach ($this->getFacets() as $field) {
      if ($request->query->has($field)) {
        $this->setFieldValue($field, $request->query->get($field));
      }
    }
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function updateFormState(FormStateInterface $form_state) {
    $form_state->set('overview', $this->getOverviewId());
    $form_state->set('pagination', $this->hasPagination());
    $form_state->set('show_total', $this->getShowTotal());
    $form_state->set('view_mode', $this->getViewMode());
    $this->updateFormStateValue($form_state, 'sort', $this->getSort());
    $this->updateFormStateValue($form_state, 'count', $this->getCount());
    $this->updateFormStateValue($form_state, 'page', $this->getPage());
    $this->updateFormStateValue($form_state, 'fields', $this->getFieldValues());
    foreach ($this->getFieldValues() as $key => $value) {
      $this->updateFormStateValue($form_state, $key, $value, TRUE);
    }
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param string $key
   * @param mixed $value
   * @param bool $isField
   */
  protected function updateFormStateValue(FormStateInterface $form_state, $key, $value, $isField = FALSE) {
    if (!$form_state->has($key)) {
      $form_state->set($key, $value);
    }
    if ($form_state->hasValue($key)) {
      $result = $form_state->getValue($key);
      if (is_array($form_state->getValue($key))) {
        $result = [];
        foreach ($form_state->getValue($key) as $value2) {
          if ($value2) {
            $result[] = $value2;
          }
        }
      }
      if ($isField) {
        if ($form_state->get(['fields', $key]) != $result) {
          $form_state->set('page', 0);
          $form_state->set(['fields', $key], $result);
        }
      } else {
        $form_state->set($key, $result);
      }
    }
  }

  /**
   * @param string $overview_id
   * @param array $values
   *
   * @return \Drupal\entity_overview\OverviewFilter
   */
  public static function createFromFormValues(string $overview_id, array $values): OverviewFilter {
    if (empty($values['facets'])) {
      $values['facets'] = [];
    }
    if (is_string($values['facets'])) {
      $values['facets'] = [$values['facets']];
    } elseif (is_array($values['facets'])) {
      $result = [];
      foreach ($values['facets'] as $value) {
        if (!empty($value)) {
          $result[] = $value;
        }
      }
      $values['facets'] = $result;
    }
    if (is_string($values['count'])) {
      $values['count'] = intval($values['count']);
    }
    foreach ($values['fields'] as $field_name => $selections) {
      if (is_array($selections)) {
        $result = [];
        foreach ($selections as $key => $value) {
          if (!empty($value)) {
            $result[] = $value;
          }
        }
        $values['fields'][$field_name] = $result;
      } elseif (is_null($selections)) {
        $values['fields'][$field_name] = '';
      }
    }
    $values['pagination'] = boolval($values['pagination'] ?? FALSE);
    if (!empty($values['view_mode'])) {
      $values['view_mode'] = strval($values['view_mode']);
    }

    return new self($overview_id, $values);
  }


  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\entity_overview\OverviewFilter
   */
  public static function createFromFormState(OverviewFilter $filter, FormStateInterface $form_state): OverviewFilter {
    /** @var \Drupal\entity_overview\OverviewManager $overviewManager */
    $overviewManager = \Drupal::service('entity_overview.manager');
    $overview_id = $form_state->get('overview');
    $overview = $overviewManager->getOverview($overview_id);
    $values = [
      'overview' => $overview_id,
      'fields' => $form_state->get('fields'),
      'view_mode' => $form_state->get('view_mode'),
      'pagination' => $form_state->get('pagination'),
      'show_total' => $form_state->get('show_total') ?? $overview->getShowTotal(),
      'page' => $form_state->getValue('page') ?? $form_state->get('page') ?? 0
    ];
    $field_info = $overviewManager->getAllFieldInfos($overview);
    foreach ($field_info as $field => $info) {
      if ($info['base']) {
        if ($form_state->hasValue($field) || $form_state->has($field) || $filter->hasFacet($field)) {
          $values[$field] = $form_state->getValue($field) ?? $form_state->get($field) ?? NULL;
        }
      } else {
        if ($form_state->hasValue($field)) {
          $result = $form_state->getValue($field);
          if (is_array($result)) {
            $result = [];
            foreach ($form_state->getValue($field) as $value2) {
              if ($value2) {
                $result[] = $value2;
              }
            }
          }
          $values['fields'][$field] = $result;
        }
      }
    }
    \Drupal::request()->query->set('page', $values['page']);

    return new self($overview_id, $values);
  }
}
