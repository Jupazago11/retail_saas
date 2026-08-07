<?php

use App\Enums\InventoryAdjustmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('adjustment_type')->default(InventoryAdjustmentType::Increase->value);
            $table->string('reason', 120);
            $table->text('notes')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            $table->timestamp('posted_to_inventory_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'adjustment_type']);
            $table->index(['company_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
