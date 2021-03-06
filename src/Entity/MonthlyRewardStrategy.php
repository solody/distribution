<?php

namespace Drupal\distribution\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Monthly reward strategy entity.
 *
 * @ConfigEntityType(
 *   id = "distribution_mr_strategy",
 *   label = @Translation("Monthly reward strategy"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\distribution\MonthlyRewardStrategyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\distribution\Form\MonthlyRewardStrategyForm",
 *       "edit" = "Drupal\distribution\Form\MonthlyRewardStrategyForm",
 *       "delete" = "Drupal\distribution\Form\MonthlyRewardStrategyDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\distribution\MonthlyRewardStrategyHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "distribution_mr_strategy",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/distribution/distribution_mr_strategy/{distribution_mr_strategy}",
 *     "add-form" = "/admin/distribution/distribution_mr_strategy/add",
 *     "edit-form" = "/admin/distribution/distribution_mr_strategy/{distribution_mr_strategy}/edit",
 *     "delete-form" = "/admin/distribution/distribution_mr_strategy/{distribution_mr_strategy}/delete",
 *     "collection" = "/admin/distribution/distribution_mr_strategy"
 *   }
 * )
 */
class MonthlyRewardStrategy extends ConfigEntityBase implements MonthlyRewardStrategyInterface {

  /**
   * The Monthly reward strategy ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Monthly reward strategy label.
   *
   * @var string
   */
  protected $label;

  /**
   * The plugin ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    $plugin_manager = \Drupal::service('plugin.manager.monthly_reward_strategy');
    return $plugin_manager->createInstance($this->plugin, $this->getPluginConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin_id) {
    $this->plugin = $plugin_id;
    $this->configuration = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginConfiguration(array $configuration) {
    $this->configuration = $configuration;
    return $this;
  }
}
