<?php

namespace Drupal\distribution;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\Core\Session\AccountInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\distribution\Entity\AcceptanceInterface;
use Drupal\distribution\Entity\DistributorInterface;
use Drupal\distribution\Entity\Leader;
use Drupal\distribution\Entity\LeaderInterface;
use Drupal\distribution\Entity\MonthlyStatementInterface;
use Drupal\distribution\Entity\PromoterInterface;
use Drupal\distribution\Event\CommissionEvent;
use Drupal\finance\Entity\Ledger;
use Drupal\finance\FinanceManagerInterface;
use Drupal\distribution\Entity\Commission;
use Drupal\distribution\Entity\Promoter;
use Drupal\distribution\Entity\Distributor;
use Drupal\distribution\Entity\Event;
use Drupal\distribution\Entity\Target;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\user\Entity\User;

/**
 * Class DistributionManager.
 */
class DistributionManager implements DistributionManagerInterface {
  const FINANCE_ACCOUNT_TYPE = 'distribution';
  const FINANCE_PENDING_ACCOUNT_TYPE = 'distribution_pending';
  /**
   * Drupal\finance\FinanceManagerInterface definition.
   *
   * @var \Drupal\finance\FinanceManagerInterface
   */
  protected $financeFinanceManager;

  /**
   * @var TaskManagerInterface
   */
  protected $taskManager;

  /**
   * @var MonthlyRewardManagerInterface
   */
  protected $monthlyRewardManager;

  /**
   * Constructs a new DistributionManager object.
   * @param FinanceManagerInterface $finance_finance_manager
   * @param TaskManagerInterface $task_manager
   * @param MonthlyRewardManagerInterface $monthly_reward_manager
   */
  public function __construct(FinanceManagerInterface $finance_finance_manager, TaskManagerInterface $task_manager, MonthlyRewardManagerInterface $monthly_reward_manager) {
    $this->financeFinanceManager = $finance_finance_manager;
    $this->taskManager = $task_manager;
    $this->monthlyRewardManager = $monthly_reward_manager;
  }

  /**
   * @return \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  private function getEventDispatcher() {
    return \Drupal::getContainer()->get('event_dispatcher');
  }

  /**
   * @inheritdoc
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function distribute(OrderInterface $commerce_order) {
    // 检查系统是否开启分销
    $config = \Drupal::config('distribution.settings');

    if ($config->get('enable')) {
      // 检查订单是否已经处理过佣金，防止重复处理
      if ($this->isDistributed($commerce_order)) return;

      // 检查订单能否确定上级分销用户
      $distributor = $this->determineDistributor($commerce_order);
      if (!$distributor) return;

      // 把分销商用户记录到订单字段
      $order = Order::load($commerce_order->id());
      $order->set('distributor', $distributor);
      $order->save();

      foreach ($commerce_order->getItems() as $orderItem) {
        $this->createEvent($orderItem, $distributor);
      }

      // 创建任务成绩
      $this->taskManager->createOrderAchievement($distributor, $order);

      // 如果开启了月度奖金，那么处理月度奖金
      if ($config->get('commission.monthly_reward')) {
        // $order 必须是已经保存有 distributor 字段的
        // 为订单创建月度奖金池金额，提升分销用户的奖励条件值、奖金分配比值
        $this->monthlyRewardManager->handleDistribution($order);
      }
    }
  }

  public function isDistributed(OrderInterface $commerce_order) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_event')
      ->condition('order_id', $commerce_order->id());
    $ids = $query->execute();

    return !empty($ids);
  }

  /**
   * @param OrderItemInterface $commerce_order_item
   * @param Distributor $distributor
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createEvent(OrderItemInterface $commerce_order_item, Distributor $distributor) {
    $target = $this->getTarget($commerce_order_item->getPurchasedEntity());

    // 如果商品没有设置分成，中止分佣
    if (!$target) return;

    $event = Event::create([
      'order_id' => $commerce_order_item->getOrderId(),
      'order_item_id' => $commerce_order_item->id(),
      'distributor_id' => $distributor,
      'target_id' => $target,
      'amount' => $commerce_order_item->getTotalPrice(),
      'amount_promotion' => $this->computeCommissionAmount($target, Commission::TYPE_PROMOTION)->multiply($commerce_order_item->getQuantity()),
      'amount_chain' => $this->computeCommissionAmount($target, Commission::TYPE_CHAIN)->multiply($commerce_order_item->getQuantity()),
      'amount_chain_senior' => $this->computeCommissionAmount($target, Commission::TYPE_CHAIN, true)->multiply($commerce_order_item->getQuantity()),
      'amount_leader' => $this->computeCommissionAmount($target, Commission::TYPE_LEADER)->multiply($commerce_order_item->getQuantity()),
      'name' => '订单[' . $commerce_order_item->getOrderId() . ']中商品[' . $target->getName() . ']产生佣金事件'
    ]);

    $event->save();

    $this->createCommissions($event);
  }

  /**
   * 计算佣金事件产生的特定类型的佣金总额
   *
   * @param Target $target
   * @param $commission_type
   * @param Price $price
   * @param bool $senior
   * @return Price
   */
  public function computeCommissionAmount(Target $target, $commission_type, $senior = false) {
    // 检查配置的计算模式
    $config = \Drupal::config('distribution.settings');

    $computed_price = null;

    if ($config->get('commission.compute_mode') === 'fixed_amount') {
      // 固定金额，直接取已设置的固定金额
      switch ($commission_type) {
        case Commission::TYPE_PROMOTION:
          if ($target->getAmountPromotion())
            $computed_price = $target->getAmountPromotion();
          break;
        case Commission::TYPE_CHAIN:
          if ($target->getAmountChain() && !$senior) {
            $computed_price = $target->getAmountChain();
          }
          if ($target->getAmountChainSenior() && $senior) {
            $computed_price = $target->getAmountChainSenior();
          }
          break;
        case Commission::TYPE_LEADER:
          if ($target->getAmountLeader())
            $computed_price = $target->getAmountLeader();
          break;
      }
    } elseif ($config->get('commission.compute_mode') === 'dynamic_percentage') {
      // 动态计算，取百分比设置，从成交金额中计算
      $percentage = 0;
      $price = $target->getPurchasableEntity()->getPrice();
      switch ($commission_type) {
        case Commission::TYPE_PROMOTION:
          if ($target->getPercentagePromotion()) {
            $percentage = $target->getPercentagePromotion();
          }
          break;
        case Commission::TYPE_CHAIN:
          if ($target->getPercentageChain() && !$senior) {
            $percentage = $target->getPercentageChain();
          }
          if ($target->getPercentageChainSenior() && $senior) {
            $percentage = $target->getPercentageChainSenior();
          }
          break;
        case Commission::TYPE_LEADER:
          if ($target->getPercentageLeader()) {
            $percentage = $target->getPercentageLeader();
          }
          break;
      }

      if ($percentage > 0) $computed_price = new Price((string)($price->getNumber() * $percentage / 100), $price->getCurrencyCode());
    }

    if (!$computed_price) $computed_price = new Price('0.00', 'CNY');

    return $computed_price;
  }

  public function createCommissions(Event $distributionEvent) {
    // 检查需要产生的佣金类型
    $config = \Drupal::config('distribution.settings');

    // 推广佣金
    if ($config->get('commission.promotion')) {
      // 非分销用户成交的订单，才能产生推广佣金
      if (!$this->getDistributor($distributionEvent->getOrder()->getCustomer())) {
        // 读取推广者
        $promoters = $this->getPromoters($distributionEvent->getOrder()->getCustomer());
        // 平分佣金
        $amount = new Price((string)($distributionEvent->getAmountPromotion()->getNumber() / count($promoters)), $distributionEvent->getAmountPromotion()->getCurrencyCode());

        foreach ($promoters as $promoter) {
          $commission = Commission::create([
            'event_id' => $distributionEvent->id(),
            'type' => 'promotion',
            'distributor_id' => $promoter->getDistributor()->id(),
            'name' => $distributionEvent->getName() . '：推广佣金 ' . $distributionEvent->getAmountPromotion()->getCurrencyCode() . $distributionEvent->getAmountPromotion()->getNumber() . ' / ' . count($promoters),
            'amount' => $amount,
            'promoter_id' => $promoter->id()
          ]);
          $commission->save();

          // 记账到 Finance
          $finance_account = $this->financeFinanceManager->getAccount($promoter->getDistributor()->getOwner(), self::FINANCE_PENDING_ACCOUNT_TYPE);
          if ($finance_account) {
            $this->financeFinanceManager->createLedger(
              $finance_account,
              Ledger::AMOUNT_TYPE_DEBIT,
              $amount,
              $commission->getName(),
              $commission
            );
          }

          // 触发事件
          $this->getEventDispatcher()->dispatch(CommissionEvent::PROMOTION, new CommissionEvent($commission));
        }
      }
    }

    // 链级佣金
    if ($config->get('commission.chain')) {
      // 计算分佣链级
      $level_percentages = [
        (float)$config->get('chain_commission.level_1'),
        (float)$config->get('chain_commission.level_2'),
        (float)$config->get('chain_commission.level_3')
      ];

      $current_distributor = $distributionEvent->getDistributor();
      $computed_level_percentage = 0;
      $computed_level_percentage_prefix = 1;
      $computed_level_percentage_formula_prefix = '';

      foreach ($level_percentages as $index => $level_percentage) {

        $base_compute_amount = $current_distributor->isSenior() ? $distributionEvent->getAmountChainSenior() : $distributionEvent->getAmountChain();
        $computed_level_percentage = $computed_level_percentage_prefix * ((float)$level_percentage / 100);
        $computed_level_percentage_prefix = $computed_level_percentage_prefix * (1- ((float)$level_percentage / 100));
        $computed_level_amount = $base_compute_amount->multiply((string)$computed_level_percentage);

        $computed_level_percentage_formula = $base_compute_amount . $computed_level_percentage_formula_prefix . ' x ' . $level_percentage . '%';
        $computed_level_percentage_formula_prefix .= ' x (1 - ' . $level_percentage . '%)';

        // 如果开启了分销商自己分佣，在确定订单的从属分销商时，会把订单购买者自己作为从属
        if ($index === 0 &&
          $config->get('chain_commission.distributor_self_commission.enable') &&
          $config->get('chain_commission.distributor_self_commission.directly_adjust_order_amount')) {
          // 如果开启了佣金直抵，并且订单购买者本身已经是分销商，则跳过分佣，因为他已在下单时通过价格调整的方式享受了佣金
        } else {

          $commission = Commission::create([
            'event_id' => $distributionEvent->id(),
            'type' => 'chain',
            'distributor_id' => $current_distributor->id(),
            'name' => $distributionEvent->getName() . '：链级佣金，' . ($index+1) . '级上游，计算方法：' . $computed_level_percentage_formula,
            'amount' => $computed_level_amount
          ]);
          $commission->save();

          // 记账到 Finance
          $finance_account = $this->financeFinanceManager->getAccount($current_distributor->getOwner(), self::FINANCE_PENDING_ACCOUNT_TYPE);
          if ($finance_account) {
            $this->financeFinanceManager->createLedger(
              $finance_account,
              Ledger::AMOUNT_TYPE_DEBIT,
              $computed_level_amount,
              $commission->getName(),
              $commission
            );
          }

          // 触发事件
          $this->getEventDispatcher()->dispatch(CommissionEvent::CHAIN, new CommissionEvent($commission));
        }

        $current_distributor = $current_distributor->getUpstreamDistributor();
        if (!$current_distributor) break;
      }

    }

    // 团队领导佣金
    if ($config->get('commission.leader')) {
      // 查找团队领导
      $leader = self::computeLeader($distributionEvent->getDistributor());
      $upstream_leader = null;
      if ($leader instanceof Leader) $upstream_leader = self::computeLeader($leader->getDistributor());

      if ($leader && !$upstream_leader) {
        $commission = Commission::create([
          'event_id' => $distributionEvent->id(),
          'type' => 'leader',
          'distributor_id' => $leader->getDistributor()->id(),
          'name' => $distributionEvent->getName() . '：团队领导佣金 ' . $distributionEvent->getAmountLeader()->getCurrencyCode() . $distributionEvent->getAmountLeader()->getNumber(),
          'amount' => $distributionEvent->getAmountLeader(),
          'leader_id' => $leader->id()
        ]);
        $commission->save();

        // 记账到 Finance
        $finance_account = $this->financeFinanceManager->getAccount($leader->getDistributor()->getOwner(), self::FINANCE_PENDING_ACCOUNT_TYPE);
        if ($finance_account) {
          $this->financeFinanceManager->createLedger(
            $finance_account,
            Ledger::AMOUNT_TYPE_DEBIT,
            $distributionEvent->getAmountLeader(),
            $commission->getName(),
            $commission
          );
        }

        // 触发事件
        $this->getEventDispatcher()->dispatch(CommissionEvent::LEADER, new CommissionEvent($commission));
      } else if ($leader && $upstream_leader) {
        $group_leader_percentage = $config->get('leader_commission.group_leader_percentage');
        $group_leader_amount = $distributionEvent->getAmountLeader()->multiply((string)($group_leader_percentage/100));

        $commission = Commission::create([
          'event_id' => $distributionEvent->id(),
          'type' => 'leader',
          'distributor_id' => $leader->getDistributor()->id(),
          'name' => $distributionEvent->getName() . '：团队组长佣金 ' . $group_leader_amount->getCurrencyCode() . $group_leader_amount->getNumber() . ' = '. $distributionEvent->getAmountLeader()->getCurrencyCode() . $distributionEvent->getAmountLeader()->getNumber().' x '.$group_leader_percentage. '%',
          'amount' => $group_leader_amount,
          'leader_id' => $leader->id()
        ]);
        $commission->save();

        // 记账到 Finance
        $finance_account = $this->financeFinanceManager->getAccount($leader->getDistributor()->getOwner(), self::FINANCE_PENDING_ACCOUNT_TYPE);
        if ($finance_account) {
          $this->financeFinanceManager->createLedger(
            $finance_account,
            Ledger::AMOUNT_TYPE_DEBIT,
            $group_leader_amount,
            $commission->getName(),
            $commission
          );
        }

        $leader_amount = $distributionEvent->getAmountLeader()->subtract($group_leader_amount);
        $commission = Commission::create([
          'event_id' => $distributionEvent->id(),
          'type' => 'leader',
          'distributor_id' => $upstream_leader->getDistributor()->id(),
          'name' => $distributionEvent->getName() . '：团队领导佣金 ' . $leader_amount->getCurrencyCode() . $leader_amount->getNumber() . ' = ' . $distributionEvent->getAmountLeader()->getCurrencyCode() . $distributionEvent->getAmountLeader()->getNumber() . ' x (1 -'.$group_leader_percentage. '%)',
          'amount' => $leader_amount,
          'leader_id' => $upstream_leader->id()
        ]);
        $commission->save();

        // 记账到 Finance
        $finance_account = $this->financeFinanceManager->getAccount($upstream_leader->getDistributor()->getOwner(), self::FINANCE_PENDING_ACCOUNT_TYPE);
        if ($finance_account) {
          $this->financeFinanceManager->createLedger(
            $finance_account,
            Ledger::AMOUNT_TYPE_DEBIT,
            $leader_amount,
            $commission->getName(),
            $commission
          );
        }
      }
    }
  }

  /**
   * 创建任务奖励
   * @param AcceptanceInterface $acceptance
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTaskCommissions(AcceptanceInterface $acceptance) {
    $commission = Commission::create([
      'type' => Commission::TYPE_TASK,
      'distributor_id' => $acceptance->getDistributor()->id(),
      'name' => $acceptance->getDistributor()->getName() . '完成了任务['.$acceptance->getTask()->getName().']，获得奖励' . $acceptance->getTask()->getReward()->getCurrencyCode() . $acceptance->getTask()->getReward()->getNumber(),
      'amount' => $acceptance->getTask()->getReward(),
      'acceptance_id' => $acceptance->id()
    ]);
    $commission->save();

    // 记账到 Finance
    $finance_account = $this->financeFinanceManager->getAccount($acceptance->getDistributor()->getOwner(), self::FINANCE_ACCOUNT_TYPE);
    if ($finance_account) {
      $this->financeFinanceManager->createLedger(
        $finance_account,
        Ledger::AMOUNT_TYPE_DEBIT,
        $acceptance->getTask()->getReward(),
        $commission->getName(),
        $commission
      );
    }

    // 触发事件
    $this->getEventDispatcher()->dispatch(CommissionEvent::TASK, new CommissionEvent($commission));
  }

  public function createMonthlyRewardCommission(MonthlyStatementInterface $monthly_statement, DistributorInterface $distributor, Price $amount, $remarks = '') {
    $commission = Commission::create([
      'type' => Commission::TYPE_MONTHLY_REWARD,
      'distributor_id' => $distributor->id(),
      'name' => $distributor->getName() . '达到月度['.$monthly_statement->getMonth().']的奖励条件，获得奖金' .$amount->getCurrencyCode() . $amount->getNumber().$remarks,
      'amount' => $amount,
      'statement_id' => $monthly_statement
    ]);
    $commission->save();

    // 记账到 Finance
    $finance_account = $this->financeFinanceManager->getAccount($distributor->getOwner(), self::FINANCE_ACCOUNT_TYPE);
    if ($finance_account) {
      $this->financeFinanceManager->createLedger(
        $finance_account,
        Ledger::AMOUNT_TYPE_DEBIT,
        $amount,
        $commission->getName(),
        $commission
      );
    }

    // 触发事件
    $this->getEventDispatcher()->dispatch(CommissionEvent::MONTHLY_REWARD, new CommissionEvent($commission));
  }

  /**
   * @param Distributor $distributor
   * @return LeaderInterface|null|static
   */
  public static function computeLeader(Distributor $distributor) {
    $leader = null;
    $upstream_distributor = $distributor->getUpstreamDistributor();

    while (!$leader && $upstream_distributor) {
      $leader = self::getLeader($upstream_distributor);
      $upstream_distributor = $upstream_distributor->getUpstreamDistributor();
    }

    return $leader;
  }

  /**
   * @param Distributor $distributor
   * @return LeaderInterface|null|static
   */
  public static function getLeader(Distributor $distributor) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_leader')
      ->condition('distributor_id', $distributor->id());
    $ids = $query->execute();

    $leader = null;
    if (count($ids)) {
      $leader = Leader::load(array_pop($ids));
    }

    return $leader;
  }


  /**
   * @inheritdoc
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setTarget(PurchasableEntityInterface $purchasableEntity, $data) {
    $target = $this->getTarget($purchasableEntity);

    if (!$target) {
      $target = Target::create([
        'purchasable_entity' => $purchasableEntity
      ]);
    }

    if (isset($data['name'])) $target->setName($data['name']);

    if (isset($data['amount_off'])) $target->setAmountOff(self::makePrice($data['amount_off']));

    if (isset($data['amount_promotion'])) $target->setAmountPromotion(self::makePrice($data['amount_promotion']));
    if (isset($data['amount_chain'])) $target->setAmountChain(self::makePrice($data['amount_chain']));
    if (isset($data['amount_chain_senior'])) $target->setAmountChainSenior(self::makePrice($data['amount_chain_senior']));
    if (isset($data['amount_leader'])) $target->setAmountLeader(self::makePrice($data['amount_leader']));
    if (isset($data['amount_monthly_reward'])) $target->setAmountMonthlyReward(self::makePrice($data['amount_monthly_reward']));

    if (isset($data['percentage_promotion'])) $target->setPercentagePromotion($data['percentage_promotion']);
    if (isset($data['percentage_chain'])) $target->setPercentageChain($data['percentage_chain']);
    if (isset($data['percentage_chain_senior'])) $target->setPercentageChainSenior($data['percentage_chain_senior']);
    if (isset($data['percentage_leader'])) $target->setPercentageLeader($data['percentage_leader']);
    if (isset($data['percentage_monthly_reward'])) $target->setPercentageMonthlyReward($data['percentage_monthly_reward']);

    $target->save();

    return $target;
  }

  public static function makePrice($value) {
    return new Price((string)$value['number'], $value['currency_code']);
  }

  /**
   * @inheritdoc
   */
  public function getTarget(PurchasableEntityInterface $purchasableEntity) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_target')
      ->condition('purchasable_entity.target_id', $purchasableEntity->id())
      ->condition('purchasable_entity.target_type', $purchasableEntity->getEntityTypeId());
    $ids = $query->execute();

    $target = null;
    if (count($ids)) {
      $target = Target::load(array_pop($ids));
    }

    return $target;
  }

  /**
   * @inheritdoc
   */
  public function determineDistributor(OrderInterface $commerce_order) {
    $config = \Drupal::config('distribution.settings');

    $distributor = null;
    $customer_distributor = $this->getDistributor($commerce_order->getCustomer());

    // 购买者自己是分销商
    if ($customer_distributor) {
      if ($config->get('chain_commission.distributor_self_commission.enable')) {
        $distributor = $customer_distributor;
      } else {
        $upstream_distributor = $customer_distributor->getUpstreamDistributor();
        if ($upstream_distributor) $distributor = $upstream_distributor;
      }
    } else {
      // 从推广关系中确定分销用户，取最后一个推广者
      $promoter = $this->getLastPromoter($commerce_order->getCustomer());
      if ($promoter) $distributor = $promoter->getDistributor();
    }
    return $distributor;
  }

  /**
   * @inheritdoc
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createPromoter(Distributor $distributor, AccountInterface $user) {
    if ($user->isAnonymous()) {
      throw new \Exception('匿名用户不能被推广');
    }

    if ($distributor->getOwnerId() === $user->id()) {
      throw new \Exception('不能推广自己');
    }

    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_promoter')
      ->condition('distributor_id', $distributor->id())
      ->condition('user_id', $user->id());
    $ids = $query->execute();

    $promoter = null;
    if (count($ids) === 0) {
      $promoter = Promoter::create([
        'distributor_id' => $distributor->id(),
        'user_id' => $user->id()
      ]);
    } else {
      $promoter = Promoter::load(array_pop($ids));
      $promoter->setChangedTime(time());
    }

    $promoter->save();
    return $promoter;
  }

  /**
   * @param AccountInterface $user
   * @return PromoterInterface[]|static[]
   */
  public function getPromoters(AccountInterface $user) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_promoter')
      ->condition('user_id', $user->id());

    $ids = $query->execute();

    if (count($ids)) {
      return Promoter::loadMultiple($ids);
    }
  }

  /**
   * @param Distributor $distributor
   * @return \Drupal\Core\Entity\EntityInterface[]|Promoter[]
   */
  public function getPromotedUsers(Distributor $distributor) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_promoter')
      ->condition('distributor_id', $distributor->id());

    $ids = $query->execute();

    if (count($ids)) {
      return Promoter::loadMultiple($ids);
    }
  }

  /**
   * @param AccountInterface $user
   * @return Promoter|null
   */
  public function getLastPromoter(AccountInterface $user) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_promoter')
      ->condition('user_id', $user->id())
      ->sort('changed', 'DESC');

    $ids = $query->execute();

    if (count($ids)) {
      reset($ids);
      return Promoter::load(current($ids));
    } else {
      return null;
    }
  }

  /**
   * @inheritdoc
   */
  public function createDistributor(AccountInterface $user, Distributor $upstream_distributor = null, $state = 'draft', $agent = []) {
    $distributor = $this->getDistributor($user);

    if (!$distributor) {
      $price = new Price('0.00', 'CNY');

      $data = [
        'user_id' => $user->id(),
        'name' => $user->getAccountName(),
        'state' => $state,
        'amount_achievement' => $price,
        'amount_leader' => $price,
        'amount_chain' => $price,
        'amount_promotion' => $price
      ];

      if ($upstream_distributor) {
        $data['upstream_distributor_id'] = $upstream_distributor;
      } else {
        // 从推广者中查找最新一名，作为上级
        $promoter = $this->getLastPromoter($user);
        if ($promoter instanceof PromoterInterface) {
          $data['upstream_distributor_id'] = $promoter->getDistributor();
        }
      }

      $level_number = 1;
      if (isset($data['upstream_distributor_id']) && $data['upstream_distributor_id'] instanceof DistributorInterface) $level_number += $data['upstream_distributor_id']->getLevelNumber();
      $data['level_number'] = $level_number;

      if (isset($agent['name'])) $data['agent_name'] = $agent['name'];
      if (isset($agent['phone'])) $data['agent_phone'] = $agent['phone'];

      $distributor = Distributor::create($data);
      $distributor->save();

      /** @var User $userEntity */
      $userEntity = $user;
      $userEntity->addRole(DISTRIBUTOR_ROLE_ID);
      $userEntity->save();

      // 创建佣金管理账户（调用Finance模块）
      $this->financeFinanceManager->createAccount($user, self::FINANCE_ACCOUNT_TYPE);
      $this->financeFinanceManager->createAccount($user, self::FINANCE_PENDING_ACCOUNT_TYPE);

      // 自动领取新手任务
      $this->taskManager->acceptNewcomerTasks($distributor);
    }

    return $distributor;
  }

  /**
   * @inheritdoc
   */
  public function getDistributor(AccountInterface $user) {
    $distributor = null;

    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_distributor')
      ->condition('user_id', $user->id());
    $ids = $query->execute();

    if (count($ids) !== 0) {
      $distributor = Distributor::load(array_pop($ids));
    }

    return $distributor;
  }

  /**
   * @inheritdoc
   */
  public function getDistributorByPhone($phone) {
    $distributor = null;

    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_distributor')
      ->condition('agent_phone', $phone);
    $ids = $query->execute();

    if (count($ids) !== 0) {
      $distributor = Distributor::load(array_pop($ids));
    }

    return $distributor;
  }

  /**
   * @inheritdoc
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function cancelDistribution(OrderInterface $commerce_order, $is_completed) {
    // 分两种情况：
    // 1、从completed到 cancel
    // 2、从其他状态到 cancel

    // 二者都要处理：把分佣款记为 不可用 status->false
    // 前者：把账目从主账户出账
    // 后者：把账目从预计账户出账

    if ($this->isDistributed($commerce_order)) {

      $events = $this->getOrderEvents($commerce_order);

      foreach ($events as $event) {
        $commissions = $this->getEventCommissions($event);

        foreach ($commissions as $commission) {
          $pending_account = $this->financeFinanceManager->getAccount($commission->getDistributor()->getOwner(), self::FINANCE_PENDING_ACCOUNT_TYPE);
          $main_account = $this->financeFinanceManager->getAccount($commission->getDistributor()->getOwner(), self::FINANCE_ACCOUNT_TYPE);

          $process_account = $pending_account;
          if ($is_completed) {
            $process_account = $main_account;
          }
          $this->financeFinanceManager->createLedger($process_account, Ledger::AMOUNT_TYPE_CREDIT, $commission->getAmount(), '订单取消，取消佣金。（佣金信息：' . $commission->getName() . '）', $commission);

          $commission->setValid(false);
          $commission->save();
        }
      }
    }

    // 取消任务成绩
    // 把对应的成绩记录标为无效，并在总成绩缓存
    // 如果任务已完成，会跳过处理
    $order_distributor = $commerce_order->get('distributor')->entity;
    if ($order_distributor) {
      $this->taskManager->cancelOrderAchievement($commerce_order->get('distributor')->entity, $commerce_order);
    }
  }

  public function upgradeAsLeader(Distributor $distributor) {
    $leader = self::getLeader($distributor);
    if (!$leader) {
      $leader = Leader::create([
        'distributor_id' => $distributor,
        'name' => $distributor->getName()
      ]);

      $leader->save();
    }

    $distributor->setIsLeader(true);
    $distributor->save();

    return $leader;
  }

  /**
   * 把订单的记账金额从预计账户移到主账户
   *
   * @param OrderInterface $commerce_order
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function transferPendingDistribution(OrderInterface $commerce_order) {
    $events = $this->getOrderEvents($commerce_order);

    foreach ($events as $event) {
      $commissions = $this->getEventCommissions($event);

      foreach ($commissions as $commission) {
        $pending_account = $this->financeFinanceManager->getAccount($commission->getDistributor()->getOwner(), self::FINANCE_PENDING_ACCOUNT_TYPE);
        $main_account = $this->financeFinanceManager->getAccount($commission->getDistributor()->getOwner(), self::FINANCE_ACCOUNT_TYPE);

        // 转账到主账户
        $this->financeFinanceManager->transfer($pending_account, $main_account, $commission->getAmount(), '订单完成，佣金由预计账户转入主账户。（分佣信息：' . $commission->getName() . '）', $commission);
      }
    }
  }

  /**
   * @param OrderInterface $commerce_order
   * @return Event[]
   */
  public function getOrderEvents(OrderInterface $commerce_order) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_event')
      ->condition('order_id', $commerce_order->id());
    $ids = $query->execute();

    if (count($ids)) {
      return Event::loadMultiple($ids);
    } else {
      return [];
    }
  }

  /**
   * @param Event $event
   * @return Commission[]
   */
  public function getEventCommissions(Event $event) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_commission')
      ->condition('event_id', $event->id());
    $ids = $query->execute();

    if (count($ids)) {
      return Commission::loadMultiple($ids);
    } else {
      return [];
    }
  }

  /**
   * @param OrderInterface $commerce_order
   * @return Price
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function countOrderCommissionsAmount(OrderInterface $commerce_order) {
    $amount = new Price('0.00', 'CNY');

    $events = $this->getOrderEvents($commerce_order);
    foreach ($events as $event) {
      $commissions = $this->getEventCommissions($event);
      foreach ($commissions as $commission) {
        $amount = $amount->add($commission->getAmount());
      }
    }

    return $amount;
  }

  /**
   * 计算已推广的用户数量
   * @param Distributor $distributor
   * @param null $recent days
   * @return Int
   * @throws \Exception
   */
  public function countPromoters(Distributor $distributor, $recent = null) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_promoter')
      ->condition('distributor_id', $distributor->id());

    if ($recent) {
      $now = new \DateTime('now', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $recent_time = $now->sub(new \DateInterval('P' . $recent . 'D'));
      $query->condition('created', $recent_time->getTimestamp(), '>=');
    }

    return $query->count()->execute();
  }

  public function countOrders(Distributor $distributor, $recent = null) {
    // 找出关联用户
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('distribution_promoter')
      ->condition('distributor_id', $distributor->id());

    $ids = $query->execute();

    if (count($ids)) {

      $user_ids = [];

      $promoters = Promoter::loadMultiple($ids);
      foreach ($promoters as $promoter) {
        /** @var Promoter $promoter */
        $user_ids[] = $promoter->getUser()->id();
      }

      /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
      $query = \Drupal::entityQuery('commerce_order')
        ->condition('state', 'draft', '<>')
        ->condition('uid', $user_ids, 'IN')
        ->condition('distributor', $distributor->id());

      if ($recent) {
        $now = new \DateTime('now', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $recent_time = $now->sub(new \DateInterval('P' . $recent . 'D'));
        $query->condition('created', $recent_time->getTimestamp(), '>=');
      }

      return $query->count()->execute();
    } else {
      return 0;
    }
  }

  /**
   * @param Distributor $distributor
   * @param null $type
   * @param null $recent
   * @return Price
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function countCommissionTotalAmount(Distributor $distributor, $type = null, $recent = null) {
    $query = \Drupal::entityQuery('distribution_commission')
      ->condition('distributor_id', $distributor->id());

    if ($type) {
      $query->condition('type', $type);
    }

    if ($recent) {
      $now = new \DateTime('now', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $recent_time = $now->sub(new \DateInterval('P' . $recent . 'D'));
      $query->condition('created', $recent_time->getTimestamp(), '>=');
    }

    $ids = $query->execute();

    $commissions = Commission::loadMultiple($ids);

    $price = new Price('0.00', 'CNY');
    foreach ($commissions as $commission) {
      /** @var Commission $commission */
      $price = $price->add($commission->getAmount());
    }

    return $price;
  }
}
