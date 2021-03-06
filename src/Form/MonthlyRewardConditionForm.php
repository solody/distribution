<?php

namespace Drupal\distribution\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\distribution\Entity\MonthlyRewardConditionInterface;

/**
 * Class MonthlyRewardConditionForm.
 */
class MonthlyRewardConditionForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var MonthlyRewardConditionInterface $distribution_mr_condition */
    $distribution_mr_condition = $this->entity;



    /* You will need additional form elements for your custom properties. */
    $plugins = array_column(\Drupal::service('plugin.manager.monthly_reward_condition')->getDefinitions(), 'label', 'id');
    asort($plugins);

    // Use the first available plugin as the default value.
    if (!$distribution_mr_condition->getPluginId()) {
      $plugin_ids = array_keys($plugins);
      $plugin = reset($plugin_ids);
      $distribution_mr_condition->setPluginId($plugin);
    }
    // The form state will have a plugin value if #ajax was used.
    $plugin = $form_state->getValue('plugin', $distribution_mr_condition->getPluginId());
    // Pass the plugin configuration only if the plugin hasn't been changed via #ajax.
    $plugin_configuration = $distribution_mr_condition->getPluginId() == $plugin ? $distribution_mr_condition->getPluginConfiguration() : [];



    $wrapper_id = Html::getUniqueId('monthly_reward_condition-config-form');
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $distribution_mr_condition->label(),
      '#description' => $this->t("Label for the Monthly reward condition."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $distribution_mr_condition->id(),
      '#machine_name' => [
        'exists' => '\Drupal\distribution\Entity\MonthlyRewardCondition::load',
      ],
      '#disabled' => !$distribution_mr_condition->isNew(),
    ];

    $form['plugin'] = [
      '#type' => 'radios',
      '#title' => $this->t('Plugin'),
      '#options' => $plugins,
      '#default_value' => $plugin,
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
    ];

    $form['configuration'] = [
      '#type' => 'commerce_plugin_configuration',
      '#plugin_type' => 'monthly_reward_condition',
      '#plugin_id' => $plugin,
      '#default_value' => $plugin_configuration,
    ];

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var MonthlyRewardConditionInterface $distribution_mr_condition */
    $distribution_mr_condition = $this->entity;
    $distribution_mr_condition->setPluginConfiguration($form_state->getValue(['configuration']));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $distribution_mr_condition = $this->entity;
    $status = $distribution_mr_condition->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Monthly reward condition.', [
          '%label' => $distribution_mr_condition->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Monthly reward condition.', [
          '%label' => $distribution_mr_condition->label(),
        ]));
    }
    $form_state->setRedirectUrl($distribution_mr_condition->toUrl('collection'));
  }

}
