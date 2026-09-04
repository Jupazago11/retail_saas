<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            // Posicion en el plano visual, en porcentaje (0-100) del mismo
            // viewBox que dining_floor_plans.outline_points. Nula hasta que
            // el dueño ubique la mesa en el mapa; mientras tanto la mesa
            // sigue operando normal (la pagina de mesas cae a la vista de
            // lista si no hay plano o posiciones guardadas).
            $table->decimal('pos_x', 5, 2)->nullable()->after('capacity');
            $table->decimal('pos_y', 5, 2)->nullable()->after('pos_x');
            $table->string('shape')->default('square')->after('pos_y');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y', 'shape']);
        });
    }
};
