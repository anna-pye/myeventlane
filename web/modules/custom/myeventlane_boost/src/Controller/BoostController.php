<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Boost controller: renders the single "pick a duration" selector form.
 */
final class BoostController extends ControllerBase {

  /**
   * Page title callback.
   */
  public function title(NodeInterface $node): string|TranslatableMarkup {
    return $this->t('Boost “@title”', ['@title' => $node->label()]);
  }

  /**
   * Route access callback.
   */
  public function access(NodeInterface $node): AccessResult {
    if ($node->bundle() !== 'event' || !$node->isPublished()) {
      return AccessResult::forbidden();
    }
    $account = $this->currentUser();
    $is_owner = ((int) $node->getOwnerId() === (int) $account->id());
    $can_purchase = $account->hasPermission('purchase boost for events') || $account->hasPermission('administer nodes');

    return AccessResult::allowedIf($is_owner || $can_purchase)
      ->addCacheableDependency($node)
      ->cachePerPermissions()
      ->cachePerUser();
  }

  /**
   * Page builder: hero + card with selector form + footer actions.
   */
  public function build(NodeInterface $node): array {
    if ($node->bundle() !== 'event') {
      throw new NotFoundHttpException();
    }

    // The selector form (radios + submit) lives in BoostSelectForm.
    // We pass the Node so the form can bind order item to this event.
    $form = \Drupal::formBuilder()->getForm(\Drupal\myeventlane_boost\Form\BoostSelectForm::class, $node);

    // A clean, styled "Cancel" link back to the event page.
    $cancel_link = Link::fromTextAndUrl(
      $this->t('Cancel'),
      $node->toUrl('canonical')
    )->toRenderable();
    // Hook for your CSS (ghost/secondary button look).
    $cancel_link['#attributes']['class'][] = 'mel-btn';
    $cancel_link['#attributes']['class'][] = 'mel-btn--ghost';
    $cancel_link['#attributes']['class'][] = 'boost-cancel';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-boost-page']],

      // Hero header (matches the screenshot + brand guide).
      'lead' => [
        '#markup' =>
          '<div class="boost-hero">'
            . '<div>'
              . '<h1 class="boost-title">' . $this->t('Boost “@title”', ['@title' => $node->label()]) . '</h1>'
              . '<div class="boost-kicker">' . $this->t('Featured placement + badge. Choose a boost duration below.') . '</div>'
            . '</div>'
            . '<div class="boost-hero__art" aria-hidden="true">📈</div>'
          . '</div>',
      ],

      // Card wrapper around the form (your library adds the visual).
      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['boost-card']],
        'form' => $form,
        // Footer actions under the form: Cancel on the left, submit lives in form.
        'footer' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['boost-footer']],
          'left' => $cancel_link,
          // Right side is the form submit (BoostSelectForm sets button label).
        ],
      ],

      '#attached' => [
        'library' => ['myeventlane_boost/boost'],
      ],
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => ['user', 'user.permissions'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Back-compat wrappers (if any old routes point to these names).
   */
  public function boostPage(NodeInterface $node): array {
    return $this->build($node);
  }
  public function boostTitle(NodeInterface $node): string|TranslatableMarkup {
    return $this->title($node);
  }
  public function boostAccess(NodeInterface $node): AccessResult {
    return $this->access($node);
  }

}
