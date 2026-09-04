<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Nullable: solo se llena cuando la venta viene de Mesas y
            // Comandas. Una sola FrozenSale puede terminar en VARIAS Sale
            // cuando la cuenta se divide por items entre varios pagadores
            // (ver App\Actions\Dining\SplitDiningTableBill) — por eso este
            // FK vive en `sales`, no se puede modelar con el puntero unico
            // `frozen_sales.converted_sale_id`.
            $table->foreignId('frozen_sale_id')->nullable()->after('warehouse_id')
                ->constrained('frozen_sales')->nullOnDelete();
            // Etiqueta libre del pagador ("Persona 1", "Juan") cuando la
            // venta es una porcion de una cuenta dividida. Null en una
            // venta normal o en una mesa cobrada de una sola vez.
            $table->string('payer_label', 60)->nullable()->after('frozen_sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frozen_sale_id');
            $table->dropColumn('payer_label');
        });
    }
};
