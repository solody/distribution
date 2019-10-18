<?php

namespace Drupal\distribution\Plugin\rest\resource;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\distribution\DistributionManager;
use Drupal\distribution\Entity\Commission;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "distribution_compute_commission_amount",
 *   label = @Translation("Compute commission amount"),
 *   uri_paths = {
 *     "create" = "/api/rest/distribution/compute-commission-amount"
 *   }
 * )
 */
class ComputeCommissionAmount extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var DistributionManager
   */
  protected $distributionManager;

  /**
   * Constructs a new ComputeCommissionAmount object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user, DistributionManager $distributionManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->distributionManager = $distributionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('distribution'),
      $container->get('current_user'),
      $container->get('distribution.distribution_manager')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param $data
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function post($data) {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $return_data = [];
    foreach ($data as $k => $v) {
      /** @var EntityStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage($k);
      $entities = $storage->loadMultiple($v);

      $return_data[$k] = [];

      foreach ($entities as $entity) {
        if ($entity instanceof PurchasableEntityInterface) {
          $target = $this->distributionManager->getTarget($entity);
          if ($target) {
            $return_data[$k][$entity->id()] = [
              'amount_off' => $target->getAmountOff(),
              'amount_promotion' => $this->distributionManager->computeCommissionAmount(null, $target, Commission::TYPE_PROMOTION)->toArray(),
              'amount_chain' => $this->distributionManager->computeCommissionAmount(null, $target, Commission::TYPE_CHAIN)->toArray(),
              'amount_chain_senior' => $this->distributionManager->computeCommissionAmount(null, $target, Commission::TYPE_CHAIN, true)->toArray(),
              'amount_leader' => $this->distributionManager->computeCommissionAmount(null, $target, Commission::TYPE_LEADER)->toArray(),
            ];
          } else {
            $zero_price = (new Price('0.00', 'CNY'))->toArray();
            $return_data[$k][$entity->id()] = [
              'amount_off' => $zero_price,
              'amount_promotion' => $zero_price,
              'amount_chain' => $zero_price,
              'amount_chain_senior' => $zero_price,
              'amount_leader' => $zero_price,
            ];
          }
        }
      }
    }

    return new ModifiedResourceResponse($return_data, 200);
  }

}
