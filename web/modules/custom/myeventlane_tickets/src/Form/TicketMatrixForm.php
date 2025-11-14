<?php

namespace Drupal\myeventlane_tickets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

class TicketMatrixForm extends FormBase {

  public function getFormId(): string {
    return 'myeventlane_ticket_matrix_form';
  }

  /**
   * Supports one ProductInterface or an array of ProductInterface.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $products = NULL): array {
    // Normalize to array of ProductInterface.
    if ($products instanceof ProductInterface) {
      $products = [$products];
    }
    if (!is_array($products)) {
      $products = [];
    }
    $products = array_values(array_filter($products, fn($p) => $p instanceof ProductInterface));

    if (!$products) {
      $form['#markup'] = $this->t('Tickets are not available yet.');
      return $form;
    }

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'myeventlane_tickets/ticket_matrix';
    $form['#attached']['drupalSettings']['myeventlane']['initialSubtotal'] = '0.00';

    /** @var \Drupal\commerce_price\CurrencyFormatterInterface $fmt */
    $fmt = \Drupal::service('commerce_price.currency_formatter');

    $form['variations'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-ticket-matrix', 'mel-matrix-active']],
    ];

    foreach ($products as $product) {
      $pid = (string) $product->id();

      $form['variations']["product_$pid"] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $product->label() ?: $this->t('Tickets'),
        '#attributes' => ['class' => ['mel-ticket-matrix-card']],
      ];

      $rows =& $form['variations']["product_$pid"]['rows'];
      $rows = [
        '#type' => 'container',
        '#attributes' => ['class' => ['mel-ticket-rows']],
      ];

      foreach ($product->getVariations() as $variation) {
        if (!$variation instanceof ProductVariationInterface) {
          continue;
        }
        if (method_exists($variation, 'isPublished') && !$variation->isPublished()) {
          continue;
        }

        $vid = (string) $variation->id();
        $price = $variation->getPrice();
        $price_text = $price
          ? $fmt->format($price->getNumber(), $price->getCurrencyCode(), ['currency_display' => 'symbol'])
          : $this->t('Free');

        $v_label = trim((string) $variation->label());
        if ($v_label === '') {
          $v_label = $this->t('@product — Ticket', ['@product' => $product->label() ?: $this->t('Ticket')]);
        }

       $stock_level = NULL;
       $stock_note = '';
        if ($variation->hasField('stock') && !$variation->get('stock')->isEmpty()) {
          $stock_level = (int) $variation->get('stock')->value;
          $stock_note = $stock_level > 0
            ? $this->t('Only @n left', ['@n' => $stock_level])
            : $this->t('Sold Out');
        }

        // Show "Sold out" if stock is 0
        if ($stock_level === 0) {
          $rows["row_$vid"] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['mel-ticket-row', 'mel-sold-out']],
            'label' => [
              '#type' => 'item',
              '#markup' => $this->t('@title — @price <span class="mel-stock-note">@note</span>', [
                '@title' => $v_label,
                '@price' => $price_text,
                '@note' => $stock_note,
              ]),
              '#wrapper_attributes' => ['class' => ['mel-ticket-label']],
            ],
          ];
          continue;
        }

        // Otherwise render active ticket row
        $rows["row_$vid"] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['mel-ticket-row']],
        ];
        $rows["row_$vid"]['label'] = [
          '#type' => 'item',
          '#markup' => $this->t('@title — @price', [
            '@title' => $v_label,
            '@price' => $price_text,
          ]),
          '#wrapper_attributes' => ['class' => ['mel-ticket-label']],
        ];
        $rows["row_$vid"]['qty'] = [
          '#type' => 'number',
          '#title' => $this->t('Quantity'),
          '#title_display' => 'invisible',
          '#min' => 0,
          '#max' => $stock_level,
          '#default_value' => 0,
          '#attributes' => [
            'class' => ['mel-qty-input'],
            'data-vid' => $vid,
            'data-stock' => $stock_level,
          ],
        ];
        $rows["row_$vid"]['inc'] = [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => '+',
          '#attributes' => [
            'type' => 'button',
            'class' => ['mel-stepper-btn'],
            'data-mel-step' => '+',
          ],
        ];
        $rows["row_$vid"]['dec'] = [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => '−',
          '#attributes' => [
            'type' => 'button',
            'class' => ['mel-stepper-btn'],
            'data-mel-step' => '-',
          ],
        ];
      }
    }

    $form['error_msg'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-error', 'visually-hidden']],
      'text' => ['#markup' => $this->t('Please choose at least one ticket.')],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to cart'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['mel-ticket-sticky-cta']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('variations');
    $total = 0;

    foreach ($values as $product_key => $product_group) {
      if (empty($product_group['rows']) || !is_array($product_group['rows'])) {
        continue;
      }

      foreach ($product_group['rows'] as $row_key => $row) {
        if (!isset($row['qty']) || !is_numeric($row['qty'])) {
          continue;
        }

        $qty = (int) $row['qty'];
        $total += max(0, $qty);

        // Enforce non-negative
        if ($qty < 0) {
          $form_state->setErrorByName("variations][$product_key][$row_key][qty", $this->t('Quantity cannot be negative.'));
        }

        // Enforce stock limits
        $vid = substr((string) $row_key, 4);
        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation */
        $variation = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($vid);
        if ($variation && $variation->hasField('stock') && !$variation->get('stock')->isEmpty()) {
          $stock_level = (int) $variation->get('stock')->value;
          if ($qty > $stock_level) {
            $form_state->setErrorByName("variations][$product_key][$row_key][qty", $this->t('Only @n ticket(s) left.', ['@n' => $stock_level]));
          }
        }
      }
    }

    if ($total === 0) {
      $form_state->setErrorByName('variations', $this->t('Please choose at least one ticket.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue('variations');

    /** @var \Drupal\commerce_cart\CartManagerInterface $cart_manager */
    $cart_manager = \Drupal::service('commerce_cart.cart_manager');
    /** @var \Drupal\commerce_cart\CartProviderInterface $cart_provider */
    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');

    $added = 0;

    foreach ($values as $product_group) {
      if (empty($product_group['rows']) || !is_array($product_group['rows'])) {
        continue;
      }

      foreach ($product_group['rows'] as $row_key => $row) {
        if (!str_starts_with((string) $row_key, 'row_')) {
          continue;
        }
        $vid = substr((string) $row_key, 4);
        $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
        if ($qty <= 0) {
          continue;
        }

        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation */
        $variation = $storage->load($vid);
        if (!$variation) {
          continue;
        }

        $product = $variation->getProduct();
        $store = $product ? ($product->getStores()[0] ?? NULL) : NULL;
        if (!$store) {
          continue;
        }

        $cart = $cart_provider->getCart('default', $store) ?? $cart_provider->createCart('default', $store);
        $cart_manager->addEntity($cart, $variation, $qty);
        $added += $qty;
      }
    }

    if ($added > 0) {
      $this->messenger()->addStatus($this->t('Added @n ticket(s) to your cart.', ['@n' => $added]));
    } else {
      $this->messenger()->addWarning($this->t('Nothing was added.'));
    }
  }
}