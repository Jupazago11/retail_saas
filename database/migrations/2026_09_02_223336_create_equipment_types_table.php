<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('monthly_price', 10, 2);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // Catalogo hardcodeado anterior (app/Support/EquipmentRentalCatalog.php),
        // sembrado aqui para no perder los precios vigentes al pasar a tabla editable.
        DB::table('equipment_types')->insert([
            [
                'code' => 'thermal_printer',
                'name' => 'Impresora termica',
                'unit_cost' => 110000.00,
                'monthly_price' => 15000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'barcode_scanner',
                'name' => 'Lector de codigo de barras',
                'unit_cost' => 100000.00,
                'monthly_price' => 12000.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_types');
    }
};
