<?php

namespace Drupal\entity_overview_search\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\Form\OverviewFilterForm;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\node\Entity\Node;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentAttributeSorting;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentDataSorting;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentPopularitySorting;
use Drupal\relewise\Relewise;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class OverviewSearchPageForm extends OverviewFilterForm {

  public function getFormId() {
    return 'entity_overview_search_page';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state, OverviewFilter $filter = NULL) {
    $config = $this->config('entity_overview_search.settings');
    $overview_id = $config->get('overview') ?? NULL;
    if (empty($overview_id)) {
      return [];
    }
    $filter = new OverviewFilter($overview_id, $config->get('filter') ?? []);
    $overview = $filter->getOverview();

    $filter->setPagination(TRUE);
    $filter->setShowTotal($overview->getShowTotal());

    $form = parent::buildForm($form, $form_state, $filter);

    $form['#cache']['max-age'] = 0;

    $title = $this->t('Search');
    if (!empty($form_state->get(['fields', 'text']))) {
      $title = $this->t('Search results for “@keyword”', [
        '@keyword' => $form_state->get([
          'fields',
          'text'
        ])
      ]);
    }
    $form['facets']['text']['#type'] = 'search';
    $form['facets']['text']['#attributes']['class'][] = 'overview-form-search-title';
    $form['facets']['text']['#title'] = $title;

    return $form;
  }

  /**
   * Function for building the display of the entities. Overwrite for building overviews with custom layouts and views.
   *
   * @param array $content
   * @param EntityInterface[] $entities
   * @param array $options
   */
  protected function buildEntitiesInContent(array &$content, array $entities, OverviewFilter $filter) {
    if (empty($entities)) {
      $content['content'] = [
        '#markup' => $this->t('Your search yielded no results.')->__toString()
      ];
    } else {
      parent::buildEntitiesInContent($content, $entities, $filter);
    }
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
    /** @var \Drupal\Core\Ajax\AjaxResponse $response */
    $response = parent::contentCallback($form, $form_state);
    if (empty($form_state->getValue('text'))) {
      $title = $this->t('Search results')
        ->__toString();
    } else {
      $title = $this->t('Search results for “@keyword”', ['@keyword' => $form_state->getValue('text')])
        ->__toString();
    }
    $response->addCommand(new HtmlCommand('.overview-form-search-title label', $title));
    return $response;
  }

}
