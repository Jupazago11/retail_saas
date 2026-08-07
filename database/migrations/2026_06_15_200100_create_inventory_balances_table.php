<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_on_hand', 14, 6)->default(0);
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'warehouse_id']);
            $table->index(['product_id', 'product_variant_id']);
            $table->unique(['company_id', 'warehouse_id', 'product_id', 'product_variant_id'], 'inventory_balances_full_unique');
        });

        DB::statement('
            CREATE UNIQUE INDEX inventory_balances_without_variant_unique
            ON inventory_balances (company_id, warehouse_id, product_id)
            WHERE product_variant_id IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_balances_without_variant_unique');
        Schema::dropIfExists('inventory_balances');
    }
};
