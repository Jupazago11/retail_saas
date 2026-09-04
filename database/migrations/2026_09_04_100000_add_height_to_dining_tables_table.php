<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            // Nula = misma dimension que `size` (cuadrada/redonda uniforme,
            // el comportamiento de siempre). Con un valor propio, la mesa
            // (solo si shape=square — una mesa redonda no se estira) se
            // dibuja rectangular: `size` sigue siendo el ancho, `height` el
            // alto. Se arrastra igual que el tamaño de siempre, solo que
            // ahora el asa de la esquina puede mover ancho y alto por
            // separado en vez de forzarlos iguales.
            $table->decimal('height', 5, 2)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('height');
        });
    }
};
