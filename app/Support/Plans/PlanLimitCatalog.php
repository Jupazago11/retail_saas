<?php

namespace App\Support\Plans;

class PlanLimitCatalog
{
    public static function definitions(): array
    {
        return [
            'max_users' => 'Usuarios maximos',
            'max_companies' => 'Empresas maximas',
            'max_branches' => 'Sucursales maximas',
            'max_warehouses' => 'Bodegas maximas',
            'max_cash_registers' => 'Cajas maximas',
            'max_products' => 'Productos maximos',
            'max_monthly_sales' => 'Ventas mensuales maximas',
            'max_electronic_documents' => 'Documentos electronicos maximos',
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::definitions());
    }
}
