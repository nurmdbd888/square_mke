<?php

namespace App\Settings;

/**
 * Business rules used by purchases, sales and the costing engine (M3).
 */
final class BusinessSettings
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->define(new SettingDefinition(
            key: 'vat_recoverable',
            type: SettingDefinition::BOOLEAN,
            default: true,
            group: 'costing',
            label: 'Purchase VAT is recoverable',
            help: 'When on, VAT paid by Square is left out of stock cost.',
        ));

        $registry->define(new SettingDefinition(
            key: 'sd_in_cost',
            type: SettingDefinition::BOOLEAN,
            default: true,
            group: 'costing',
            label: 'Include SD in stock cost',
            help: 'Supplementary duty is normally not creditable, so it stays in cost.',
        ));

        $registry->define(new SettingDefinition(
            key: 'costing_mode',
            type: SettingDefinition::CHOICE,
            default: 'weighted_average',
            group: 'costing',
            label: 'Costing mode',
            options: [
                'weighted_average' => 'Weighted average',
                'batch_fefo' => 'Batch FEFO',
            ],
        ));

        $registry->define(new SettingDefinition(
            key: 'block_negative_stock',
            type: SettingDefinition::BOOLEAN,
            default: false,
            group: 'costing',
            label: 'Block sales that would go negative',
            help: 'When off, an oversell posts with a warning and is costed at the last average.',
        ));

        $registry->define(new SettingDefinition(
            key: 'purchase_bonus_cost',
            type: SettingDefinition::CHOICE,
            default: 'zero',
            group: 'costing',
            label: 'Purchase bonus stock cost',
            options: [
                'zero' => 'Zero (lowers the average)',
                'list_price' => 'At unit price (other income later)',
            ],
        ));

        $registry->define(new SettingDefinition(
            key: 'count_commission',
            type: SettingDefinition::BOOLEAN,
            default: true,
            group: 'commission',
            label: 'Count commission in profit',
            effectiveDated: true,
        ));

        $registry->define(new SettingDefinition(
            key: 'commission_pct',
            type: SettingDefinition::DECIMAL,
            default: '5',
            group: 'commission',
            label: 'Commission percent',
            effectiveDated: true,
            rules: ['decimal:0,4', 'min:0', 'max:100'],
        ));

        $registry->define(new SettingDefinition(
            key: 'commission_vat_pct',
            type: SettingDefinition::DECIMAL,
            default: '0.62',
            group: 'commission',
            label: 'Commission VAT percent',
            effectiveDated: true,
            rules: ['decimal:0,4', 'min:0', 'max:100'],
        ));

        $registry->define(new SettingDefinition(
            key: 'near_expiry_days',
            type: SettingDefinition::INTEGER,
            default: 60,
            group: 'costing',
            label: 'Near-expiry alert window (days)',
            help: 'Batches expiring within this many days appear on stock alerts.',
            rules: ['integer', 'min:1', 'max:365'],
        ));

        $registry->define(new SettingDefinition(
            key: 'commission_base',
            type: SettingDefinition::CHOICE,
            default: 'sales',
            group: 'commission',
            label: 'Commission base',
            effectiveDated: true,
            options: [
                'sales' => 'Net sales revenue',
                'purchase' => 'Purchase value ex-VAT',
            ],
        ));
    }
}
