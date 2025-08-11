<?php

namespace Drupal\myeventlane_boost\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Url;

class BoostPurchaseForm extends FormBase {

  protected $event;

  public function getFormId() {
    return 'boost_purchase_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $event = NULL) {
    $this->event = $event;

    // Pricing logic
    $options = [
      1 => $this->t('1 day ($5)'),
      3 => $this->t('3 days ($15)'),
      5 => $this->t('5 days ($25)'),
    ];

    $form['duration'] = [
      '#type' => 'select',
      '#title' => $this->t('Promotion Duration'),
      '#options' => $options,
      '#default_value' => 1,
      '#required' => TRUE,
    ];

    $form['event_id'] = [
      '#type' => 'hidden',
      '#value' => $this->event->id(),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purchase Boost'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $duration = $form_state->getValue('duration');
    $event_id = $form_state->getValue('event_id');
    $uid = $this->currentUser()->id();

    // Debug
    \Drupal::logger('boost_debug')->notice('duration: @duration, event_id: @event_id', [
      '@duration' => $duration, '@event_id' => $event_id,
    ]);

    // Find variation by SKU
    $variations = \Drupal\commerce_product\Entity\ProductVariation::loadMultiple();
    $variation = NULL;
    foreach ($variations as $v) {
      if ($v->getSku() === 'boost_' . $duration . 'd') {
        $variation = $v;
        break;
      }
    }
    if (!$variation) {
      \Drupal::logger('boost_debug')->error('No product variation found for SKU: boost_' . $duration . 'd');
      \Drupal::messenger()->addError($this->t('Boost product for this duration is not configured. Please contact admin.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    // Create an order item.
    $order_item = \Drupal\commerce_order\Entity\OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $variation,
      'quantity' => 1,
    ]);
    $order_item->save();

    // Create an order for this user.
    $order = \Drupal\commerce_order\Entity\Order::create([
      'type' => 'default',
      'state' => 'draft',
      'uid' => $uid,
      'order_items' => [$order_item],
    ]);
    // Optional: only set field_boost_event if this field exists on your order entity.
    if ($order->hasField('field_boost_event')) {
      $order->set('field_boost_event', $event_id);
    }
    $order->save();

    // Only redirect if order was created.
    if ($order && $order->id()) {
      $checkout_url = \Drupal\Core\Url::fromRoute('commerce_checkout.form', ['commerce_order' => $order->id()]);
      \Drupal::logger('boost_debug')->notice('Redirecting to checkout order id: @oid, url: @url', [
        '@oid' => $order->id(),
        '@url' => $checkout_url ? $checkout_url->toString() : 'EMPTY',
      ]);
      $form_state->setRedirectUrl($checkout_url);
    } else {
      \Drupal::logger('boost_debug')->error('Order could not be created');
      \Drupal::messenger()->addError($this->t('Could not create checkout. Please try again.'));
      $form_state->setRebuild(TRUE);
    }
  }

} // <-- this was missing
