<?php
namespace Drupal\distribution\Plugin\CommissionType;

use Drupal\distribution\Plugin\CommissionTypeBase;
use Drupal\entity\BundleFieldDefinition;

/**
 * 月度奖励
 * @CommissionType(
 *   id = "monthly_reward",
 *   label = @Translation("Monthly Reward")
 * )
 */
class MonthlyReward extends CommissionTypeBase
{

    /**
     * Builds the field definitions for entities of this bundle.
     *
     * Important:
     * Field names must be unique across all bundles.
     * It is recommended to prefix them with the bundle name (plugin ID).
     *
     * @return \Drupal\entity\BundleFieldDefinition[]
     *   An array of bundle field definitions, keyed by field name.
     */
    public function buildFieldDefinitions()
    {
        $fields['statement_id'] = BundleFieldDefinition::create('entity_reference')
            ->setLabel(t('本奖金所关联的月度奖励报表'))
            ->setSetting('target_type', 'distribution_monthly_statement')
            ->setDisplayOptions('view', [
                'label' => 'inline',
                'type' => 'entity_reference_label'
            ]);

        return $fields;
    }
}