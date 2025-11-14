<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_price\CurrencyFormatter; // concrete to avoid interface/Class mismatch
use Drupal\commerce_store\Entity\StoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Boost selection form.
 */
final class BoostSelectForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly CurrencyFormatter $currencyFormatter,
    private readonly CartManagerInterface $cartManager,
    private readonly CartProviderInterface $cartProvider,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart.cart_provider'),
    );
  }

  public function getFormId(): string {
    return 'myeventlane_boost_select_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array {
    if (!$node || $node->bundle() !== 'event') {
      $form['#markup'] = $this->t('This page requires an event.');
      return $form;
    }

    // Load published products of type "boost_upgrade".
    $pids = $this->etm->getStorage('commerce_product')->getQuery()
      ->condition('type', 'boost_upgrade')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    $form['#prefix'] = '<div class="boost-card">';
    $form['#suffix'] = '</div>';

    if (!$pids) {
      $form['empty'] = ['#markup' => $this->t('No boost options are available right now.')];
      return $form;
    }

    /** @var \Drupal\commerce_product\Entity\ProductInterface[] $products */
    $products = $this->etm->getStorage('commerce_product')->loadMultiple($pids);

    $options = [];
    $rows = [];

    foreach ($products as $product) {
      if (!$product instanceof ProductInterface) {
        continue;
      }
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations */
      $variations = $product->getVariations();
      foreach ($variations as $variation) {
        if (!$variation instanceof ProductVariationInterface) {
          continue;
        }

        $days = (int) ($variation->get('field_boost_days')->value ?? 0);
        $price = $variation->getPrice();
        $price_str = $price ? $this->currencyFormatter->format($price->getNumber(), $price->getCurrencyCode()) : '';

        $options[$variation->id()] = $this->t('@days days — @price', [
          '@days'  => $days,
          '@price' => $price_str,
        ]);

        $rows[$variation->id()] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['boost-row']],
          'icon' => ['#markup' => '<div class="boost-row__icon">⚡️</div>'],
          'text' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['boost-row__text']],
            'title' => ['#markup' => '<div class="boost-row__title">' . $this->t('@d days', ['@d' => $days]) . '</div>'],
            'desc' => ['#markup' => '<div class="boost-row__desc">' . $this->t('Keep your event featured for @d days.', ['@d' => $days]) . '</div>'],
          ],
          'price' => ['#markup' => '<div class="boost-row__price">' . $price_str . '</div>'],
        ];
      }
    }

    if (!$options) {
      $form['empty'] = ['#markup' => $this->t('No valid boost variations found.')];
      return $form;
    }

    $form['choices'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose a boost duration'),
      '#options' => $options,
      '#required' => TRUE,
      '#attributes' => ['class' => ['visually-hidden']],
    ];

    $form['styled_list'] = ['#type' => 'container', '#attributes' => ['class' => ['boost-list']]];
    foreach ($rows as $vid => $row) {
      $row['radio'] = [
        '#type' => 'radio',
        '#title' => '',
        '#return_value' => (string) $vid,
        '#parents' => ['choices'],
        '#attributes' => ['class' => ['boost-row__radio']],
      ];
      $form['styled_list'][$vid] = $row;
    }

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['boost-footer']],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Continue to checkout'),
        '#button_type' => 'primary',
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => $node->toUrl('canonical'),
        '#attributes' => ['class' => ['button', 'button--secondary']],
      ],
    ];

    $form['event_nid'] = ['#type' => 'value', '#value' => $node->id()];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('choices')) {
      $form_state->setErrorByName('choices', $this->t('Please select a boost option.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $nid = (int) $form_state->getValue('event_nid');
    $variation_id = (int) $form_state->getValue('choices');

    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->etm->getStorage('node')->load($nid);
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation */
    $variation = $this->etm->getStorage('commerce_product_variation')->load($variation_id);

    if (!$node instanceof NodeInterface || !$variation instanceof ProductVariationInterface) {
      $this->messenger()->addError($this->t('Invalid selection.'));
      $form_state->setRedirect('<front>');
      return;
    }

    // Resolve store from the parent product (variations don’t have stores).
    $store = $this->resolveStoreFromVariation($variation);
    if (!$store) {
      $this->messenger()->addError($this->t('No store available for this product.'));
      $form_state->setRedirect('<front>');
      return;
    }

    // Get/create cart for that store.
    $cart = $this->cartProvider->getCart('default', $store) ?: $this->cartProvider->createCart('default', $store);

    // Let Commerce create the correct order-item bundle and fields.
    $added = $this->cartManager->addEntity($cart, $variation, 1, TRUE);
    $order_item = is_array($added) ? reset($added) : $added;

    if ($order_item && $order_item->hasField('field_target_event')) {
      $order_item->set('field_target_event', ['target_id' => $node->id()]);
      $order_item->save();
    }

    $form_state->setRedirect('commerce_cart.page');
  }

  /**
   * Resolve a store for a variation (first product store, else default).
   */
  private function resolveStoreFromVariation(ProductVariationInterface $variation): ?StoreInterface {
    $product = $variation->getProduct();
    if ($product instanceof ProductInterface) {
      $stores = $product->getStores();
      if (!empty($stores)) {
        return reset($stores);
      }
    }
    $store_storage = $this->etm->getStorage('commerce_store');
    return method_exists($store_storage, 'loadDefault') ? $store_storage->loadDefault() : NULL;
  }
}
