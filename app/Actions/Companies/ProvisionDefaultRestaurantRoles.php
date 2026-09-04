<?php

namespace App\Actions\Companies;

use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\Permission;

/**
 * Roles genericos automaticos del vertical restaurante: Cajero, Mesero y
 * Cocina, ademas del dueño. Es la unica excepcion deliberada a "cada empresa
 * arma sus propios roles personalizados, sin catalogo compartido" (ver
 * docs/decisiones-tecnicas.md, refactor role_templates -> company_roles) —
 * el modelo de negocio restaurante exige estos 3 puestos desde el primer
 * dia, asi que no tiene sentido que el dueño los arme a mano cada vez.
 */
class ProvisionDefaultRestaurantRoles
{
    public const ROLES = [
        'CAJERO' => [
            'display_name' => 'Cajero',
            // sales.* para su modulo de ventas normal; dining.orders para
            // poder tomar/editar pedidos igual que el mesero (hay negocios
            // sin meseros); kitchen.manage para ver el estado de cocina
            // desde caja sin poder editar mesas (eso sigue siendo
            // dining.manage, exclusivo del dueño/administrador).
            'permissions' => ['sales.view', 'sales.create', 'dining.orders', 'kitchen.manage'],
        ],
        'MESERO' => [
            'display_name' => 'Mesero',
            // dining.orders (no dining.manage): el mesero solo ve su modulo
            // de toma de pedidos (mapa/lista de mesas + comanda), nunca el
            // CRUD administrativo de mesas ni el editor del plano — eso es
            // dining.manage, reservado al dueño/administrador.
            'permissions' => ['dining.orders'],
        ],
        'COCINA' => [
            'display_name' => 'Cocina',
            'permissions' => ['kitchen.manage'],
        ],
    ];

    public function handle(Company $company): void
    {
        $permissionIdsByCode = Permission::query()->pluck('id', 'code');

        foreach (self::ROLES as $code => $definition) {
            $role = CompanyRole::query()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['display_name' => $definition['display_name'], 'status' => RecordStatus::Active->value]
            );

            $permissionIds = collect($definition['permissions'])
                ->map(fn (string $permissionCode) => $permissionIdsByCode[$permissionCode] ?? null)
                ->filter()
                ->values();

            $role->permissions()->sync($permissionIds);
        }
    }
}
