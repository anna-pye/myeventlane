<?php

namespace Drupal\Tests\commerce_cart\FunctionalJavascript;

use Drupal\commerce_price\Calculator;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\commerce_product\Entity\ProductVariationType;

/**
 * Tests pages with multiple products rendered with add to cart forms.
 *
 * @group commerce
 */
class MultipleCartFormsTest extends CartWebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_cart_test',
    'commerce_cart_big_pipe',
  ];

  /**
   * @var \Drupal\commerce_product\Entity\ProductAttributeValueInterface[]
   */
  protected $colorAttributes = [];

  /**
   * @var \Drupal\commerce_product\Entity\ProductAttributeValueInterface[]
   */
  protected $sizeAttributes = [];

  /**
   * @var \Drupal\commerce_product\Entity\ProductInterface[]
   */
  protected $products = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->maximumMetaRefreshCount = 0;

    // Delete parent test product.
    $this->variation->getProduct()->setUnpublished();
    $this->variation->getProduct()->save();

    /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $order_item_form_display */
    $order_item_form_display = EntityFormDisplay::load('commerce_order_item.default.add_to_cart');
    $order_item_form_display->setComponent('quantity', [
      'type' => 'commerce_quantity',
    ]);
    $order_item_form_display->save();

    $variation_type = ProductVariationType::load('default');
    $color_attributes = $this->createAttributeSet($variation_type, 'color', [
      'red' => 'Red',
      'blue' => 'Blue',
    ]);
    $this->colorAttributes = $color_attributes;
    $size_attributes = $this->createAttributeSet($variation_type, 'size', [
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ]);
    $this->sizeAttributes = $size_attributes;

    $attribute_values_matrix = [
      ['red', 'small'],
      ['red', 'medium'],
      ['red', 'large'],
      ['blue', 'small'],
      ['blue', 'medium'],
      ['blue', 'large'],
    ];

    for ($i = 1; $i < 5; $i++) {
      // Create a product variation.
      $variations = [];
      // Generate variations off of the attributes values matrix.
      foreach ($attribute_values_matrix as $key => $value) {
        $variation = $this->createEntity('commerce_product_variation', [
          'type' => 'default',
          'sku' => $this->randomMachineName(),
          'price' => [
            'number' => Calculator::multiply('3', $i),
            'currency_code' => 'USD',
          ],
          'attribute_color' => $color_attributes[$value[0]],
          'attribute_size' => $size_attributes[$value[1]],
        ]);
        $variations[] = $variation;
      }

      $this->products[] = $this->createEntity('commerce_product', [
        'type' => 'default',
        'title' => $this->randomMachineName(),
        'stores' => [$this->store],
        'variations' => $variations,
      ]);
    }
  }

  /**
   * Tests that the form IDs are unique on load, and AJAX rebuild.
   */
  public function testUniqueAddToCartFormIds() {
    $this->drupalGet('/test-multiple-cart-forms');
    $seen_ids = [];
    /** @var \Behat\Mink\Element\NodeElement[] $forms */
    $forms = $this->getSession()->getPage()->findAll('css', '.commerce-order-item-add-to-cart-form');
    $this->assertCount(4, $forms);
    foreach ($forms as $form) {
      $form_id = $form->find('xpath', '//input[@type="hidden" and @name="form_id"]')->getValue();
      $this->assertFalse(in_array($form_id, $seen_ids));
      $seen_ids[] = $form_id;
    }

    $forms[1]->selectFieldOption('Size', 'Large');
    $this->assertSession()->assertWaitOnAjaxRequest();

    /** @var \Behat\Mink\Element\NodeElement[] $forms */
    $forms = $this->getSession()->getPage()->findAll('css', '.commerce-order-item-add-to-cart-form');
    $this->assertCount(4, $forms);
    $ajax_seen_ids = [];
    foreach ($forms as $form) {
      $form_id = $form->find('xpath', '//input[@type="hidden" and @name="form_id"]')->getValue();
      $this->assertFalse(in_array($form_id, $ajax_seen_ids));
      $ajax_seen_ids[] = $form_id;
    }

    $this->assertEquals($seen_ids, $ajax_seen_ids);

  }

  /**
   * Tests that a page with multiple add to cart forms works properly.
   */
  public function testMultipleRenderedProducts() {
    // View of rendered products, each containing an add to cart form.
    $this->drupalGet('/test-multiple-cart-forms');
    /** @var \Behat\Mink\Element\NodeElement[] $forms */
    $forms = $this->getSession()->getPage()->findAll('css', '.commerce-order-item-add-to-cart-form');

    // Modify a single product's add to cart form.
    $current_form = $forms[2];
    $current_form->fillField('quantity[0][value]', '3');
    $current_form->selectFieldOption('Color', 'Blue');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $current_form->selectFieldOption('Size', 'Medium');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $current_form->selectFieldOption('Color', 'Red');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $current_form->pressButton('Add to cart');

    $this->cart = $this->reloadEntity($this->cart);
    $order_items = $this->cart->getItems();
    $this->assertEquals(3, $order_items[0]->getQuantity());
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    $variation = $order_items[0]->getPurchasedEntity();
    $this->assertEquals($this->sizeAttributes['medium']->id(), $variation->getAttributeValueId('attribute_size'));
    $this->assertEquals($this->colorAttributes['red']->id(), $variation->getAttributeValueId('attribute_color'));

    // Modify one form, but submit another.
    $forms = $this->getSession()->getPage()->findAll('css', '.commerce-order-item-add-to-cart-form');
    $current_form = $forms[0];
    $current_form->fillField('quantity[0][value]', '2');
    // Values already selected, no ajax request expected.
    $current_form->selectFieldOption('Color', 'Red');
    $current_form->selectFieldOption('Size', 'Small');
    $current_form->selectFieldOption('Color', 'Blue');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $forms[1]->selectFieldOption('Size', 'Large');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $forms[1]->pressButton('Add to cart');

    $this->cart = $this->reloadEntity($this->cart);
    $order_items = $this->cart->getItems();
    $this->assertEquals(1, $order_items[1]->getQuantity());
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    $variation = $order_items[1]->getPurchasedEntity();
    $this->assertEquals($this->sizeAttributes['large']->id(), $variation->getAttributeValueId('attribute_size'));
    $this->assertEquals($this->colorAttributes['red']->id(), $variation->getAttributeValueId('attribute_color'));
  }

  /**
   * Tests that a page with multiple add to cart forms works properly.
   */
  public function testMultipleRenderedProductsWithTitleWidget() {
    $order_item_form_display = EntityFormDisplay::load('commerce_order_item.default.add_to_cart');
    $order_item_form_display->setComponent('purchased_entity', [
      'type' => 'commerce_product_variation_title',
    ]);
    $order_item_form_display->save();
    // View of rendered products, each containing an add to cart form.
    $this->drupalGet('/test-multiple-cart-forms');
    /** @var \Behat\Mink\Element\NodeElement[] $forms */
    $forms = $this->getSession()->getPage()->findAll('css', '.commerce-order-item-add-to-cart-form');
    // Modify a single product's add to cart form.
    $current_form = $forms[2];
    $current_form->fillField('quantity[0][value]', '3');
    $current_form->selectFieldOption('purchased_entity[0][variation]', $this->products[2]->getVariations()[1]->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $current_form->pressButton('Add to cart');
    $this->cart = $this->reloadEntity($this->cart);
    $order_items = $this->cart->getItems();
    $this->assertEquals(3, $order_items[0]->getQuantity());
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    $variation = $order_items[0]->getPurchasedEntity();
    $this->assertEquals($this->products[2]->getVariations()[1]->id(), $variation->id());

    // Modify one form, but submit another.
    $current_form = $forms[0];
    $current_form->fillField('quantity[0][value]', '2');
    $current_form->selectFieldOption('purchased_entity[0][variation]', $this->products[0]->getVariations()[2]->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $forms[1]->selectFieldOption('purchased_entity[0][variation]', $this->products[1]->getVariations()[3]->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $forms[1]->pressButton('Add to cart');
    $this->cart = $this->reloadEntity($this->cart);
    $order_items = $this->cart->getItems();
    $this->assertEquals(1, $order_items[1]->getQuantity());
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    $variation = $order_items[1]->getPurchasedEntity();
    $this->assertEquals($this->products[1]->getVariations()[3]->id(), $variation->id());
  }

  /**
   * Tests that a page with multiple add to cart forms works properly.
   */
  public function testMultipleRenderedFields() {
    // View of fields, one of which is the variations field
    // rendered via the "commerce_add_to_cart" formatter.
    $this->drupalGet('/test-multiple-cart-forms-fields');
    /** @var \Behat\Mink\Element\NodeElement[] $forms */
    $forms = $this->getSession()->getPage()->findAll('css', '.commerce-order-item-add-to-cart-form');

    $current_form = $forms[3];
    $current_form->fillField('Quantity', '10');
    $current_form->selectFieldOption('Size', 'Large');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $current_form->selectFieldOption('Color', 'Blue');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $current_form->pressButton('Add to cart');

    $this->cart = $this->reloadEntity($this->cart);
    $order_items = $this->cart->getItems();
    $this->assertEquals(10, $order_items[0]->getQuantity());
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
    $variation = $order_items[0]->getPurchasedEntity();
    $this->assertEquals($this->sizeAttributes['large']->id(), $variation->getAttributeValueId('attribute_size'));
    $this->assertEquals($this->colorAttributes['blue']->id(), $variation->getAttributeValueId('attribute_color'));
  }

}
