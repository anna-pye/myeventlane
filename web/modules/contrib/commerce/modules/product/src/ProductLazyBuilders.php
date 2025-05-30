<?php

namespace Drupal\commerce_product;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides #lazy_builder callbacks.
 */
class ProductLazyBuilders implements TrustedCallbackInterface {

  /**
   * Constructs a new ProductLazyBuilders object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FormBuilderInterface $formBuilder,
    protected EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * Builds the add to cart form.
   *
   * @param string $product_id
   *   The product ID.
   * @param string $view_mode
   *   The view mode used to render the product.
   * @param bool $combine
   *   TRUE to combine order items containing the same product variation.
   * @param string $langcode
   *   The langcode for the language that should be used in form.
   *
   * @return array
   *   A renderable array containing the cart form.
   */
  public function addToCartForm($product_id, $view_mode, $combine, $langcode) {
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $this->entityTypeManager->getStorage('commerce_product')->load($product_id);
    // Load Product for current language.
    $product = $this->entityRepository->getTranslationFromContext($product, $langcode);

    $default_variation = $product->getDefaultVariation();
    if (!$default_variation) {
      return [];
    }

    $order_item = $order_item_storage->createFromPurchasableEntity($default_variation);
    /** @var \Drupal\commerce_cart\Form\AddToCartFormInterface $form_object */
    $form_object = $this->entityTypeManager->getFormObject('commerce_order_item', 'add_to_cart');
    $form_object->setEntity($order_item);
    // The default form ID is based on the variation ID, but in this case the
    // product ID is more reliable (the default variation might change between
    // requests due to an availability change, for example).
    $form_object->setFormId($form_object->getBaseFormId() . '_commerce_product_' . $product_id);
    $form_state = (new FormState())->setFormState([
      'product' => $product,
      'view_mode' => $view_mode,
      'settings' => [
        'combine' => $combine,
      ],
    ]);

    return $this->formBuilder->buildForm($form_object, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['addToCartForm'];
  }

}
