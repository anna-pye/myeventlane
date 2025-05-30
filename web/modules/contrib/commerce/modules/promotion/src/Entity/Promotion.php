<?php

namespace Drupal\commerce_promotion\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\commerce\ConditionGroup;
use Drupal\commerce\Entity\CommerceContentEntityBase;
use Drupal\commerce\EntityOwnerTrait;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface;
use Drupal\commerce\Plugin\Commerce\Condition\ParentEntityAwareInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderItemPromotionOfferInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PromotionOfferInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Defines the promotion entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_promotion",
 *   label = @Translation("Promotion", context = "Commerce"),
 *   label_collection = @Translation("Promotions", context = "Commerce"),
 *   label_singular = @Translation("promotion", context = "Commerce"),
 *   label_plural = @Translation("promotions", context = "Commerce"),
 *   label_count = @PluralTranslation(
 *     singular = "@count promotion",
 *     plural = "@count promotions",
 *     context = "Commerce",
 *   ),
 *   handlers = {
 *     "event" = "Drupal\commerce_promotion\Event\PromotionEvent",
 *     "storage" = "Drupal\commerce_promotion\PromotionStorage",
 *     "access" = "Drupal\entity\EntityAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\commerce_promotion\PromotionListBuilder",
 *     "views_data" = "Drupal\commerce_promotion\PromotionViewsData",
 *     "form" = {
 *       "default" = "Drupal\commerce_promotion\Form\PromotionForm",
 *       "add" = "Drupal\commerce_promotion\Form\PromotionForm",
 *       "enable" = "Drupal\commerce_promotion\Form\PromotionEnableForm",
 *       "disable" = "Drupal\commerce_promotion\Form\PromotionDisableForm",
 *       "edit" = "Drupal\commerce_promotion\Form\PromotionForm",
 *       "duplicate" = "Drupal\commerce_promotion\Form\PromotionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\commerce_promotion\PromotionRouteProvider",
 *       "delete-multiple" = "Drupal\entity\Routing\DeleteMultipleRouteProvider",
 *     },
 *     "translation" = "Drupal\commerce_promotion\PromotionTranslationHandler",
 *   },
 *   base_table = "commerce_promotion",
 *   data_table = "commerce_promotion_field_data",
 *   admin_permission = "administer commerce_promotion",
 *   translatable = TRUE,
 *   translation = {
 *     "content_translation" = {
 *       "access_callback" = "content_translation_translate_access"
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "promotion_id",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "status" = "status",
 *   },
 *   links = {
 *     "add-form" = "/promotion/add",
 *     "edit-form" = "/promotion/{commerce_promotion}/edit",
 *     "enable-form" = "/promotion/{commerce_promotion}/enable",
 *     "disable-form" = "/promotion/{commerce_promotion}/disable",
 *     "duplicate-form" = "/promotion/{commerce_promotion}/duplicate",
 *     "delete-form" = "/promotion/{commerce_promotion}/delete",
 *     "delete-multiple-form" = "/admin/commerce/promotions/delete",
 *     "collection" = "/admin/commerce/promotions",
 *     "reorder" = "/admin/commerce/promotions/reorder",
 *     "drupal:content-translation-overview" = "/promotion/{commerce_promotion}/translations",
 *     "drupal:content-translation-add" = "/promotion/{commerce_promotion}/translations/add/{source}/{target}",
 *     "drupal:content-translation-edit" = "/promotion/{commerce_promotion}/translations/edit/{language}",
 *     "drupal:content-translation-delete" = "/promotion/{commerce_promotion}/translations/delete/{language}",
 *   },
 * )
 */
class Promotion extends CommerceContentEntityBase implements PromotionInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel == 'canonical') {
      $rel = 'edit-form';
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() {
    $duplicate = parent::createDuplicate();
    // Coupons cannot be transferred because their codes are unique.
    $duplicate->set('coupons', []);

    return $duplicate;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return $this->get('display_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDisplayName($display_name) {
    $this->set('display_name', $display_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->set('description', $description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderTypes() {
    return $this->get('order_types')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function setOrderTypes(array $order_types) {
    $this->set('order_types', $order_types);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderTypeIds() {
    $order_type_ids = [];
    foreach ($this->get('order_types') as $field_item) {
      $order_type_ids[] = $field_item->target_id;
    }
    return $order_type_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrderTypeIds(array $order_type_ids) {
    $this->set('order_types', $order_type_ids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStores() {
    return $this->getTranslatedReferencedEntities('stores');
  }

  /**
   * {@inheritdoc}
   */
  public function setStores(array $stores) {
    $this->set('stores', $stores);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStoreIds() {
    $store_ids = [];
    foreach ($this->get('stores') as $field_item) {
      $store_ids[] = $field_item->target_id;
    }
    return $store_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function setStoreIds(array $store_ids) {
    $this->set('stores', $store_ids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOffer() {
    if (!$this->get('offer')->isEmpty()) {
      return $this->get('offer')->first()->getTargetInstance();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setOffer(PromotionOfferInterface $offer) {
    $this->set('offer', [
      'target_plugin_id' => $offer->getPluginId(),
      'target_plugin_configuration' => $offer->getConfiguration(),
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions() {
    $conditions = [];
    foreach ($this->get('conditions') as $field_item) {
      /** @var \Drupal\commerce\Plugin\Field\FieldType\PluginItemInterface $field_item */
      $condition = $field_item->getTargetInstance();
      if ($condition instanceof ParentEntityAwareInterface) {
        $condition->setParentEntity($this);
      }
      $conditions[] = $condition;
    }
    return $conditions;
  }

  /**
   * {@inheritdoc}
   */
  public function setConditions(array $conditions) {
    $this->set('conditions', []);
    foreach ($conditions as $condition) {
      if ($condition instanceof ConditionInterface) {
        $this->get('conditions')->appendItem([
          'target_plugin_id' => $condition->getPluginId(),
          'target_plugin_configuration' => $condition->getConfiguration(),
        ]);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditionOperator() {
    return $this->get('condition_operator')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setConditionOperator($condition_operator) {
    $this->set('condition_operator', $condition_operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCouponIds() {
    $coupon_ids = [];
    foreach ($this->get('coupons') as $field_item) {
      $coupon_ids[] = $field_item->target_id;
    }
    return $coupon_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoupons() {
    $coupons = $this->get('coupons')->referencedEntities();
    return $coupons;
  }

  /**
   * {@inheritdoc}
   */
  public function setCoupons(array $coupons) {
    $this->set('coupons', $coupons);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCoupons() {
    return !$this->get('coupons')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function addCoupon(CouponInterface $coupon) {
    if (!$this->hasCoupon($coupon)) {
      $this->get('coupons')->appendItem($coupon);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeCoupon(CouponInterface $coupon) {
    $index = $this->getCouponIndex($coupon);
    if ($index !== FALSE) {
      $this->get('coupons')->offsetUnset($index);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCoupon(CouponInterface $coupon) {
    return in_array($coupon->id(), $this->getCouponIds());
  }

  /**
   * Gets the index of the given coupon.
   *
   * @param \Drupal\commerce_promotion\Entity\CouponInterface $coupon
   *   The coupon.
   *
   * @return int|bool
   *   The index of the given coupon, or FALSE if not found.
   */
  protected function getCouponIndex(CouponInterface $coupon) {
    return array_search($coupon->id(), $this->getCouponIds());
  }

  /**
   * {@inheritdoc}
   */
  public function getUsageLimit() {
    return $this->get('usage_limit')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUsageLimit($usage_limit) {
    $this->set('usage_limit', $usage_limit);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerUsageLimit() {
    return $this->get('usage_limit_customer')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomerUsageLimit($usage_limit_customer) {
    $this->set('usage_limit_customer', $usage_limit_customer);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate($store_timezone = 'UTC') {
    return new DrupalDateTime($this->get('start_date')->value, $store_timezone);
  }

  /**
   * {@inheritdoc}
   */
  public function setStartDate(DrupalDateTime $start_date) {
    $this->get('start_date')->value = $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate($store_timezone = 'UTC') {
    if (!$this->get('end_date')->isEmpty()) {
      return new DrupalDateTime($this->get('end_date')->value, $store_timezone);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setEndDate(?DrupalDateTime $end_date = NULL) {
    $this->get('end_date')->value = NULL;
    if ($end_date) {
      $this->get('end_date')->value = $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompatibility() {
    return $this->get('compatibility')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCompatibility($compatibility) {
    if (!in_array($compatibility, [self::COMPATIBLE_NONE, self::COMPATIBLE_ANY])) {
      throw new \InvalidArgumentException('Invalid compatibility type');
    }
    $this->get('compatibility')->value = $compatibility;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled) {
    $this->set('status', (bool) $enabled);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return (int) $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresCoupon() {
    return !empty($this->get('require_coupon')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function available(OrderInterface $order) {
    if (!$this->isEnabled()) {
      return FALSE;
    }
    // A promotion that requires a coupon to apply should reference coupons
    // to apply.
    if ($this->requiresCoupon() && !$this->hasCoupons()) {
      return FALSE;
    }
    if (!in_array($order->bundle(), $this->getOrderTypeIds())) {
      return FALSE;
    }
    if (!empty($this->getStoreIds()) && !in_array($order->getStoreId(), $this->getStoreIds())) {
      return FALSE;
    }
    $date = $order->getCalculationDate();
    $store_timezone = $date->getTimezone()->getName();
    $start_date = $this->getStartDate($store_timezone);
    if ($start_date->format('U') > $date->format('U')) {
      return FALSE;
    }
    $end_date = $this->getEndDate($store_timezone);
    if ($end_date && $end_date->format('U') <= $date->format('U')) {
      return FALSE;
    }

    $usage_limit = $this->getUsageLimit();
    $usage_limit_customer = $this->getCustomerUsageLimit();
    // If there are no usage limits, the promotion is available.
    if (!$usage_limit && !$usage_limit_customer) {
      return TRUE;
    }
    /** @var \Drupal\commerce_promotion\PromotionUsageInterface $usage */
    $usage = \Drupal::service('commerce_promotion.usage');

    if ($usage_limit && $usage_limit <= $usage->load($this)) {
      return FALSE;
    }
    if ($usage_limit_customer) {
      // Promotion cannot apply to orders without email addresses.
      if (!$email = $order->getEmail()) {
        return FALSE;
      }
      if ($usage_limit_customer <= $usage->load($this, $email)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order) {
    // Check compatibility.
    // @todo port remaining strategies from Commerce Discount #2762997.
    switch ($this->getCompatibility()) {
      case self::COMPATIBLE_NONE:
        // If there are any existing promotions, then this cannot apply.
        foreach ($order->collectAdjustments() as $adjustment) {
          if (($adjustment->getType() == 'promotion') &&
            ($adjustment->getSourceId() != $this->id())) {
            return FALSE;
          }
        }
        break;

      case self::COMPATIBLE_ANY:
        break;
    }

    $conditions = $this->getConditions();
    if (!$conditions) {
      // Promotions without conditions always apply.
      return TRUE;
    }
    // Filter the conditions just in case there are leftover order item
    // conditions (which have been moved to offer conditions).
    $conditions = array_filter($conditions, function ($condition) {
      /** @var \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface $condition */
      return $condition->getEntityTypeId() == 'commerce_order';
    });
    $condition_group = new ConditionGroup($conditions, $this->getConditionOperator());

    return $condition_group->evaluate($order);
  }

  /**
   * {@inheritdoc}
   */
  public function apply(OrderInterface $order) {
    $offer = $this->getOffer();
    if ($offer instanceof OrderItemPromotionOfferInterface) {
      $offer_conditions = new ConditionGroup($offer->getConditions(), $offer->getConditionOperator());
      // Apply the offer to order items that pass the conditions.
      foreach ($order->getItems() as $order_item) {
        // Skip order items with a null unit price or with a quantity = 0.
        if (!$order_item->getUnitPrice() || Calculator::compare($order_item->getQuantity(), '0') === 0) {
          continue;
        }
        if ($offer_conditions->evaluate($order_item)) {
          $offer->apply($order_item, $this);
        }
      }
    }
    else {
      $offer->apply($order, $this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clear(OrderInterface $order) {
    $offer = $this->getOffer();
    if ($offer instanceof OrderItemPromotionOfferInterface) {
      foreach ($order->getItems() as $order_item) {
        $offer->clear($order_item, $this);
      }
    }
    else {
      $offer->clear($order, $this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // Explicitly set the owner ID to 0 if the translation owner is anonymous
      // (This will ensure we don't store a broken reference in case the user
      // no longer exists).
      if ($translation->getOwner()->isAnonymous()) {
        $translation->setOwnerId(0);
      }
    }
    // The promotion references at least a coupon code, therefore it is now
    // required to apply a coupon in order for the promotion to apply.
    if ($this->hasCoupons()) {
      $this->set('require_coupon', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Ensure there's a back-reference on each coupon.
    foreach ($this->getCoupons() as $coupon) {
      if (!$coupon->getPromotionId()) {
        $coupon->promotion_id = $this->id();
        $coupon->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    // Delete the linked coupons and usage records.
    $coupons = [];
    foreach ($entities as $entity) {
      foreach ($entity->getCoupons() as $coupon) {
        $coupons[] = $coupon;
      }
    }
    /** @var \Drupal\commerce_promotion\CouponStorageInterface $coupon_storage */
    $coupon_storage = \Drupal::service('entity_type.manager')->getStorage('commerce_promotion_coupon');
    $coupon_storage->delete($coupons);
    /** @var \Drupal\commerce_promotion\PromotionUsageInterface $usage */
    $usage = \Drupal::service('commerce_promotion.usage');
    $usage->delete($entities);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The promotion name.'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDescription(t('The promotion owner.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['display_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Display name'))
      ->setDescription(t('If provided, shown on the order instead of "@translated".', [
        '@translated' => t('Discount'),
      ]))
      ->setTranslatable(TRUE)
      ->setSettings([
        'display_description' => TRUE,
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setDescription(t('Additional information about the promotion to show to the customer'))
      ->setTranslatable(TRUE)
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 1,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time when the promotion was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time when the promotion was last edited.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['order_types'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Order types'))
      ->setDescription(t('The order types for which the promotion is valid.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(TRUE)
      ->setSetting('target_type', 'commerce_order_type')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'commerce_entity_select',
        'weight' => 2,
      ]);

    $fields['stores'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Stores'))
      ->setDescription(t('Limit promotion availability to selected stores.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'commerce_store')
      ->setSetting('handler', 'default')
      ->setSetting('optional_label', t('Restrict to specific stores'))
      ->setDisplayOptions('form', [
        'type' => 'commerce_entity_select',
        'weight' => 2,
      ]);

    $fields['offer'] = BaseFieldDefinition::create('commerce_plugin_item:commerce_promotion_offer')
      ->setLabel(t('Offer type'))
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', [static::class, 'getOfferOptions'])
      ->setDisplayOptions('form', [
        'type' => 'commerce_plugin_select',
        'weight' => 3,
      ]);

    $fields['conditions'] = BaseFieldDefinition::create('commerce_plugin_item:commerce_condition')
      ->setLabel(t('Conditions'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_conditions',
        'weight' => 3,
        'settings' => [
          'entity_types' => ['commerce_order'],
        ],
      ]);

    $fields['condition_operator'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Condition operator'))
      ->setDescription(t('The condition operator.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'AND' => t('All conditions must pass'),
        'OR' => t('Only one condition must pass'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDefaultValue('AND');

    $fields['coupons'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Coupons'))
      ->setDescription(t('Coupons which allow promotion to be redeemed.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(FALSE)
      ->setSetting('target_type', 'commerce_promotion_coupon')
      ->setSetting('handler', 'default');

    $fields['usage_limit'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Usage limit'))
      ->setDescription(t('The maximum number of times the promotion can be used. 0 for unlimited.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'commerce_usage_limit',
        'weight' => 4,
      ]);

    $fields['usage_limit_customer'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Customer usage limit'))
      ->setDescription(t('The maximum number of times the promotion can be used by a customer. 0 for unlimited.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'commerce_usage_limit',
        'weight' => 4,
      ]);

    $fields['start_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Start date'))
      ->setDescription(t('The date the promotion becomes valid.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDefaultValueCallback('Drupal\commerce_promotion\Entity\Promotion::getDefaultStartDate')
      ->setDisplayOptions('form', [
        'type' => 'commerce_store_datetime',
        'weight' => 5,
      ]);

    $fields['end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('End date'))
      ->setDescription(t('The date after which the promotion is invalid.'))
      ->setRequired(FALSE)
      ->setSetting('datetime_type', 'datetime')
      ->setSetting('datetime_optional_label', t('Provide an end date'))
      ->setDisplayOptions('form', [
        'type' => 'commerce_store_datetime',
        'weight' => 6,
      ]);

    $fields['compatibility'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Compatibility with other promotions'))
      ->setSetting('allowed_values_function', ['\Drupal\commerce_promotion\Entity\Promotion', 'getCompatibilityOptions'])
      ->setRequired(TRUE)
      ->setDefaultValue(self::COMPATIBLE_ANY)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 4,
      ]);

    $fields['allow_multiple_coupons'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Allow multiple coupons'))
      ->setDescription(t('Allow multiple coupons to apply to a single order.'))
      ->setDefaultValue(FALSE)
      ->setSetting('display_description', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['require_coupon'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Require a coupon to apply this promotion'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether the promotion is enabled.'))
      ->setDefaultValue(TRUE)
      ->setRequired(TRUE)
      ->setSettings([
        'on_label' => t('Enabled'),
        'off_label' => t('Disabled'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
      ]);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The weight of this promotion in relation to others.'))
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * Default value callback for 'start_date' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return string
   *   The default value (date string).
   */
  public static function getDefaultStartDate() {
    $timestamp = \Drupal::time()->getRequestTime();
    return date(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $timestamp);
  }

  /**
   * Helper callback for uasort() to sort promotions by weight and label.
   *
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $a
   *   The first promotion to sort.
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $b
   *   The second promotion to sort.
   *
   * @return int
   *   The comparison result for uasort().
   */
  public static function sort(PromotionInterface $a, PromotionInterface $b) {
    $a_weight = $a->getWeight();
    $b_weight = $b->getWeight();
    if ($a_weight == $b_weight) {
      $a_label = $a->label();
      $b_label = $b->label();
      return strnatcasecmp($a_label, $b_label);
    }
    return ($a_weight < $b_weight) ? -1 : 1;
  }

  /**
   * Gets the allowed values for the 'compatibility' base field.
   *
   * @return array
   *   The allowed values.
   */
  public static function getCompatibilityOptions() {
    return [
      self::COMPATIBLE_ANY => t('Any promotion'),
      self::COMPATIBLE_NONE => t('Not with any other promotions'),
    ];
  }

  /**
   * Gets the allowed values for the 'offer' base field.
   *
   * @return array
   *   The allowed values.
   */
  public static function getOfferOptions() {
    /** @var \Drupal\commerce_promotion\PromotionOfferManager $offer_manager */
    $offer_manager = \Drupal::getContainer()->get('plugin.manager.commerce_promotion_offer');
    $plugins = array_map(static function ($definition) {
      return $definition['label'];
    }, $offer_manager->getDefinitions());
    asort($plugins);

    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function isMultipleCouponsAllowed() {
    return (bool) $this->get('allow_multiple_coupons')->value;
  }

}
