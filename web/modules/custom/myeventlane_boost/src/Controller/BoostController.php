<?php

namespace Drupal\myeventlane_boost\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\node\Entity\Node;
use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_store\Resolver\StoreResolverInterface;


class BoostController extends ControllerBase {

  public function purchase(Node $node) {
    $duration = \Drupal::request()->query->get('duration', '3'); // Default = 3
    $sku = match ($duration) {
      '14' => 'boost-14day',
      '7' => 'boost-7day',
      default => 'boost-3day',
    };

    $variations = \Drupal::entityTypeManager()
      ->getStorage('commerce_product_variation')
      ->loadByProperties(['sku' => $sku]);

    $variation = reset($variations);
    if (!$variation) {
      $this->messenger()->addError("Boost product variation for $sku not found.");
      return $this->redirect('<front>');
    }

    $product = $variation->getProduct();

   $url = Url::fromRoute('entity.commerce_product_variation.add_to_cart_form', [
      'commerce_product_variation' => $variation->id(),
    ], [
      'query' => [
        'event_id' => $node->id(),
        'destination' => '/node/' . $node->id(),
      ],
    ]);

    return new RedirectResponse($url->toString());
  }

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

    $form = \Drupal::formBuilder()->getForm(\Drupal\myeventlane_boost\Form\BoostSelectForm::class, $node);

    $cancel_link = Link::fromTextAndUrl(
      $this->t('Cancel'),
      $node->toUrl('canonical')
    )->toRenderable();
    $cancel_link['#attributes']['class'][] = 'mel-btn';
    $cancel_link['#attributes']['class'][] = 'mel-btn--ghost';
    $cancel_link['#attributes']['class'][] = 'boost-cancel';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-boost-page']],

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

      'card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['boost-card']],
        'form' => $form,
        'footer' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['boost-footer']],
          'left' => $cancel_link,
        ],
      ],

      '#attached' => [
        'library' => ['myeventlane_boost/boost'],
      ],
    ];
  }


 /**
  * Bridge route: add boost variation to cart.
  */
 public function bridgeAddToCart(NodeInterface $node) {
  return $this->build($node);
   // // Determine duration from query (default to 3).
   // $duration = \Drupal::request()->query->get('duration', '3');
   // $sku = match ($duration) {
   //   '14' => 'boost-14day',
   //   '7'  => 'boost-7day',
   //   default => 'boost-3day',
   // };

   // Load the variation by SKU.
 //   $variations = \Drupal::entityTypeManager()
 //     ->getStorage('commerce_product_variation')
 //     ->loadByProperties(['sku' => $sku]);
 //   $variation = reset($variations);
 //   if (!$variation) {
 //     $this->messenger()->addError("Boost variation for SKU '$sku' not found.");
 //     return $this->redirect('<front>');
 //   }

 //   // Resolve the store.
 //   /** @var \Drupal\commerce_store\Resolver\StoreResolverInterface $store_resolver */
 //   $store_resolver = \Drupal::service('commerce_store.default_store_resolver');
 //   $store = $store_resolver->resolve();

 //   if (!$store) {
 //     $stores = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadMultiple();
 //     $store = reset($stores);
 //   }

 //   if (!$store instanceof \Drupal\commerce_store\Entity\Store) {
 //     $this->messenger()->addError('No store available for cart.');
 //     return $this->redirect('<front>');
 //   }

 //   // Load cart services.
 //   $cart_provider = \Drupal::service('commerce_cart.cart_provider');
 //   $cart_manager = \Drupal::service('commerce_cart.cart_manager');
 //   $account = \Drupal::currentUser();

 //   // Use correct signature: getCart(order_type, store, account).
 //   $cart = $cart_provider->getCart('default', $store, $account);
 //   if (!$cart) {
 //     $cart = $cart_provider->createCart('default', $store, $account);
 //   }

 //   // Add variation to cart.
 //   $cart_manager->addEntity($cart, $variation);

 //   // Redirect to cart page.
 //   return $this->redirect('commerce_cart.page');
 }
  /**
   * Backward compatibility wrappers for route defaults.
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