<?php

namespace Drupal\entity_overview\Ajax;

/**
 * An ajax command for replacing the current history stack frame.
 */
class HistoryReplaceStateCommand extends AbstractStateCommand {

  /**
   * {@inheritdoc}
   */
  protected function commandName() {
    return 'history_replace_state';
  }

}
