<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 60);
            $table->decimal('points', 14, 4);
            $table->decimal('cash_equivalent', 14, 2)->default(0);
            $table->decimal('balance_after', 14, 4);
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'loyalty_account_id', 'occurred_at']);
            $table->index(['company_id', 'sale_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_movements');
    }
};
