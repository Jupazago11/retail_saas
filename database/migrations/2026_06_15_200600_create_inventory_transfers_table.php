<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('reason', 120);
            $table->text('notes')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('posted_to_inventory_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'source_warehouse_id']);
            $table->index(['company_id', 'destination_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
