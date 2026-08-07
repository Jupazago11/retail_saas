<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('barcode', 120)->nullable();
            $table->decimal('conversion_factor', 14, 6);
            $table->decimal('price_1', 14, 2)->default(0);
            $table->decimal('price_2', 14, 2)->nullable();
            $table->decimal('price_3', 14, 2)->nullable();
            $table->string('status')->default(RecordStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'product_id', 'name']);
            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_presentations');
    }
};
