<?php

namespace App\Enums;

enum EquipmentType: string
{
    case ThermalPrinter = 'thermal_printer';
    case BarcodeScanner = 'barcode_scanner';

    public function label(): string
    {
        return match ($this) {
            self::ThermalPrinter => 'Impresora termica',
            self::BarcodeScanner => 'Lector de codigo de barras',
        };
    }
}
