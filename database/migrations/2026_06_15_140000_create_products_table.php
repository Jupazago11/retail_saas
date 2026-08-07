<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->string('tax_id', 50)->nullable();
            $table->string('name');
            $table->string('sku', 80)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('price_1', 14, 2)->default(0);
            $table->decimal('price_2', 14, 2)->nullable();
            $table->decimal('price_3', 14, 2)->nullable();
            $table->decimal('margin_1', 8, 2)->nullable();
            $table->decimal('margin_2', 8, 2)->nullable();
            $table->decimal('margin_3', 8, 2)->nullable();
            $table->boolean('tracks_inventory')->default(true);
            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
