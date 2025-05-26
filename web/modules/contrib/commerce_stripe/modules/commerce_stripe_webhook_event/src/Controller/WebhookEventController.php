<?php

namespace Drupal\commerce_stripe_webhook_event\Controller;

use Drupal\commerce_stripe_webhook_event\Form\WebhookEventFilterForm;
use Drupal\commerce_stripe_webhook_event\WebhookEvent;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for commerce stripe webhook event routes.
 */
class WebhookEventController extends ControllerBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->database = $container->get('database');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * Displays a listing of webhook events.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   *
   * @see \Drupal\commerce_stripe_webhook_event\Form\WebhookEventPurgeConfirmForm
   * @see \Drupal\commerce_stripe_webhook_event\Controller\WebhookEventController::eventDetails()
   */
  public function overview(Request $request): array {
    $filter = $this->buildFilterQuery($request);
    $rows = [];

    $build['commerce_stripe_webhook_event_filter_form'] = $this->formBuilder()->getForm(WebhookEventFilterForm::class);

    $header = [
      // Icon column.
      '',
      [
        'data' => $this->t('Stripe Event ID'),
        'field' => 'w.stripe_event_id',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Type'),
        'field' => 'w.type',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Status'),
        'field' => 'w.status',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      [
        'data' => $this->t('Processed'),
        'field' => 'w.processed',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('Received'),
        'field' => 'w.received',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      [
        'data' => $this->t('IP'),
        'field' => 'w.ip',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    $query = $this->database->select('commerce_stripe_webhook_event', 'w')
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    $query->fields('w', [
      'webhook_event_id',
      'stripe_event_id',
      'type',
      'status',
      'processed',
      'received',
      'ip',
    ]);

    if (!empty($filter['where'])) {
      $query->where($filter['where'], $filter['args']);
    }
    $webhook_events = $query
      ->limit(50)
      ->orderByHeader($header)
      ->execute();

    $statuses = WebhookEvent::getStatuses();
    $statuses_raw = WebhookEvent::getStatusesRaw();
    foreach ($webhook_events as $webhook_event) {
      $link = Link::createFromRoute($webhook_event->stripe_event_id, 'commerce_stripe_webhook_event.event', ['webhook_event_id' => $webhook_event->webhook_event_id])->toString();
      $processed = ($webhook_event->processed > 0) ? $this->dateFormatter->format($webhook_event->processed, 'custom', 'Y-m-d H:i:s') : '';
      $status = $statuses[$webhook_event->status];
      $status_raw = $statuses_raw[$webhook_event->status];
      $rows[] = [
        'data' => [
          // Cells.
          ['class' => ['icon']],
          $link,
          $webhook_event->type,
          $status,
          $processed,
          $this->dateFormatter->format($webhook_event->received, 'custom', 'Y-m-d H:i:s'),
          $webhook_event->ip,
        ],
        // Attributes for table row.
        'class' => [
          Html::getClass('webhook-event-' . $status_raw),
          Html::getClass($webhook_event->type),
        ],
      ];
    }

    $build['webhook_event_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'id' => 'admin-webhook-event',
        'class' => ['admin-webhook-event'],
      ],
      '#empty' => $this->t('No webhook events available.'),
      '#attached' => [
        'library' => ['commerce_stripe_webhook_event/webhook_event'],
      ],
    ];
    $build['webhook_event_pager'] = ['#type' => 'pager'];

    return $build;

  }

  /**
   * Displays details about a specific webhook event.
   *
   * @param int $webhook_event_id
   *   Unique ID of the webhook event.
   *
   * @return array
   *   If the ID is located in the webhook event table, a build array in the
   *   format expected by \Drupal\Core\Render\RendererInterface::render().
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If no event found for the given ID.
   */
  public function eventDetails(int $webhook_event_id): array {
    $webhook_event = $this->database->query('SELECT [w].* FROM {commerce_stripe_webhook_event} [w] WHERE [w].[webhook_event_id] = :id', [':id' => $webhook_event_id])->fetchObject();

    if (empty($webhook_event)) {
      throw new NotFoundHttpException();
    }

    $build = [];
    $statuses = WebhookEvent::getStatuses();
    $status = $statuses[$webhook_event->status];
    $processed = ($webhook_event->processed > 0) ? $this->dateFormatter->format($webhook_event->processed, 'custom', 'Y-m-d H:i:s') : '';
    $rows = [
      [
        ['data' => $this->t('Stripe Event ID'), 'header' => TRUE],
        $webhook_event->stripe_event_id,
      ],
      [
        ['data' => $this->t('Type'), 'header' => TRUE],
        $webhook_event->type,
      ],
      [
        ['data' => $this->t('Status'), 'header' => TRUE],
        $status,
      ],
      [
        ['data' => $this->t('Received'), 'header' => TRUE],
        $this->dateFormatter->format($webhook_event->received, 'custom', 'Y-m-d H:i:s'),
      ],
      [
        ['data' => $this->t('Processed'), 'header' => TRUE],
        $processed,
      ],
      [
        ['data' => $this->t('Payload'), 'header' => TRUE],
        ['data' => $webhook_event->payload, 'class' => 'pre'],
      ],
      [
        ['data' => $this->t('Signature'), 'header' => TRUE],
        $webhook_event->signature,
      ],
      [
        ['data' => $this->t('Reason'), 'header' => TRUE],
        $webhook_event->reason,
      ],
      [
        ['data' => $this->t('IP'), 'header' => TRUE],
        $webhook_event->ip,
      ],
      [
        ['data' => $this->t('Stripe object type'), 'header' => TRUE],
        $webhook_event->stripe_object_type,
      ],
      [
        ['data' => $this->t('Stripe object id'), 'header' => TRUE],
        $webhook_event->stripe_object_id,
      ],
      [
        ['data' => $this->t('Entity type'), 'header' => TRUE],
        $webhook_event->entity_type,
      ],
      [
        ['data' => $this->t('Entity id'), 'header' => TRUE],
        $webhook_event->entity_id,
      ],
    ];
    $build['webhook_event_table'] = [
      '#type' => 'table',
      '#rows' => $rows,
      '#attributes' => ['class' => ['webhook-event']],
      '#attached' => [
        'library' => ['commerce_stripe_webhook_event/webhook_event'],
      ],
    ];

    return $build;
  }

  /**
   * Builds a query for webhook event filters based on session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array|null
   *   An associative array with keys 'where' and 'args' or NULL if there were
   *   no filters set.
   */
  protected function buildFilterQuery(Request $request): ?array {
    $session_filters = $request->getSession()->get('commerce_stripe_webhook_event_overview_filter', []);
    if (empty($session_filters)) {
      return NULL;
    }

    $filters = WebhookEvent::getFilters();

    // Build query.
    $where = $args = [];
    foreach ($session_filters as $key => $filter) {
      $filter_where = [];
      foreach ($filter as $value) {
        $filter_where[] = $filters[$key]['where'];
        $args[] = $value;
      }
      if (!empty($filter_where)) {
        $where[] = '(' . implode(' OR ', $filter_where) . ')';
      }
    }
    $where = !empty($where) ? implode(' AND ', $where) : '';

    return [
      'where' => $where,
      'args' => $args,
    ];
  }

}
