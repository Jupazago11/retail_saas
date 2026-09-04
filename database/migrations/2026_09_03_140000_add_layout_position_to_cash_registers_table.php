<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            // Posicion opcional en el plano visual del salon (mismo viewBox
            // porcentual 0-100 que dining_tables.pos_x/pos_y). Nula por
            // defecto: mostrar una caja en el plano es una decision explicita
            // del dueño, no todas las empresas la quieren ahi.
            $table->decimal('pos_x', 5, 2)->nullable()->after('printer_type');
            $table->decimal('pos_y', 5, 2)->nullable()->after('pos_x');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y']);
        });
    }
};
