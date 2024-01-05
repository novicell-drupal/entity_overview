<?php

use Drupal\entity_overview\OverviewFilter;

/**
 * This hook is invoked when the entity overview count options are calculated.
 *
 * It allows other modules to alter these options.
 *
 * @code
 * $options = [
 *   '5' => '5',
 *   '10' => '10',
 *   '15' => '15',
 *   '20' => '20',
 *   '25' => '25',
 * ]
 * @endcode
 *
 * @param array $options
 * @param string $overview_id
 *
 * @return void
 */
function hook_entity_overview_count_options_alter(&$options, $overview_id) {}

/**
 * This hook can be used to alter entity overview facet options.
 *
 * @param array $facet_options
 * @param OverviewFilter $filter
 *
 * @return void
 */
function hook_entity_overview_facet_options_alter(&$facet_options, $filter) {}
