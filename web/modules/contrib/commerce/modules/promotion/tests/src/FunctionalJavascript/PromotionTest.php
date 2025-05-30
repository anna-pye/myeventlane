<?php

namespace Drupal\Tests\commerce_promotion\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\CombinationOffer;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the admin UI for promotions.
 *
 * @group commerce
 */
class PromotionTest extends CommerceWebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'path',
    'commerce_product',
    'commerce_promotion',
    'language',
    'locale',
  ];

  /**
   * A test product.
   *
   * @var \Drupal\commerce_product\Entity\Product
   */
  protected $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'price' => [
        'number' => 222,
        'currency_code' => 'USD',
      ],
    ]);
    $this->product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [$variation],
    ]);
    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_promotion',
      'access commerce_promotion overview',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests creating a promotion.
   */
  public function testCreatePromotion() {
    $this->drupalGet('admin/commerce/promotions');
    $this->getSession()->getPage()->clickLink('Add promotion');

    // Check the integrity of the form.
    $this->assertSession()->fieldExists('name[0][value]');
    $this->assertSession()->fieldExists('display_name[0][value]');
    $name = $this->randomMachineName(8);
    $this->getSession()->getPage()->fillField('name[0][value]', $name);
    $this->getSession()->getPage()->fillField('display_name[0][value]', 'Discount');
    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_id]', 'order_item_percentage_off');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_configuration][order_item_percentage_off][percentage]', '10.0');

    // Change, assert any values reset.
    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_id]', 'order_percentage_off');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldValueNotEquals('offer[0][target_plugin_configuration][order_percentage_off][percentage]', '10.0');
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_configuration][order_percentage_off][percentage]', '10.0');

    // Confirm the integrity of the conditions UI.
    foreach (['order', 'products', 'customer'] as $condition_group) {
      $tab_matches = $this->xpath('//a[@href="#edit-conditions-form-' . $condition_group . '"]');
      $this->assertNotEmpty($tab_matches);
    }
    $vertical_tab_elements = $this->xpath('//a[@href="#edit-conditions-form-order"]');
    $vertical_tab_element = reset($vertical_tab_elements);
    $vertical_tab_element->click();
    $this->getSession()->getPage()->checkField('Current order total');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('conditions[form][order][order_total_price][configuration][form][amount][number]', '50.00');

    // Confirm that the usage limit widget works properly.
    $this->assertSession()->fieldExists('usage_limit[0][limit]');
    $this->assertSession()->fieldValueEquals('usage_limit[0][limit]', 0);
    $usage_limit_xpath = '//input[@type="number" and @name="usage_limit[0][usage_limit]"]';
    $this->assertFalse($this->getSession()->getDriver()->isVisible($usage_limit_xpath));
    // Select 'Limited number of uses'.
    $this->getSession()->getPage()->selectFieldOption('usage_limit[0][limit]', '1');
    $this->assertTrue($this->getSession()->getDriver()->isVisible($usage_limit_xpath));
    $this->getSession()->getPage()->fillField('usage_limit[0][usage_limit]', '99');

    // Confirm that the customer usage limit widget works properly.
    $this->assertSession()->fieldExists('usage_limit_customer[0][limit_customer]');
    $this->assertSession()->fieldValueEquals('usage_limit_customer[0][limit_customer]', 0);
    $customer_usage_limit_xpath = '//input[@type="number" and @name="usage_limit_customer[0][usage_limit_customer]"]';
    $this->assertFalse($this->getSession()->getDriver()->isVisible($customer_usage_limit_xpath));
    $this->getSession()->getPage()->selectFieldOption('usage_limit_customer[0][limit_customer]', '1');
    $this->assertTrue($this->getSession()->getDriver()->isVisible($customer_usage_limit_xpath));
    $this->getSession()->getPage()->fillField('usage_limit_customer[0][usage_limit_customer]', '5');

    $this->setRawFieldValue('start_date[0][value][date]', '2019-11-29');
    $this->setRawFieldValue('start_date[0][value][time]', '10:30:00');
    $this->submitForm([], (string) $this->t('Save'));
    $this->assertSession()->pageTextContains("Saved the $name promotion.");
    $rows = $this->getSession()->getPage()->findAll('xpath', "//table/tbody/tr/td[text()[contains(., '$name')]]");
    $this->assertCount(1, $rows);

    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = Promotion::load(1);
    $this->assertNotEmpty($promotion->getCreatedTime());
    $this->assertNotEmpty($promotion->getChangedTime());
    $this->assertEquals($name, $promotion->getName());
    $this->assertEquals('Discount', $promotion->getDisplayName());
    $offer = $promotion->getOffer();
    $this->assertEquals('0.10', $offer->getConfiguration()['percentage']);
    $conditions = $promotion->getConditions();
    $condition = reset($conditions);
    $this->assertEquals('50.00', $condition->getConfiguration()['amount']['number']);
    $this->assertEquals('99', $promotion->getUsageLimit());
    $this->assertEquals('5', $promotion->getCustomerUsageLimit());
    $this->assertEquals('2019-11-29 10:30:00', $promotion->getStartDate()->format('Y-m-d H:i:s'));
    $this->assertNull($promotion->getEndDate());

    $this->drupalGet($promotion->toUrl('edit-form'));

    $this->assertSession()->fieldExists('usage_limit[0][limit]');
    $this->assertSession()->fieldValueEquals('usage_limit[0][limit]', 1);
    $this->assertTrue($this->getSession()->getDriver()->isVisible($usage_limit_xpath));
    $this->assertSession()->fieldExists('usage_limit_customer[0][limit_customer]');
    $this->assertSession()->fieldValueEquals('usage_limit_customer[0][limit_customer]', 1);
    $this->assertTrue($this->getSession()->getDriver()->isVisible($customer_usage_limit_xpath));
  }

  /**
   * Tests creating a promotion on a non English admin.
   */
  public function testCreatePromotionOnTranslatedAdmin() {
    $this->config('system.site')->set('default_langcode', 'fr')->save();
    /** @var \Drupal\locale\StringStorageInterface $storage */
    $storage = $this->container->get('locale.storage');
    // Translate the 'Products' string which is used when building the offer
    // conditions.
    $source_string = $storage->createString(['source' => 'Products'])->save();
    $storage->createTranslation([
      'lid' => $source_string->lid,
      'language' => 'fr',
      'translation' => 'Produits',
    ])->save();
    $this->adminUser->set('preferred_langcode', 'fr')->save();
    $this->drupalGet('admin/commerce/promotions');
    $this->getSession()->getPage()->clickLink('Add promotion');
    $this->assertSession()->fieldExists('name[0][value]');
    $this->assertSession()->fieldExists('display_name[0][value]');
    $name = $this->randomMachineName(8);
    $this->getSession()->getPage()->fillField('name[0][value]', $name);
    $this->getSession()->getPage()->fillField('display_name[0][value]', 'Discount');
    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_id]', 'order_buy_x_get_y');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_configuration][order_buy_x_get_y][offer][percentage]', '100');
    $this->getSession()->getPage()->checkField('offer[0][target_plugin_configuration][order_buy_x_get_y][get][conditions][products][order_item_product][enable]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_configuration][order_buy_x_get_y][get][conditions][products][order_item_product][configuration][form][products]', $this->product->label() . ' (' . $this->product->id() . ')');
    $this->getSession()->getPage()->checkField('offer[0][target_plugin_configuration][order_buy_x_get_y][get][auto_add]');
    $this->submitForm([], (string) $this->t('Save'));

    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = Promotion::load(1);
    $this->assertNotEmpty($promotion->getCreatedTime());
    $this->assertNotEmpty($promotion->getChangedTime());
    $this->assertEquals($name, $promotion->getName());
    $this->assertEquals('Discount', $promotion->getDisplayName());
    $offer_configuration = $promotion->getOffer()->getConfiguration();
    $this->assertEquals('percentage', $offer_configuration['offer_type']);
    $this->assertEquals('1', $offer_configuration['offer_percentage']);
    $this->assertEquals('1', $offer_configuration['buy_quantity']);
    $this->assertNotEmpty($offer_configuration['get_auto_add']);
    $this->assertEquals([
      [
        'plugin' => 'order_item_product',
        'configuration' => [
          'products' => [
            [
              'product' => $this->product->uuid(),
            ],
          ],
        ],
      ],
    ], $offer_configuration['get_conditions']);
  }

  /**
   * Tests creating a promotion using the "Save and add coupons" button.
   */
  public function testCreatePromotionWithSaveAndAddCoupons() {
    $this->drupalGet('admin/commerce/promotions');
    $this->getSession()->getPage()->clickLink('Add promotion');

    $name = $this->randomString();
    $this->getSession()->getPage()->fillField('name[0][value]', $name);
    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_id]', 'order_item_fixed_amount_off');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_configuration][order_item_fixed_amount_off][amount][number]', '10.00');
    $this->submitForm([], (string) $this->t('Save and add coupons'));
    $this->assertSession()->pageTextContains("Saved the $name promotion.");

    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = Promotion::load(1);
    $offer = $promotion->getOffer();
    $this->assertEquals('order_item_fixed_amount_off', $offer->getPluginId());
    $this->assertEquals('10.00', $offer->getConfiguration()['amount']['number']);
  }

  /**
   * Tests creating a promotion with an end date.
   */
  public function testCreatePromotionWithEndDate() {
    $this->drupalGet('admin/commerce/promotions');
    $this->getSession()->getPage()->clickLink('Add promotion');
    $this->drupalGet('promotion/add');

    $this->assertSession()->fieldExists('name[0][value]');
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_id]', 'order_percentage_off');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $end_date = new DrupalDateTime('now', 'UTC');
    $end_date = $end_date->modify('+1 month');
    $name = $this->randomMachineName(8);
    $this->getSession()->getPage()->checkField('end_date[0][has_value]');
    $this->setRawFieldValue('end_date[0][container][value][date]', $end_date->format('Y-m-d'));
    $this->setRawFieldValue('end_date[0][container][value][time]', $end_date->format('H:i:s'));
    $edit = [
      'name[0][value]' => $name,
      'offer[0][target_plugin_configuration][order_percentage_off][percentage]' => '10.0',
    ];
    $this->submitForm($edit, (string) $this->t('Save'));
    $this->assertSession()->pageTextContains("Saved the $name promotion.");

    $rows = $this->getSession()->getPage()->findAll('xpath', "//table/tbody/tr/td[text()[contains(., '$name')]]");
    $this->assertCount(1, $rows);
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = Promotion::load(1);
    $offer = $promotion->getOffer();
    $this->assertEquals('0.10', $offer->getConfiguration()['percentage']);
    $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $this->assertEquals($end_date->format($storage_format), $promotion->getEndDate()->format($storage_format));
  }

  /**
   * Tests updating the offer type when creating a promotion.
   */
  public function testCreatePromotionOfferTypeSelection() {
    $this->drupalGet('admin/commerce/promotions');
    $this->clickLink('Add promotion');

    $offer_config_xpath = '//div[@data-drupal-selector="edit-offer-0-target-plugin-configuration"]';
    $offer_config_container = $this->xpath($offer_config_xpath);
    $this->assertEmpty($offer_config_container);

    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_id]', 'order_item_percentage_off');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $offer_config_container = $this->xpath($offer_config_xpath);
    $this->assertNotEmpty($offer_config_container);

    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_id]', '');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $offer_config_container = $this->xpath($offer_config_xpath);
    $this->assertEmpty($offer_config_container);
  }

  /**
   * Tests editing a promotion.
   */
  public function testEditPromotion() {
    $promotion = $this->createEntity('commerce_promotion', [
      'name' => '10% off',
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.10',
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'order_total_price',
          'target_plugin_configuration' => [
            'amount' => [
              'number' => '9.10',
              'currency_code' => 'USD',
            ],
          ],
        ],
      ],
      'start_date' => '2019-10-07T13:37:00',
    ]);

    /** @var \Drupal\commerce\Plugin\Field\FieldType\PluginItem $offer_field */
    $offer_field = $promotion->get('offer')->first();
    $this->assertEquals('0.10', $offer_field->target_plugin_configuration['percentage']);

    $this->drupalGet($promotion->toUrl('edit-form'));
    $this->assertSession()->checkboxChecked('Current order total');
    $this->assertSession()->fieldValueEquals('conditions[form][order][order_total_price][configuration][form][amount][number]', '9.10');

    $this->setRawFieldValue('start_date[0][value][time]', '14:15:13');
    $edit = [
      'name[0][value]' => '20% off',
      'offer[0][target_plugin_configuration][order_item_percentage_off][percentage]' => '20',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the 20% off promotion.');

    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = $this->reloadEntity($promotion);
    $this->assertEquals('20% off', $promotion->getName());
    $offer = $promotion->getOffer();
    $this->assertEquals('0.20', $offer->getConfiguration()['percentage']);
    $this->assertEquals('2019-10-07 14:15:13', $promotion->getStartDate()->format('Y-m-d H:i:s'));
  }

  /**
   * Tests duplicating a promotion.
   */
  public function testDuplicatePromotion() {
    $promotion = $this->createEntity('commerce_promotion', [
      'name' => '10% off',
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.10',
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'order_total_price',
          'target_plugin_configuration' => [
            'amount' => [
              'number' => '9.10',
              'currency_code' => 'USD',
            ],
          ],
        ],
      ],
    ]);

    $this->drupalGet($promotion->toUrl('duplicate-form'));
    // Check the integrity of the form.
    $this->assertSession()->fieldValueEquals('name[0][value]', '10% off');
    $this->assertSession()->fieldValueEquals('offer[0][target_plugin_id]', 'order_item_percentage_off');
    $this->assertSession()->fieldValueEquals('offer[0][target_plugin_configuration][order_item_percentage_off][percentage]', '10');
    $this->assertSession()->checkboxChecked('Current order total');
    $this->assertSession()->fieldValueEquals('conditions[form][order][order_total_price][configuration][form][amount][number]', '9.10');

    $edit = [
      'name[0][value]' => '20% off',
      'offer[0][target_plugin_configuration][order_item_percentage_off][percentage]' => '20',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the 20% off promotion.');

    // Confirm that the original promotion is unchanged.
    $promotion = $this->reloadEntity($promotion);
    $this->assertNotEmpty($promotion);
    $this->assertEquals('10% off', $promotion->label());

    // Confirm that the new promotion has the expected data.
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = Promotion::load($promotion->id() + 1);
    $this->assertNotEmpty($promotion);
    $this->assertEquals('20% off', $promotion->label());
    $offer = $promotion->getOffer();
    $this->assertEquals('0.20', $offer->getConfiguration()['percentage']);
  }

  /**
   * Tests deleting a promotion.
   */
  public function testDeletePromotion() {
    $promotion = $this->createEntity('commerce_promotion', [
      'name' => $this->randomMachineName(8),
    ]);
    $this->drupalGet($promotion->toUrl('delete-form'));
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->submitForm([], (string) $this->t('Delete'));

    $this->container->get('entity_type.manager')->getStorage('commerce_promotion')->resetCache([$promotion->id()]);
    $promotion_exists = (bool) Promotion::load($promotion->id());
    $this->assertEmpty($promotion_exists);
  }

  /**
   * Tests disabling a promotion.
   */
  public function testDisablePromotion() {
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = $this->createEntity('commerce_promotion', [
      'name' => $this->randomMachineName(8),
    ]);
    $this->assertTrue($promotion->isEnabled());
    $this->drupalGet($promotion->toUrl('disable-form'));
    $this->assertSession()->pageTextContains($this->t('Are you sure you want to disable the promotion @label?', ['@label' => $promotion->label()]));
    $this->submitForm([], 'Disable');

    $promotion = $this->reloadEntity($promotion);
    $this->assertFalse($promotion->isEnabled());
  }

  /**
   * Tests enabling a promotion.
   */
  public function testEnablePromotion() {
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = $this->createEntity('commerce_promotion', [
      'name' => $this->randomMachineName(8),
      'status' => FALSE,
    ]);
    $this->assertFalse($promotion->isEnabled());
    $this->drupalGet($promotion->toUrl('enable-form'));
    $this->assertSession()->pageTextContains($this->t('Are you sure you want to enable the promotion @label?', ['@label' => $promotion->label()]));
    $this->submitForm([], 'Enable');

    $promotion = $this->reloadEntity($promotion);
    $this->assertTrue($promotion->isEnabled());
  }

  /**
   * Tests creating a combination offer promotion.
   */
  public function testCombinationOffer() {
    $this->drupalGet('admin/commerce/promotions');
    $this->getSession()->getPage()->clickLink('Add promotion');

    // Check the integrity of the form.
    $this->assertSession()->fieldExists('name[0][value]');
    $this->assertSession()->fieldExists('display_name[0][value]');
    $name = $this->randomMachineName(8);
    $this->getSession()->getPage()->fillField('name[0][value]', $name);
    $this->getSession()->getPage()->fillField('display_name[0][value]', 'Discount');
    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_id]', 'combination_offer');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_configuration][combination_offer][offers][0][target_plugin_id]', 'order_item_percentage_off');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->buttonNotExists('remove_offer0');
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_configuration][combination_offer][offers][0][target_plugin_configuration][order_item_percentage_off][percentage]', '10');
    $this->getSession()->getPage()->pressButton('Add another offer');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->buttonExists('remove_offer1');
    $this->getSession()->getPage()->selectFieldOption('offer[0][target_plugin_configuration][combination_offer][offers][1][target_plugin_id]', 'order_percentage_off');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('offer[0][target_plugin_configuration][combination_offer][offers][1][target_plugin_configuration][order_percentage_off][percentage]', '10');
    $this->getSession()->getPage()->pressButton('Add another offer');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('offer[0][target_plugin_configuration][combination_offer][offers][2][target_plugin_id]');
    $this->getSession()->getPage()->pressButton('remove_offer2');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists('offer[0][target_plugin_configuration][combination_offer][offers][2][target_plugin_id]');
    $this->submitForm([], (string) $this->t('Save'));
    $this->assertSession()->pageTextContains("Saved the $name promotion.");

    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = Promotion::load(1);
    $this->assertEquals($name, $promotion->getName());
    $this->assertEquals('Discount', $promotion->getDisplayName());
    $offer = $promotion->getOffer();
    $this->assertInstanceOf(CombinationOffer::class, $offer);
    $configuration = $offer->getConfiguration();
    $this->assertCount(2, $configuration['offers']);
    $this->assertEquals('order_item_percentage_off', $configuration['offers'][0]['target_plugin_id']);
    $this->assertEquals([
      'display_inclusive' => TRUE,
      'percentage' => '0.1',
      'conditions' => [],
      'operator' => 'OR',
    ], $configuration['offers'][0]['target_plugin_configuration']);
    $this->assertEquals('order_percentage_off', $configuration['offers'][1]['target_plugin_id']);
    $this->assertEquals(['percentage' => '0.1'], $configuration['offers'][1]['target_plugin_configuration']);
  }

}
