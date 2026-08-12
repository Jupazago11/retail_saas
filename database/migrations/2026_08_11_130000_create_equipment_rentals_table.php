<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('equipment_type');
            $table->string('status')->default('requested');
            $table->foreignId('replaces_rental_id')->nullable()->constrained('equipment_rentals')->nullOnDelete();
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('monthly_price', 10, 2);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('pending_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'equipment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_rentals');
    }
};
