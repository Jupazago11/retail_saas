<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_order_items', function (Blueprint $table) {
            // Instruccion corta del cliente para ese plato puntual (ej. "sin
            // cebolla", "sin azucar") — se captura al agregar el plato y la
            // ve cocina en su pantalla, no es el mismo campo que
            // frozen_sales.notes (nota de la venta completa).
            $table->string('notes')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('dining_order_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
