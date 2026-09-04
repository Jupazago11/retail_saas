<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El negocio no permite huecos en la numeracion de mesas activas
        // (ver App\Models\DiningTable::renumberActiveTables()): al archivar
        // una mesa, las siguientes bajan un numero para cerrar el hueco. Un
        // indice unico plano no distingue status, asi que un numero usado
        // alguna vez por una mesa archivada quedaba bloqueado para siempre
        // aunque ya no hubiera ninguna mesa activa con ese numero. La
        // unicidad de `name` ahora se garantiza a nivel de aplicacion, solo
        // entre mesas activas (uniqueName()/renumberActiveTables()).
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'branch_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->unique(['company_id', 'branch_id', 'name']);
        });
    }
};
