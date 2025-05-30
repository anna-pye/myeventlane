<?php

namespace Drupal\Tests\commerce_promotion\Kernel\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderItemPercentageOff;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Tests the Promotion entity.
 *
 * @coversDefaultClass \Drupal\commerce_promotion\Entity\Promotion
 *
 * @group commerce
 */
class PromotionTest extends OrderKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);
    $this->installConfig(['commerce_promotion']);
  }

  /**
   * @covers ::getName
   * @covers ::setName
   * @covers ::getDisplayName
   * @covers ::setDisplayName
   * @covers ::getDescription
   * @covers ::setDescription
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getOrderTypes
   * @covers ::setOrderTypes
   * @covers ::getOrderTypeIds
   * @covers ::setOrderTypeIds
   * @covers ::getStores
   * @covers ::setStores
   * @covers ::setStoreIds
   * @covers ::getStoreIds
   * @covers ::getOffer
   * @covers ::setOffer
   * @covers ::getConditionOperator
   * @covers ::setConditionOperator
   * @covers ::getCouponIds
   * @covers ::getCoupons
   * @covers ::setCoupons
   * @covers ::hasCoupons
   * @covers ::addCoupon
   * @covers ::removeCoupon
   * @covers ::hasCoupon
   * @covers ::getUsageLimit
   * @covers ::setUsageLimit
   * @covers ::getUsageLimit
   * @covers ::setUsageLimit
   * @covers ::getStartDate
   * @covers ::setStartDate
   * @covers ::getEndDate
   * @covers ::setEndDate
   * @covers ::isEnabled
   * @covers ::setEnabled
   * @covers ::getOwner
   * @covers ::setOwner
   * @covers ::getOwnerId
   * @covers ::setOwnerId
   * @covers ::requiresCoupon
   */
  public function testPromotion() {
    $order_type = OrderType::load('default');
    $promotion = Promotion::create([
      'status' => FALSE,
    ]);

    $promotion->setName('My Promotion');
    $this->assertEquals('My Promotion', $promotion->getName());

    $promotion->setDisplayName('50% off');
    $this->assertEquals('50% off', $promotion->getDisplayName());

    $promotion->setDescription('My Promotion Description');
    $this->assertEquals('My Promotion Description', $promotion->getDescription());

    $promotion->setCreatedTime(635879700);
    $this->assertEquals(635879700, $promotion->getCreatedTime());

    $promotion->setOrderTypes([$order_type]);
    $order_types = $promotion->getOrderTypes();
    $this->assertEquals($order_type->id(), $order_types[0]->id());

    $promotion->setOrderTypeIds([$order_type->id()]);
    $this->assertEquals([$order_type->id()], $promotion->getOrderTypeIds());

    $promotion->setStores([$this->store]);
    $this->assertEquals([$this->store], $promotion->getStores());

    $promotion->setStoreIds([$this->store->id()]);
    $this->assertEquals([$this->store->id()], $promotion->getStoreIds());

    $offer = new OrderItemPercentageOff(['percentage' => '0.5'], 'order_percentage_off', []);
    $promotion->setOffer($offer);
    $this->assertEquals($offer->getPluginId(), $promotion->getOffer()->getPluginId());
    $this->assertEquals($offer->getConfiguration(), $promotion->getOffer()->getConfiguration());

    $this->assertEquals('AND', $promotion->getConditionOperator());
    $promotion->setConditionOperator('OR');
    $this->assertEquals('OR', $promotion->getConditionOperator());

    $coupon1 = Coupon::create([
      'code' => $this->randomMachineName(),
      'status' => TRUE,
    ]);
    $coupon1->save();
    $coupon2 = Coupon::create([
      'code' => $this->randomMachineName(),
      'status' => TRUE,
    ]);
    $coupon2->save();
    $coupon1 = Coupon::load($coupon1->id());
    $coupon2 = Coupon::load($coupon2->id());
    $coupons = [$coupon1, $coupon2];
    $coupon_ids = [$coupon1->id(), $coupon2->id()];

    $this->assertFalse($promotion->hasCoupons());
    $promotion->setCoupons($coupons);
    $this->assertTrue($promotion->hasCoupons());
    $this->assertEquals($coupons, $promotion->getCoupons());
    $this->assertEquals($coupon_ids, $promotion->getCouponIds());
    $this->assertTrue($promotion->hasCoupon($coupon1));
    $promotion->removeCoupon($coupon1);
    $this->assertFalse($promotion->hasCoupon($coupon1));
    $promotion->addCoupon($coupon1);
    $this->assertTrue($promotion->hasCoupon($coupon1));

    // Check Coupon::postDelete() remove Coupon reference from promotion.
    $promotion->save();
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = $this->reloadEntity($promotion);
    $this->assertEquals($promotion->id(), 1);
    $coupon1->delete();
    $this->assertFalse($promotion->hasCoupon($coupon1));

    $promotion->setUsageLimit(10);
    $this->assertEquals(10, $promotion->getUsageLimit());

    $promotion->setCustomerUsageLimit(2);
    $this->assertEquals(2, $promotion->getCustomerUsageLimit());

    $date_pattern = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $time = $this->container->get('datetime.time');
    $default_start_date = date($date_pattern, $time->getRequestTime());
    $this->assertEquals($default_start_date, $promotion->getStartDate()->format($date_pattern));
    $promotion->setStartDate(new DrupalDateTime('2017-01-01 12:12:12'));
    $this->assertEquals('2017-01-01 12:12:12 UTC', $promotion->getStartDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-01 12:12:12 CET', $promotion->getStartDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $this->assertNull($promotion->getEndDate());
    $promotion->setEndDate(new DrupalDateTime('2017-01-31 17:15:00'));
    $this->assertEquals('2017-01-31 17:15:00 UTC', $promotion->getEndDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-31 17:15:00 CET', $promotion->getEndDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $promotion->setEnabled(TRUE);
    $this->assertEquals(TRUE, $promotion->isEnabled());

    $promotion->setOwnerId(900);
    $this->assertTrue($promotion->getOwner()->isAnonymous());
    $this->assertEquals(900, $promotion->getOwnerId());
    $promotion->save();
    $this->assertEquals(0, $promotion->getOwnerId());

    $promotion = Promotion::create([
      'status' => FALSE,
    ]);
    $this->assertFalse($promotion->requiresCoupon());
    $promotion->set('require_coupon', TRUE);
    $this->assertTrue($promotion->requiresCoupon());
    $promotion->set('require_coupon', FALSE);
    $this->assertFalse($promotion->requiresCoupon());
    // As soon as a promotion requires a coupon, applying a coupon is required
    // in order for the promotion to apply.
    $promotion->addCoupon($coupon1);
    $promotion->save();
    $this->assertTrue($promotion->requiresCoupon());
  }

  /**
   * @covers ::createDuplicate
   */
  public function testDuplicate() {
    $coupon = Coupon::create([
      'code' => $this->randomMachineName(),
      'status' => TRUE,
    ]);
    $coupon->save();
    $promotion = Promotion::create([
      'name' => '10% off',
      'coupons' => [$coupon],
      'status' => FALSE,
    ]);
    $promotion->save();
    $this->assertNotEmpty($promotion->getCouponIds());

    $duplicate_promotion = $promotion->createDuplicate();
    $this->assertEquals('10% off', $duplicate_promotion->label());
    $this->assertFalse($duplicate_promotion->hasCoupons());
  }

  /**
   * @covers ::isMultipleCouponsAllowed
   */
  public function testAllowMultipleCouponsField() {
    // Create a promotion with allow_multiple_coupons enabled.
    $promotion = Promotion::create([
      'name' => 'Test Promotion',
      'start_date' => '2025-02-17T00:00:00',
      'end_date' => '2025-03-17T00:00:00',
      'allow_multiple_coupons' => TRUE,
    ]);
    $this->assertTrue($promotion->isMultipleCouponsAllowed(), 'The allow_multiple_coupons field is correctly set to TRUE.');
    $promotion->set('allow_multiple_coupons', FALSE);
    $this->assertFalse($promotion->isMultipleCouponsAllowed());

    // Create a promotion without explicitly setting allow_multiple_coupons.
    $promotion = Promotion::create([
      'name' => 'Default Promotion',
    ]);
    $this->assertFalse($promotion->isMultipleCouponsAllowed(), 'The default value for allow_multiple_coupons is FALSE.');
  }

}
