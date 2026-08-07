<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 50);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity_in', 14, 6)->default(0);
            $table->decimal('quantity_out', 14, 6)->default(0);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('balance_quantity', 14, 6)->default(0);
            $table->decimal('balance_cost', 14, 4)->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'warehouse_id', 'occurred_at']);
            $table->index(['product_id', 'product_variant_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
