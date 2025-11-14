<?php

namespace Drupal\myeventlane_profile\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Views;
use Drupal\myeventlane_rsvp\Service\UserRsvpRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class MyProfileController extends ControllerBase {

  public function __construct(
    private readonly UserRsvpRepository $rsvpRepository,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('myeventlane_rsvp.user_rsvp_repository'),
    );
  }

  public function dashboard(): array {
    return [
      '#theme' => 'myeventlane_profile',
      '#title' => $this->t('My Profile'),
      '#content' => [
        '#type' => 'container',
        'intro' => ['#markup' => '<p>Use the tabs to view RSVPs, Tickets, or Settings.</p>'],
        'links' => [
          '#theme' => 'item_list',
          '#items' => [
            Link::fromTextAndUrl($this->t('My RSVPs'), Url::fromRoute('myeventlane_profile.rsvps')),
            Link::fromTextAndUrl($this->t('My Tickets'), Url::fromRoute('myeventlane_profile.tickets')),
            Link::fromTextAndUrl($this->t('Settings'), Url::fromRoute('myeventlane_profile.settings')),
          ],
        ],
      ],
    ];
  }

  public function rsvps(): array {
    $uid = (int) $this->currentUser()->id();
    $rows = $this->rsvpRepository->getUserRsvps($uid, 100);

    if (!$rows) {
      // Still wrap in page chrome so title renders.
      return [
        '#theme' => 'myeventlane_profile',
        '#title' => $this->t('My RSVPs'),
        '#content' => $this->emptyState($this->t('No RSVPs to show yet.')),
      ];
    }

    // Map repo rows to template-ready card items.

   $badgeMap = [
     'confirmed' => 'badge--ok',
     'active'    => 'badge--active',
     'waitlist'  => 'badge--wait',
     'canceled'  => 'badge--off',
     'cancelled' => 'badge--off',
   ];

   $items = [];
   foreach (array_values($rows) as $idx => $r) {
     $status_raw = strtolower(trim((string) ($r['status'] ?? 'rsvp')));
     $badge_class = $badgeMap[$status_raw] ?? 'badge--default';

     $eventLink = $r['event_url'] instanceof Url
       ? Link::fromTextAndUrl($r['event_title'], $r['event_url'])->toRenderable()
       : ['#markup' => $r['event_title']];

     $ctaView = NULL;
     if ($r['event_url'] instanceof Url) {
       $ctaView = Link::fromTextAndUrl($this->t('View event'), $r['event_url'])->toRenderable();
       $ctaView['#attributes']['class'][] = 'btn';
     }

     $ctaCancel = NULL;
     if (!empty($r['cancel_url'])) {
       $ctaCancel = Link::fromTextAndUrl($this->t('Cancel'), Url::fromUri($r['cancel_url']))->toRenderable();
       $ctaCancel['#attributes']['class'][] = 'btn';
     }

     $items[] = [
       'index'       => $idx + 1,
       'month'       => $r['month'] ?? NULL,
       'day'         => $r['day'] ?? NULL,
       'badge'       => $r['status'] ?: 'RSVP',
       'badge_class' => $badge_class,
       'image'       => $r['image'] ?? NULL,
       'title_link'  => $eventLink,
       'meta'        => $r['event_start_fmt'] ?? '',
       'cta_view'    => $ctaView,
       'cta_cancel'  => $ctaCancel,
     ];
   }


    // Cards block.
    $cards = [
      '#theme' => 'myeventlane_rsvp_cards',
      '#title' => $this->t('My RSVPs'),
      '#items' => $items,
      '#attributes' => ['class' => ['mel-rsvp-grid']], // scoping hook
    ];

    // Recommended block (View: recommended_for_you_affinity).
    $recommended = $this->buildRecommended();

    // Wrap in page chrome so the H1 renders and sections get spacing.
    return [
      '#theme' => 'myeventlane_profile',
      '#title' => $this->t('My RSVPs'),
      '#content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['mel-rsvps-page']],
        'section_title' => [
          '#markup' => '<h2 class="mel-section-title">'.$this->t('Your upcoming RSVPs').'</h2>',
        ],
        'cards' => $cards,
        'reco_title' => [
          '#markup' => '<h2 class="mel-section-title mel-reco-title">'.$this->t('Recommended for you').'</h2>',
        ],
        'recommended' => $recommended,
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['config:views.view.recommended_for_you_affinity'],
      ],
    ];
  }

  public function tickets(): array {
    if ($view = Views::getView('commerce_user_orders')) {
      $view->setDisplay('default');
      $view->setArguments([(string) $this->currentUser()->id()]);
      return $view->render();
    }
    if ($view = Views::getView('my_tickets')) {
      $view->setDisplay('default');
      return $view->render();
    }
    return $this->emptyState($this->t('No tickets to show yet.'));
  }

  public function settings(): array {
    return [
      '#theme' => 'myeventlane_profile',
      '#title' => $this->t('Account Settings'),
      '#content' => [
        '#markup' => '<p>Settings coming soon. Visit <a href="/user">My account</a> for now.</p>',
      ],
    ];
  }

  private function buildRecommended(): array {
    $view = Views::getView('recommended_for_you_affinity');
    if (!$view) {
      // Graceful fallback if the View isn’t installed yet.
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['mel-reco mel-empty']],
        'msg' => ['#markup' => '<p>'.$this->t('No recommendations right now.').'</p>'],
      ];
    }

    // Pick a sensible display.
    $displays = array_keys($view->storage->get('display') ?? []);
    $display_id = in_array('block_1', $displays, TRUE) ? 'block_1' : 'default';

    $view->setDisplay($display_id);
    // If your View expects contexts (e.g. user), set them here:
    // $view->setArguments([(string) $this->currentUser()->id()]);

    $build = $view->render();
    // Wrap to add classes without touching the View config.
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-reco', 'mel-reco-grid']],
      'view' => $build,
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['config:views.view.recommended_for_you_affinity'],
      ],
    ];
  }

  private function emptyState(string $msg): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-empty']],
      'msg' => ['#markup' => '<p>' . $msg . '</p>'],
      '#cache' => ['contexts' => ['user']],
    ];
  }
}
