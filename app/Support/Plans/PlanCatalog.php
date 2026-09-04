<?php

namespace App\Support\Plans;

class PlanCatalog
{
    public static function modules(): array
    {
        return [
            ['code' => 'products', 'name' => 'Productos', 'business_type_code' => null],
            ['code' => 'inventory', 'name' => 'Inventario', 'business_type_code' => null],
            ['code' => 'purchases', 'name' => 'Compras', 'business_type_code' => null],
            ['code' => 'pos', 'name' => 'Punto de venta', 'business_type_code' => null],
            ['code' => 'cash', 'name' => 'Caja', 'business_type_code' => null],
            ['code' => 'credit', 'name' => 'Credito', 'business_type_code' => null],
            ['code' => 'loyalty', 'name' => 'Fidelizacion', 'business_type_code' => null],
            ['code' => 'promotions', 'name' => 'Promociones', 'business_type_code' => null],
            ['code' => 'reports', 'name' => 'Reportes', 'business_type_code' => null],
            ['code' => 'imports', 'name' => 'Importaciones', 'business_type_code' => null],
            ['code' => 'electronic_billing', 'name' => 'Facturacion electronica', 'business_type_code' => null],
            ['code' => 'dining', 'name' => 'Mesas y comandas', 'business_type_code' => 'restaurant'],
            ['code' => 'kitchen', 'name' => 'Cocina (KDS)', 'business_type_code' => 'restaurant'],
        ];
    }

    public static function features(): array
    {
        return [
            ['module_code' => 'products', 'code' => 'products.multiple_prices', 'name' => 'Multiples precios'],
            ['module_code' => 'products', 'code' => 'products.variants', 'name' => 'Variantes'],
            ['module_code' => 'products', 'code' => 'products.presentations', 'name' => 'Presentaciones'],
            ['module_code' => 'products', 'code' => 'products.weighable', 'name' => 'Pesables'],
            ['module_code' => 'inventory', 'code' => 'inventory.negative_stock', 'name' => 'Stock negativo'],
            ['module_code' => 'inventory', 'code' => 'inventory.multiple_warehouses', 'name' => 'Multiples bodegas'],
            ['module_code' => 'pos', 'code' => 'pos.frozen_sales', 'name' => 'Ventas congeladas'],
            ['module_code' => 'pos', 'code' => 'pos.mixed_payments', 'name' => 'Pagos mixtos'],
            ['module_code' => 'pos', 'code' => 'pos.manual_discounts', 'name' => 'Descuentos manuales'],
            ['module_code' => 'pos', 'code' => 'pos.combos', 'name' => 'Combos'],
            ['module_code' => 'cash', 'code' => 'cash.opening_closing', 'name' => 'Apertura y cierre'],
            ['module_code' => 'credit', 'code' => 'credit.enabled', 'name' => 'Credito habilitado'],
            ['module_code' => 'loyalty', 'code' => 'loyalty.enabled', 'name' => 'Fidelizacion habilitada'],
            ['module_code' => 'reports', 'code' => 'reports.profitability', 'name' => 'Rentabilidad'],
            ['module_code' => 'imports', 'code' => 'imports.excel', 'name' => 'Importacion Excel'],
        ];
    }

    public static function plans(): array
    {
        return [
            [
                'code' => 'basic',
                'name' => 'Basic',
                'business_type_code' => 'general',
                'billing_period' => 'monthly',
                'base_price' => '0.00',
                'modules' => ['products', 'pos', 'cash', 'reports'],
                'features' => ['products.presentations', 'pos.mixed_payments', 'cash.opening_closing'],
                'limits' => [
                    'max_users' => 3,
                    'max_companies' => 1,
                    'max_branches' => 1,
                    'max_warehouses' => 1,
                    'max_cash_registers' => 1,
                    'max_products' => 1500,
                    'max_monthly_sales' => 3000,
                    'max_electronic_documents' => 0,
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'business_type_code' => 'general',
                'billing_period' => 'monthly',
                'base_price' => '0.00',
                'modules' => ['products', 'inventory', 'purchases', 'pos', 'cash', 'credit', 'loyalty', 'promotions', 'reports', 'imports'],
                'features' => [
                    'products.multiple_prices',
                    'products.variants',
                    'products.presentations',
                    'products.weighable',
                    'inventory.multiple_warehouses',
                    'pos.frozen_sales',
                    'pos.mixed_payments',
                    'pos.manual_discounts',
                    'cash.opening_closing',
                    'credit.enabled',
                    'loyalty.enabled',
                    'reports.profitability',
                    'imports.excel',
                ],
                'limits' => [
                    'max_users' => 10,
                    'max_companies' => 1,
                    'max_branches' => 3,
                    'max_warehouses' => 5,
                    'max_cash_registers' => 5,
                    'max_products' => 10000,
                    'max_monthly_sales' => 20000,
                    'max_electronic_documents' => 1000,
                ],
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'business_type_code' => 'general',
                'billing_period' => 'monthly',
                'base_price' => '0.00',
                'modules' => ['products', 'inventory', 'purchases', 'pos', 'cash', 'credit', 'loyalty', 'promotions', 'reports', 'imports', 'electronic_billing'],
                'features' => [
                    'products.multiple_prices',
                    'products.variants',
                    'products.presentations',
                    'products.weighable',
                    'inventory.multiple_warehouses',
                    'pos.frozen_sales',
                    'pos.mixed_payments',
                    'pos.manual_discounts',
                    'pos.combos',
                    'cash.opening_closing',
                    'credit.enabled',
                    'loyalty.enabled',
                    'reports.profitability',
                    'imports.excel',
                ],
                'limits' => [
                    'max_users' => 50,
                    'max_companies' => 3,
                    'max_branches' => 10,
                    'max_warehouses' => 20,
                    'max_cash_registers' => 20,
                    'max_products' => 50000,
                    'max_monthly_sales' => 100000,
                    'max_electronic_documents' => 10000,
                ],
            ],
            [
                'code' => 'basic',
                'name' => 'Basic',
                'business_type_code' => 'restaurant',
                'billing_period' => 'monthly',
                'base_price' => '0.00',
                'modules' => ['products', 'dining', 'cash', 'reports'],
                'features' => ['products.presentations', 'cash.opening_closing'],
                'limits' => [
                    // Minimo 4: el dueño + los 3 roles automaticos del vertical
                    // restaurante (Cajero, Mesero, Cocina) — ver
                    // ProvisionDefaultRestaurantRoles. PlansPage::saveEdit()
                    // rechaza bajar esto de 4 para un plan de este vertical.
                    'max_users' => 4,
                    'max_companies' => 1,
                    'max_branches' => 1,
                    'max_warehouses' => 1,
                    'max_cash_registers' => 1,
                    'max_products' => 1500,
                    'max_monthly_sales' => 3000,
                    'max_electronic_documents' => 0,
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'business_type_code' => 'restaurant',
                'billing_period' => 'monthly',
                'base_price' => '0.00',
                'modules' => ['products', 'inventory', 'purchases', 'dining', 'kitchen', 'cash', 'credit', 'loyalty', 'promotions', 'reports', 'imports'],
                'features' => [
                    'products.multiple_prices',
                    'products.variants',
                    'products.presentations',
                    'products.weighable',
                    'inventory.multiple_warehouses',
                    'cash.opening_closing',
                    'credit.enabled',
                    'loyalty.enabled',
                    'reports.profitability',
                    'imports.excel',
                ],
                'limits' => [
                    'max_users' => 10,
                    'max_companies' => 1,
                    'max_branches' => 3,
                    'max_warehouses' => 5,
                    'max_cash_registers' => 5,
                    'max_products' => 10000,
                    'max_monthly_sales' => 20000,
                    'max_electronic_documents' => 1000,
                ],
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'business_type_code' => 'restaurant',
                'billing_period' => 'monthly',
                'base_price' => '0.00',
                'modules' => ['products', 'inventory', 'purchases', 'dining', 'kitchen', 'cash', 'credit', 'loyalty', 'promotions', 'reports', 'imports', 'electronic_billing'],
                'features' => [
                    'products.multiple_prices',
                    'products.variants',
                    'products.presentations',
                    'products.weighable',
                    'inventory.multiple_warehouses',
                    'cash.opening_closing',
                    'credit.enabled',
                    'loyalty.enabled',
                    'reports.profitability',
                    'imports.excel',
                ],
                'limits' => [
                    'max_users' => 50,
                    'max_companies' => 3,
                    'max_branches' => 10,
                    'max_warehouses' => 20,
                    'max_cash_registers' => 20,
                    'max_products' => 50000,
                    'max_monthly_sales' => 100000,
                    'max_electronic_documents' => 10000,
                ],
            ],
        ];
    }
}
