<?php
namespace Drupal\distribution\Plugin\CommissionType;

use Drupal\distribution\Plugin\CommissionTypeBase;
use Drupal\entity\BundleFieldDefinition;

/**
 * @CommissionType(
 *   id = "leader",
 *   label = @Translation("Leader")
 * )
 */
class Leader extends CommissionTypeBase
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
        $fields['leader_id'] = BundleFieldDefinition::create('entity_reference')
            ->setLabel(t('团队领导'))
            ->setDescription(t('产生分佣项的团队领导。'))
            ->setSetting('target_type', 'distribution_leader')
            ->setDisplayOptions('view', [
                'label' => 'inline',
                'type' => 'entity_reference_label'
            ]);

        return $fields;
    }
}