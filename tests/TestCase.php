<?php

namespace Tests;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\Permission;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use InvalidArgumentException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles personalizados no dependen de un catalogo global de plantillas
     * (se elimino — ver migracion 2026_08_13_100100_drop_role_templates).
     * Esto solo reproduce, para pruebas, los mismos paquetes de permisos
     * que antes traian las plantillas 'cashier', 'seller', etc., creando
     * un CompanyRole real en la empresa de la prueba.
     */
    protected function companyRolePreset(Company $company, string $presetCode): CompanyRole
    {
        $presets = [
            'cashier' => [
                'name' => 'Cajero',
                'permissions' => ['products.view', 'sales.view', 'sales.create', 'sales.freeze', 'cash.open', 'cash.close', 'cash.view_difference'],
            ],
            'seller' => [
                'name' => 'Vendedor',
                'permissions' => ['products.view', 'sales.view', 'sales.create', 'sales.freeze', 'sales.apply_discount'],
            ],
            'purchasing_manager' => [
                'name' => 'Jefe de compras',
                'permissions' => ['masters.view', 'products.view', 'products.create', 'products.update', 'purchases.view', 'purchases.create', 'suppliers.view', 'suppliers.manage', 'payables.view', 'inventory.view'],
            ],
            'inventory_manager' => [
                'name' => 'Jefe de inventario',
                'permissions' => ['masters.view', 'masters.create', 'masters.update', 'masters.archive', 'masters.restore', 'products.view', 'products.create', 'products.update', 'products.archive', 'products.restore', 'inventory.view', 'inventory.adjust', 'inventory.transfer'],
            ],
            'accounting_assistant' => [
                'name' => 'Auxiliar contable',
                'permissions' => ['reports.view', 'reports.view_costs', 'credit.view', 'suppliers.view', 'suppliers.manage', 'payables.view', 'payables.manage', 'subscriptions.view'],
            ],
        ];

        if (! isset($presets[$presetCode])) {
            throw new InvalidArgumentException("Unknown company role preset: {$presetCode}");
        }

        $preset = $presets[$presetCode];

        $role = CompanyRole::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => $presetCode],
            ['display_name' => $preset['name'], 'status' => RecordStatus::Active->value]
        );

        $role->permissions()->sync(
            Permission::query()->whereIn('code', $preset['permissions'])->pluck('id')
        );

        return $role;
    }

    public function createApplication()
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'SESSION_DRIVER' => 'array',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
