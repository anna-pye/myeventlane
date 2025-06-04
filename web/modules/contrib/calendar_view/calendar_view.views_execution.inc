<?php

/**
 * @file
 * Provides views runtime hooks for calendar_view.module.
 */

use Drupal\calendar_view\Plugin\views\style\CalendarViewInterface;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_pre_view().
 */
function calendar_view_views_pre_view(ViewExecutable $view) {
  $display_id = $view->current_display;
  /** @var \Drupal\calendar_view\Plugin\views\style\CalendarViewBase $style */
  $style = $view->displayHandlers->get($display_id)->getPlugin('style');
  if ($style instanceof CalendarViewInterface) {
    $style->makeFilterValuesRelative();
  }
}
