<?php

namespace App\Support\Settings;

class CompanySettingCatalog
{
    public static function definitions(): array
    {
        return [
            'general.legal_name' => ['group' => 'general', 'key' => 'legal_name', 'type' => 'string', 'default' => null],
            'general.display_name' => ['group' => 'general', 'key' => 'display_name', 'type' => 'string', 'default' => null],
            'general.tax_id' => ['group' => 'general', 'key' => 'tax_id', 'type' => 'string', 'default' => null],
            'general.phone' => ['group' => 'general', 'key' => 'phone', 'type' => 'string', 'default' => null],
            'general.address' => ['group' => 'general', 'key' => 'address', 'type' => 'string', 'default' => null],
            'general.logo_path' => ['group' => 'general', 'key' => 'logo_path', 'type' => 'string', 'default' => null],
            'general.primary_color' => ['group' => 'general', 'key' => 'primary_color', 'type' => 'string', 'default' => null],

            'pos.frozen_sales_enabled' => ['group' => 'pos', 'key' => 'frozen_sales_enabled', 'type' => 'boolean', 'default' => true],
            'pos.allow_alternative_prices' => ['group' => 'pos', 'key' => 'allow_alternative_prices', 'type' => 'boolean', 'default' => false],
            'pos.allow_manual_discounts' => ['group' => 'pos', 'key' => 'allow_manual_discounts', 'type' => 'boolean', 'default' => false],
            'pos.allow_promotion_stacking' => ['group' => 'pos', 'key' => 'allow_promotion_stacking', 'type' => 'boolean', 'default' => false],
            'pos.allow_negative_stock' => ['group' => 'pos', 'key' => 'allow_negative_stock', 'type' => 'boolean', 'default' => false],
            'pos.requires_open_cash_session' => ['group' => 'pos', 'key' => 'requires_open_cash_session', 'type' => 'boolean', 'default' => true],
            'pos.require_customer_for_credit_sale' => ['group' => 'pos', 'key' => 'require_customer_for_credit_sale', 'type' => 'boolean', 'default' => true],
            'pos.sale_document_prefix' => ['group' => 'pos', 'key' => 'sale_document_prefix', 'type' => 'string', 'default' => 'VTA-'],
            'pos.sale_document_starting_sequence' => ['group' => 'pos', 'key' => 'sale_document_starting_sequence', 'type' => 'integer', 'default' => 1],

            'inventory.inventory_enabled' => ['group' => 'inventory', 'key' => 'inventory_enabled', 'type' => 'boolean', 'default' => true],
            'inventory.minimum_stock_alerts_enabled' => ['group' => 'inventory', 'key' => 'minimum_stock_alerts_enabled', 'type' => 'boolean', 'default' => true],
            'inventory.default_cost_method' => ['group' => 'inventory', 'key' => 'default_cost_method', 'type' => 'string', 'default' => 'weighted_average'],
            // No se muestra en la pagina de Configuracion: es una bandera
            // interna que marca si la empresa ya contesto la pregunta inicial
            // de "quieres usar inventario" del asistente de carga.
            'inventory.setup_wizard_answered' => ['group' => 'inventory', 'key' => 'setup_wizard_answered', 'type' => 'boolean', 'default' => false],

            'cash.module_enabled' => ['group' => 'cash', 'key' => 'module_enabled', 'type' => 'boolean', 'default' => true],
            'cash.opening_required' => ['group' => 'cash', 'key' => 'opening_required', 'type' => 'boolean', 'default' => true],
            'cash.default_opening_amount' => ['group' => 'cash', 'key' => 'default_opening_amount', 'type' => 'decimal', 'default' => '0.0000'],
            'cash.allow_close_with_difference' => ['group' => 'cash', 'key' => 'allow_close_with_difference', 'type' => 'boolean', 'default' => false],

            'printing.ticket_format' => ['group' => 'printing', 'key' => 'ticket_format', 'type' => 'string', 'default' => 'thermal_80mm'],
            'printing.show_logo' => ['group' => 'printing', 'key' => 'show_logo', 'type' => 'boolean', 'default' => true],
            'printing.show_saas_branding' => ['group' => 'printing', 'key' => 'show_saas_branding', 'type' => 'boolean', 'default' => false],

            'credit.credit_enabled' => ['group' => 'credit', 'key' => 'credit_enabled', 'type' => 'boolean', 'default' => false],
            'credit.default_term_days' => ['group' => 'credit', 'key' => 'default_term_days', 'type' => 'integer', 'default' => 30],
            'credit.block_new_credit_if_overdue' => ['group' => 'credit', 'key' => 'block_new_credit_if_overdue', 'type' => 'boolean', 'default' => true],

            'loyalty.loyalty_enabled' => ['group' => 'loyalty', 'key' => 'loyalty_enabled', 'type' => 'boolean', 'default' => false],
            'loyalty.points_rule_type' => ['group' => 'loyalty', 'key' => 'points_rule_type', 'type' => 'string', 'default' => 'per_currency'],
            'loyalty.points_rate' => ['group' => 'loyalty', 'key' => 'points_rate', 'type' => 'decimal', 'default' => '1.0000'],
            'loyalty.points_expiration_days' => ['group' => 'loyalty', 'key' => 'points_expiration_days', 'type' => 'integer', 'default' => 365],

            'electronic_billing.enabled' => ['group' => 'electronic_billing', 'key' => 'enabled', 'type' => 'boolean', 'default' => false],
            'electronic_billing.provider' => ['group' => 'electronic_billing', 'key' => 'provider', 'type' => 'string', 'default' => 'factus'],
            'electronic_billing.environment' => ['group' => 'electronic_billing', 'key' => 'environment', 'type' => 'string', 'default' => 'sandbox'],
            'electronic_billing.resolution_number' => ['group' => 'electronic_billing', 'key' => 'resolution_number', 'type' => 'string', 'default' => null],
            'electronic_billing.prefix' => ['group' => 'electronic_billing', 'key' => 'prefix', 'type' => 'string', 'default' => null],
            'electronic_billing.technical_key' => ['group' => 'electronic_billing', 'key' => 'technical_key', 'type' => 'string', 'default' => null],
            'electronic_billing.software_id' => ['group' => 'electronic_billing', 'key' => 'software_id', 'type' => 'string', 'default' => null],
            'electronic_billing.software_pin' => ['group' => 'electronic_billing', 'key' => 'software_pin', 'type' => 'string', 'default' => null],
            'electronic_billing.sequence_current' => ['group' => 'electronic_billing', 'key' => 'sequence_current', 'type' => 'integer', 'default' => 1],
            'electronic_billing.sequence_max' => ['group' => 'electronic_billing', 'key' => 'sequence_max', 'type' => 'integer', 'default' => 1000],
        ];
    }

    public static function definition(string $groupKey, string $settingKey): ?array
    {
        return self::definitions()["{$groupKey}.{$settingKey}"] ?? null;
    }
}
