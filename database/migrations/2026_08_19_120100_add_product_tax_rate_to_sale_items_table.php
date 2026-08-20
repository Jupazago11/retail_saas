<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Tasa de IVA del producto al momento de la venta, distinta del
            // `tax_rate` existente (que es un impuesto manual sumado aparte
            // sobre el subtotal). Esta es el IVA que ya viene incluido en el
            // precio y solo se usa para desglosar el ticket, sin afectar
            // subtotal/descuento/total.
            $table->decimal('product_tax_rate', 6, 2)->nullable()->after('cost_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('product_tax_rate');
        });
    }
};
