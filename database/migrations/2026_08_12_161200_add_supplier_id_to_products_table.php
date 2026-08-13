<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Opcional: no todos los productos tienen un proveedor asignado
            // todavia. Sirve para filtrar/agrupar el catalogo (ej. el
            // asistente de carga inicial de inventario, paginado por
            // proveedor), igual que ya se hace con categoria y marca.
            $table->foreignId('supplier_id')->nullable()->after('brand_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
