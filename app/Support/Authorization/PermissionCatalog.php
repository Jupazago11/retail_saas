<?php

namespace App\Support\Authorization;

class PermissionCatalog
{
    public static function permissions(): array
    {
        return [
            ['code' => 'masters.view', 'name' => 'Ver maestras', 'module_code' => 'masters'],
            ['code' => 'masters.create', 'name' => 'Crear maestras', 'module_code' => 'masters'],
            ['code' => 'masters.update', 'name' => 'Editar maestras', 'module_code' => 'masters'],
            ['code' => 'masters.archive', 'name' => 'Archivar maestras', 'module_code' => 'masters'],
            ['code' => 'masters.restore', 'name' => 'Restaurar maestras', 'module_code' => 'masters'],
            ['code' => 'products.view', 'name' => 'Ver catalogo de productos', 'module_code' => 'products'],
            ['code' => 'products.create', 'name' => 'Crear catalogo de productos', 'module_code' => 'products'],
            ['code' => 'products.update', 'name' => 'Editar catalogo de productos', 'module_code' => 'products'],
            ['code' => 'products.archive', 'name' => 'Archivar catalogo de productos', 'module_code' => 'products'],
            ['code' => 'products.restore', 'name' => 'Restaurar catalogo de productos', 'module_code' => 'products'],
            ['code' => 'inventory.view', 'name' => 'Ver inventario', 'module_code' => 'inventory'],
            ['code' => 'inventory.adjust', 'name' => 'Ajustar inventario', 'module_code' => 'inventory'],
            ['code' => 'inventory.transfer', 'name' => 'Trasladar inventario', 'module_code' => 'inventory'],
            ['code' => 'purchases.view', 'name' => 'Ver compras', 'module_code' => 'purchases'],
            ['code' => 'purchases.create', 'name' => 'Crear compras', 'module_code' => 'purchases'],
            ['code' => 'suppliers.view', 'name' => 'Ver proveedores', 'module_code' => 'suppliers'],
            ['code' => 'suppliers.manage', 'name' => 'Gestionar proveedores', 'module_code' => 'suppliers'],
            ['code' => 'payables.view', 'name' => 'Ver cuentas por pagar', 'module_code' => 'payables'],
            ['code' => 'payables.manage', 'name' => 'Gestionar cuentas por pagar', 'module_code' => 'payables'],
            ['code' => 'customers.view', 'name' => 'Ver clientes', 'module_code' => 'customers'],
            ['code' => 'customers.manage', 'name' => 'Gestionar clientes', 'module_code' => 'customers'],
            ['code' => 'sales.view', 'name' => 'Ver ventas', 'module_code' => 'sales'],
            ['code' => 'sales.create', 'name' => 'Crear ventas', 'module_code' => 'sales'],
            ['code' => 'sales.freeze', 'name' => 'Congelar ventas', 'module_code' => 'sales'],
            ['code' => 'sales.cancel', 'name' => 'Anular ventas', 'module_code' => 'sales'],
            ['code' => 'sales.return', 'name' => 'Devolver ventas', 'module_code' => 'sales'],
            ['code' => 'sales.apply_discount', 'name' => 'Aplicar descuentos', 'module_code' => 'sales'],
            ['code' => 'sales.select_alternative_price', 'name' => 'Elegir precio alterno', 'module_code' => 'sales'],
            ['code' => 'cash.open', 'name' => 'Abrir caja', 'module_code' => 'cash'],
            ['code' => 'cash.close', 'name' => 'Cerrar caja', 'module_code' => 'cash'],
            ['code' => 'cash.view_difference', 'name' => 'Ver diferencias de caja', 'module_code' => 'cash'],
            ['code' => 'credit.view', 'name' => 'Ver credito', 'module_code' => 'credit'],
            ['code' => 'credit.manage', 'name' => 'Gestionar credito', 'module_code' => 'credit'],
            ['code' => 'loyalty.manage', 'name' => 'Gestionar fidelizacion', 'module_code' => 'loyalty'],
            ['code' => 'promotions.manage', 'name' => 'Gestionar promociones', 'module_code' => 'promotions'],
            ['code' => 'reports.view', 'name' => 'Ver reportes', 'module_code' => 'reports'],
            ['code' => 'reports.view_costs', 'name' => 'Ver costos en reportes', 'module_code' => 'reports'],
            ['code' => 'settings.manage', 'name' => 'Gestionar configuracion', 'module_code' => 'settings'],
            ['code' => 'users.manage', 'name' => 'Gestionar usuarios', 'module_code' => 'users'],
            ['code' => 'roles.manage', 'name' => 'Gestionar roles', 'module_code' => 'roles'],
            ['code' => 'subscriptions.view', 'name' => 'Ver suscripciones', 'module_code' => 'subscriptions'],
        ];
    }
}
