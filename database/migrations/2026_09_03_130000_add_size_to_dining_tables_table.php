<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            // Tamano de la mesa en el plano, en porcentaje (0-100) del mismo
            // viewBox que pos_x/pos_y — asi crece o encoge proporcional al
            // lienzo sin importar la resolucion. El dueño lo ajusta arrastrando
            // la esquina de la mesa; el valor que deja queda como tamano por
            // defecto para las siguientes mesas nuevas que agregue en esa
            // misma sesion de edicion (comportamiento en JS, no en BD).
            $table->decimal('size', 5, 2)->default(8)->after('shape');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }
};
