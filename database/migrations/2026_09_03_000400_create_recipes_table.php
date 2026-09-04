<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('yield_quantity', 14, 6)->default(1);
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();

            $table->unique(['company_id', 'product_id']);
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_product_id')->constrained('products')->restrictOnDelete();
            // Null = quantity ya esta en la unidad base del insumo. Con valor,
            // quantity esta en la presentacion indicada y se convierte con
            // ProductPresentationConverter (mismo patron que SaleItem/PurchaseItem).
            $table->foreignId('ingredient_presentation_id')->nullable()
                ->constrained('product_presentations')->restrictOnDelete();
            $table->decimal('quantity', 14, 6);
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
    }
};
