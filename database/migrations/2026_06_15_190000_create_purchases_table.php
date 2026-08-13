<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number', 120)->nullable();
            $table->string('purchase_type', 50)->default('invoice');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'branch_id']);
            $table->index(['company_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
