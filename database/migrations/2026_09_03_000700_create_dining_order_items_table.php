<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frozen_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 6);
            // pending -> preparing -> ready -> served
            $table->string('kitchen_status')->default('pending');
            $table->timestamps();

            $table->index(['frozen_sale_id']);
            $table->index(['kitchen_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_order_items');
    }
};
