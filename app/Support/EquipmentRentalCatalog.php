<?php

namespace App\Support;

use App\Enums\EquipmentType;

/**
 * Precios de alquiler de hardware (impresora termica, lector de codigo de
 * barras) que el propio negocio compra y renta de por vida a las empresas
 * clientes. El kit combinado tiene descuento frente a rentar cada equipo
 * por separado para incentivar llevar ambos.
 */
class EquipmentRentalCatalog
{
    private const UNIT_COST = [
        'thermal_printer' => 110000.00,
        'barcode_scanner' => 100000.00,
    ];

    private const MONTHLY_PRICE = [
        'thermal_printer' => 15000.00,
        'barcode_scanner' => 12000.00,
    ];

    private const KIT_MONTHLY_PRICE = 25000.00;

    public static function unitCost(EquipmentType $type): float
    {
        return self::UNIT_COST[$type->value];
    }

    public static function monthlyPrice(EquipmentType $type): float
    {
        return self::MONTHLY_PRICE[$type->value];
    }

    /**
     * Total mensual a facturar segun cuantas unidades activas tiene la
     * empresa de cada tipo. Empareja impresoras con lectores al precio de
     * kit (mas barato que por separado) y cobra el resto de unidades
     * sueltas a su precio individual. Ej: 2 impresoras + 1 lector = 1 kit +
     * 1 impresora suelta.
     */
    public static function monthlyTotalForActiveCounts(int $printerCount, int $scannerCount): float
    {
        $kits = min($printerCount, $scannerCount);
        $extraPrinters = $printerCount - $kits;
        $extraScanners = $scannerCount - $kits;

        return ($kits * self::KIT_MONTHLY_PRICE)
            + ($extraPrinters * self::MONTHLY_PRICE[EquipmentType::ThermalPrinter->value])
            + ($extraScanners * self::MONTHLY_PRICE[EquipmentType::BarcodeScanner->value]);
    }
}
