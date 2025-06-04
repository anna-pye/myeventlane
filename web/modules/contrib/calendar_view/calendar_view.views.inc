<?php

/**
 * @file
 * Provide views data for calendar_view.module.
 */

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Implements hook_views_data().
 */
function calendar_view_views_data() {
  $data['views']['calendar_timestamp'] = [
    'title' => t('Jump to'),
    'group' => t('Calendar View'),
    'help' => t('Allow users to jump to any specific point in time using %dateformats.', [
      '%dateformats' => Link::fromTextAndUrl(
        t('date formats'),
        Url::fromUri('https://www.php.net/manual/en/datetime.formats.php', ['attributes' => ['target' => '_blank']])
      )->toString(),
    ]),
    'filter' => [
      'id' => 'calendar_view_timestamp',
      'allow empty' => TRUE,
    ],
  ];

  return $data;
}
