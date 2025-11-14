<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;

final class BoostPurchaseForm extends FormBase {

  /** @var \Drupal\node\NodeInterface|null */
  protected $event;

  /** @var array<int,array{id:int,label:string,days:int,price:string}> */
  protected array $plans = [];

  protected bool $isBoosted = FALSE;

  public function getFormId(): string {
    return 'myeventlane_boost_purchase_form';
  }

  /**
   * @param \Drupal\node\NodeInterface|null $event
   * @param array $variations [vid => ['id','label','days','price']]
   * @param bool $is_boosted
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    NodeInterface $event = NULL,
    array $variations = [],
    bool $is_boosted = FALSE
  ): array {
    $this->event = $event;
    $this->isBoosted = (bool) $is_boosted;

    $this->plans = $this->normalizePlans($variations);
    if (!$this->plans) {
      $this->messenger()->addError($this->t('No Boost plans available. Please contact an administrator.'));
      return $form;
    }

    $default_vid = (string) array_key_first($this->plans);
    $chosen = (string) ($form_state->getValue('variation_id') ?? $default_vid);

    $form['event_id'] = ['#type' => 'hidden', '#value' => (int) $this->event->id()];
    $form['is_boosted'] = ['#type' => 'hidden', '#value' => (int) $this->isBoosted];

    $form['card'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['boost-card', 'mel-card']],
    ];
    $form['card']['list'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['boost-list']],
    ];

    foreach ($this->plans as $vid => $info) {
      $row_key = 'row_' . $vid;

      $form['card']['list'][$row_key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['boost-row']],
      ];

      $form['card']['list'][$row_key]['icon'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->iconEmoji((int) $info['days']),
        '#attributes' => ['class' => ['boost-row__icon']],
      ];

      $form['card']['list'][$row_key]['text'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['boost-row__text']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $info['label'],
          '#attributes' => ['class' => ['boost-row__title']],
        ],
        'desc' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->isBoosted ? $this->t('Extends your current boost.') : $this->t('Featured placement + badge.'),
          '#attributes' => ['class' => ['boost-row__desc']],
        ],
      ];

      $form['card']['list'][$row_key]['price'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $info['price'],
        '#attributes' => ['class' => ['boost-row__price']],
      ];

      $form['card']['list'][$row_key]['radio'] = [
        '#type' => 'radio',
        '#title' => $info['label'],
        '#title_display' => 'invisible',
        '#return_value' => (string) $vid,
        '#default_value' => ($chosen === (string) $vid) ? (string) $vid : NULL,
        '#parents' => ['variation_id'],
        '#attributes' => ['class' => ['boost-row__radio']],
      ];
    }

    $form['card']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['boost-footer']],
    ];
    $form['card']['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('entity.node.canonical', ['node' => $this->event->id()]),
      '#attributes' => ['class' => ['button', 'button--ghost']],
    ];
    $form['card']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->isBoosted ? $this->t('Extend Boost') : $this->t('Boost Event'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('variation_id')) {
      $form_state->setErrorByName('variation_id', $this->t('Please choose a plan.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $vid = (int) $form_state->getValue('variation_id');
    $nid = (int) $form_state->getValue('event_id');
    $uid = (int) $this->currentUser()->id();

    \Drupal::logger('boost_debug')->notice('Selected variation @vid for event @nid by user @uid', [
      '@vid' => $vid, '@nid' => $nid, '@uid' => $uid,
    ]);

    $variation = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($vid);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    if (!$variation || !$node) {
      \Drupal::logger('boost_debug')->error('Invalid submit: variation or node missing. vid=@vid nid=@nid', ['@vid' => $vid, '@nid' => $nid]);
      $this->messenger()->addError($this->t('Invalid selection.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    // Resolve store.
    $product = $variation->getProduct();
    $stores = method_exists($product, 'getStores') ? $product->getStores() : $product->get('stores')->referencedEntities();
    if (!$stores) {
      $this->messenger()->addError($this->t('This Boost product is not assigned to any store.'));
      $form_state->setRedirect('entity.node.canonical', ['node' => $nid]);
      return;
    }
    $store = reset($stores);

    // Cart services.
    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
    $cart_manager  = \Drupal::service('commerce_cart.cart_manager');
    $account = $this->currentUser();

    $cart = $cart_provider->getCart('default', $store, $account) ?: $cart_provider->createCart('default', $store, $account);

    // Order item.
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $order_item_storage->createFromPurchasableEntity($variation);

    $oit_exists = (bool) \Drupal::entityTypeManager()->getStorage('commerce_order_item_type')->load('boost');
    if ($oit_exists && $order_item->bundle() !== 'boost') {
      $order_item->set('type', 'boost');
    }

    $order_item->setQuantity(1);
    if ($order_item->hasField('field_target_event')) {
      $order_item->set('field_target_event', ['target_id' => $nid]);
    }
    $order_item->save();

    $cart_manager->addOrderItem($cart, $order_item);

    $checkout_url = Url::fromRoute('commerce_checkout.form', ['commerce_order' => $cart->id()]);
    \Drupal::logger('boost_debug')->notice('Redirecting to checkout order id: @oid, url: @url', [
      '@oid' => $cart->id(),
      '@url' => $checkout_url->toString(),
    ]);
    $form_state->setRedirectUrl($checkout_url);
  }

  /**
   * Build plan list from provided variations, discovery, or legacy SKUs.
   *
   * @param array $variations
   * @return array<int,array{id:int,label:string,days:int,price:string}>
   */
  private function normalizePlans(array $variations): array {
    $plans = [];

    // Case 1: use provided variations from the controller.
    if ($variations) {
      foreach ($variations as $vid => $info) {
        $plans[(int) $vid] = [
          'id' => (int) $vid,
          'label' => (string) ($info['label'] ?? 'Boost Plan'),
          'days' => (int) ($info['days'] ?? 7),
          'price' => (string) ($info['price'] ?? ''),
        ];
      }
      return $plans;
    }

    $etm = \Drupal::entityTypeManager();
    $cf = \Drupal::service('commerce_price.currency_formatter');

    // Case 2: discover published boost_duration variations.
    $vids = \Drupal::entityQuery('commerce_product_variation')
      ->accessCheck(FALSE)
      ->condition('type', 'boost_duration')
      ->condition('status', 1)
      ->execute();

    if ($vids) {
      $vars = $etm->getStorage('commerce_product_variation')->loadMultiple($vids);
      foreach ($vars as $v) {
        $days = (int) ($v->get('field_boost_days')->value ?? 0) ?: 7;
        $price_text = '';
        if ($price = $v->getPrice()) {
          $price_text = $cf->format(
            $price->getNumber(),
            $price->getCurrencyCode(),
            [
              'currency_display' => 'symbol',
              'minimum_fraction_digits' => 2,
              'maximum_fraction_digits' => 2,
              'locale' => 'en-AU',
            ]
          );
        }
        $plans[(int) $v->id()] = [
          'id' => (int) $v->id(),
          'label' => $v->label() ?: $this->t('Boost @d Days', ['@d' => $days]),
          'days' => $days,
          'price' => $price_text,
        ];
      }
      if ($plans) {
        return $plans;
      }
    }

    // Case 3: legacy SKU fallback (boost_7d etc.).
    $all = $etm->getStorage('commerce_product_variation')->loadMultiple();
    foreach ($all as $v) {
      $sku = (string) $v->getSku();
      if (preg_match('/^boost_(\d+)d$/i', $sku, $m)) {
        $days = (int) $m[1];
        $price_text = '';
        if ($price = $v->getPrice()) {
          $price_text = $cf->format(
            $price->getNumber(),
            $price->getCurrencyCode(),
            [
              'currency_display' => 'symbol',
              'minimum_fraction_digits' => 2,
              'maximum_fraction_digits' => 2,
              'locale' => 'en-AU',
            ]
          );
        }
        $plans[(int) $v->id()] = [
          'id' => (int) $v->id(),
          'label' => $this->t('Boost @d Days', ['@d' => $days]),
          'days' => $days,
          'price' => $price_text,
        ];
      }
    }

    return $plans;
  }

  private function iconEmoji(int $days): string {
    if ($days >= 30) { return '❤️'; }
    if ($days >= 14) { return '📣'; }
    return '📊';
  }

}
