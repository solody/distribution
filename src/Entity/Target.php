<?php

namespace Drupal\distribution\Entity;

use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Target entity.
 *
 * @ingroup distribution
 *
 * @ContentEntityType(
 *   id = "distribution_target",
 *   label = @Translation("Target"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\distribution\TargetListBuilder",
 *     "views_data" = "Drupal\distribution\Entity\TargetViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\distribution\Form\TargetForm",
 *       "add" = "Drupal\distribution\Form\TargetForm",
 *       "edit" = "Drupal\distribution\Form\TargetForm",
 *       "delete" = "Drupal\distribution\Form\TargetDeleteForm",
 *     },
 *     "access" = "Drupal\distribution\TargetAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\distribution\TargetHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "distribution_target",
 *   admin_permission = "administer target entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/distribution/distribution_target/{distribution_target}",
 *     "add-form" = "/admin/distribution/distribution_target/add",
 *     "edit-form" = "/admin/distribution/distribution_target/{distribution_target}/edit",
 *     "delete-form" = "/admin/distribution/distribution_target/{distribution_target}/delete",
 *     "collection" = "/admin/distribution/distribution_target",
 *   },
 *   field_ui_base_route = "distribution_target.settings"
 * )
 */
class Target extends ContentEntityBase implements TargetInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
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
  public function isActive() {
    return (bool)$this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active) {
    $this->set('status', $active ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmountOff() {
    if (!$this->get('amount_off')->isEmpty()) {
      return $this->get('amount_off')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAmountOff(Price $price) {
    $this->set('amount_off', $price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmountPromotion() {
    if (!$this->get('amount_promotion')->isEmpty()) {
      return $this->get('amount_promotion')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAmountPromotion(Price $price) {
    $this->set('amount_promotion', $price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmountChain() {
    if (!$this->get('amount_chain')->isEmpty()) {
      return $this->get('amount_chain')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAmountChain(Price $price) {
    $this->set('amount_chain', $price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmountChainSenior() {
    if (!$this->get('amount_chain_senior')->isEmpty()) {
      return $this->get('amount_chain_senior')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAmountChainSenior(Price $price) {
    $this->set('amount_chain_senior', $price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmountLeader() {
    if (!$this->get('amount_leader')->isEmpty()) {
      return $this->get('amount_leader')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAmountLeader(Price $price) {
    $this->set('amount_leader', $price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPercentagePromotion() {
    return $this->get('percentage_promotion')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPercentagePromotion($value) {
    $this->set('percentage_promotion', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPercentageChain() {
    return $this->get('percentage_chain')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPercentageChain($value) {
    $this->set('percentage_chain', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPercentageChainSenior() {
    return $this->get('percentage_chain_senior')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPercentageChainSenior($value) {
    $this->set('percentage_chain_senior', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPercentageLeader() {
    return $this->get('percentage_leader')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPercentageLeader($value) {
    $this->set('percentage_leader', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmountMonthlyReward() {
    if (!$this->get('amount_monthly_reward')->isEmpty()) {
      return $this->get('amount_monthly_reward')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAmountMonthlyReward(Price $price) {
    $this->set('amount_monthly_reward', $price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPercentageMonthlyReward() {
    return $this->get('percentage_monthly_reward')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPercentageMonthlyReward($value) {
    $this->set('percentage_monthly_reward', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasableEntity() {
    return $this->get('purchasable_entity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);


    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('商品名称'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string'
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield'
      ]);

    $fields['purchasable_entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel('可购买实体')
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'dynamic_entity_reference_default'
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'dynamic_entity_reference_label'
      ]);

    $fields['amount_off'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('产品价格中的分销优惠金额'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'commerce_price_default'
      ]);

    $fields['percentage_leader'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('产品价格中的团队领导佣金比例'))
      ->setSettings([
        'min' => '0.00',
        'max' => '100.00',
        'suffix' => '%',
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal'
      ])
      ->setDisplayOptions('form', [
        'type' => 'number'
      ]);

    $fields['percentage_chain'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('产品价格中普通分销商的链级佣金比例'))
      ->setSettings([
        'min' => '0.00',
        'max' => '100.00',
        'suffix' => '%',
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal'
      ])
      ->setDisplayOptions('form', [
        'type' => 'number'
      ]);

    $fields['percentage_chain_senior'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('产品价格中高级分销商的链级佣金比例'))
      ->setSettings([
        'min' => '0.00',
        'max' => '100.00',
        'suffix' => '%',
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal'
      ])
      ->setDisplayOptions('form', [
        'type' => 'number'
      ]);

    $fields['percentage_promotion'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('产品价格中的推广佣金比例'))
      ->setSettings([
        'min' => '0.00',
        'max' => '100.00',
        'suffix' => '%',
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal'
      ])
      ->setDisplayOptions('form', [
        'type' => 'number'
      ]);

    $fields['percentage_monthly_reward'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('产品价格中的月度奖金比例'))
      ->setSettings([
        'min' => '0.00',
        'max' => '100.00',
        'suffix' => '%',
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal'
      ])
      ->setDisplayOptions('form', [
        'type' => 'number'
      ]);

    $fields['amount_leader'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('产品价格中的团队领导佣金金额'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'commerce_price_default'
      ]);

    $fields['amount_chain'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('产品价格中普通分销商的链级佣金金额'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'commerce_price_default'
      ]);

    $fields['amount_chain_senior'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('产品价格中高级分销商的链级佣金金额'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'commerce_price_default'
      ]);

    $fields['amount_promotion'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('产品价格中的推广佣金金额'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'commerce_price_default'
      ]);

    $fields['amount_monthly_reward'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('产品价格中的月度奖金金额'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'commerce_price_default'
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('是否启用'))
      ->setDescription(t('如果希望取消一个商品的分销佣金，那么可以把此字段设置为 False。'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox'
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }
}
