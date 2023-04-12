<?php

namespace Drupal\entity_overview\Ajax;

/**
 * An ajax command for pushing to the browser history state.
 */
class HistoryPushStateCommand extends AbstractStateCommand {

  /**
   * {@inheritdoc}
   */
  protected function commandName() {
    return 'history_push_state';
  }

}
